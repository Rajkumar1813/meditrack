<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;
use MediTrack\Core\Paginator;

class OrderRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public function findByIdAndUser(int $id, int $userId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM orders WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public function findByOrderNo(string $orderNo): ?array
    {
        return $this->db->fetchOne('SELECT * FROM orders WHERE order_no = ?', [$orderNo]);
    }

    public function findByUserAndIdempotencyKey(int $userId, string $idempotencyKey): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM orders WHERE user_id = ? AND idempotency_key = ?',
            [$userId, $idempotencyKey]
        );
    }

    public function create(array $orderData, array $items): int
    {
        return $this->db->transaction(function (Database $db) use ($orderData, $items) {
            $sql = 'INSERT INTO orders (
                        order_no, user_id, address_snapshot, status, rx_required,
                        rx_status, payment_method, payment_status, subtotal, discount,
                        gst_total, delivery_fee, total, idempotency_key, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )';

            $db->execute($sql, [
                $orderData['order_no'],
                $orderData['user_id'],
                is_string($orderData['address_snapshot']) ? $orderData['address_snapshot'] : json_encode($orderData['address_snapshot']),
                $orderData['status'] ?? 'pending',
                $orderData['rx_required'] ?? 0,
                $orderData['rx_status'] ?? 'not_required',
                $orderData['payment_method'],
                $orderData['payment_status'] ?? 'unpaid',
                $orderData['subtotal'],
                $orderData['discount'] ?? 0.00,
                $orderData['gst_total'] ?? 0.00,
                $orderData['delivery_fee'] ?? 0.00,
                $orderData['total'],
                $orderData['idempotency_key'] ?? null,
            ]);

            $orderId = (int)$db->lastInsertId();

            // Insert order items
            $itemSql = 'INSERT INTO order_items (
                            order_id, medicine_id, name_snapshot, quantity,
                            unit_price, mrp, gst_percent, line_total, requires_rx
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';

            foreach ($items as $item) {
                $db->execute($itemSql, [
                    $orderId,
                    $item['medicine_id'],
                    $item['name_snapshot'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['mrp'],
                    $item['gst_percent'] ?? 12.00,
                    $item['line_total'],
                    $item['requires_rx'] ?? 0,
                ]);

                $orderItemId = (int)$db->lastInsertId();

                // If allocated batches provided
                if (!empty($item['allocated_batches'])) {
                    $batchSql = 'INSERT INTO order_item_batches (order_item_id, batch_id, quantity) VALUES (?, ?, ?)';
                    foreach ($item['allocated_batches'] as $b) {
                        $db->execute($batchSql, [$orderItemId, $b['batch_id'], $b['quantity']]);
                    }
                }
            }

            // Record initial status in history
            $db->execute(
                'INSERT INTO order_status_history (order_id, from_status, to_status, note, created_at)
                 VALUES (?, NULL, ?, ?, CURRENT_TIMESTAMP)',
                [$orderId, $orderData['status'] ?? 'pending', 'Order placed']
            );

            return $orderId;
        });
    }

    public function updateStatus(
        int $id,
        string $toStatus,
        ?string $note = null,
        ?int $changedBy = null,
        array $extra = []
    ): bool {
        $current = $this->findById($id);
        if (!$current) {
            return false;
        }

        $fromStatus = $current['status'];

        $setParts = ['status = ?', 'updated_at = CURRENT_TIMESTAMP'];
        $params = [$toStatus];

        match ($toStatus) {
            'confirmed' => $setParts[] = 'confirmed_at = CURRENT_TIMESTAMP',
            'packed' => $setParts[] = 'packed_at = CURRENT_TIMESTAMP',
            'shipped' => $setParts[] = 'shipped_at = CURRENT_TIMESTAMP',
            'delivered' => $setParts[] = 'delivered_at = CURRENT_TIMESTAMP',
            'cancelled' => $setParts[] = 'cancelled_at = CURRENT_TIMESTAMP',
            default => null,
        };

        if (array_key_exists('courier', $extra)) {
            $setParts[] = 'courier = ?';
            $params[] = $extra['courier'];
        }
        if (array_key_exists('tracking_no', $extra)) {
            $setParts[] = 'tracking_no = ?';
            $params[] = $extra['tracking_no'];
        }
        if (array_key_exists('cancel_reason', $extra)) {
            $setParts[] = 'cancel_reason = ?';
            $params[] = $extra['cancel_reason'];
        }
        if (array_key_exists('cancelled_by', $extra)) {
            $setParts[] = 'cancelled_by = ?';
            $params[] = $extra['cancelled_by'];
        }
        if (array_key_exists('payment_status', $extra)) {
            $setParts[] = 'payment_status = ?';
            $params[] = $extra['payment_status'];
        }
        if (array_key_exists('rx_status', $extra)) {
            $setParts[] = 'rx_status = ?';
            $params[] = $extra['rx_status'];
        }

        $params[] = $id;
        $sql = 'UPDATE orders SET ' . implode(', ', $setParts) . ' WHERE id = ?';

        $updated = $this->db->execute($sql, $params) > 0;

        if ($updated) {
            $this->db->execute(
                'INSERT INTO order_status_history (order_id, from_status, to_status, note, changed_by, created_at)
                 VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
                [$id, $fromStatus, $toStatus, $note, $changedBy]
            );
        }

        return $updated;
    }

    public function updatePaymentStatus(int $id, string $paymentStatus): bool
    {
        return $this->db->execute(
            'UPDATE orders SET payment_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$paymentStatus, $id]
        ) > 0;
    }

    public function getItems(int $orderId): array
    {
        return $this->db->fetchAll('SELECT * FROM order_items WHERE order_id = ?', [$orderId]);
    }

    public function getItemsWithBatches(int $orderId): array
    {
        $items = $this->getItems($orderId);

        foreach ($items as &$item) {
            $batches = $this->db->fetchAll(
                'SELECT oib.id as allocation_id, oib.batch_id, oib.quantity,
                        b.batch_number, b.exp_date, b.selling_price, b.mrp
                 FROM order_item_batches oib
                 JOIN batches b ON oib.batch_id = b.id
                 WHERE oib.order_item_id = ?',
                [$item['id']]
            );
            $item['allocated_batches'] = $batches;
        }

        return $items;
    }

    public function getStatusHistory(int $orderId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id ASC',
            [$orderId]
        );
    }

    /**
     * Customer orders with cursor pagination.
     */
    public function findByUser(int $userId, ?string $cursor = null, int $limit = 20): array
    {
        $limit = min(Paginator::MAX_CURSOR_LIMIT, max(1, $limit));
        $cursorPayload = $cursor ? Paginator::decodeCursor($cursor) : null;
        $lastId = $cursorPayload['id'] ?? null;

        $params = [$userId];
        $where = 'WHERE user_id = ?';

        if ($lastId !== null) {
            $where .= ' AND id < ?';
            $params[] = (int)$lastId;
        }

        $params[] = $limit + 1; // Fetch 1 extra to determine has_more

        $sql = "SELECT id, order_no, user_id, status, rx_required, rx_status,
                       payment_method, payment_status, subtotal, discount, gst_total,
                       delivery_fee, total, courier, tracking_no, cancel_reason,
                       created_at, updated_at
                FROM orders
                {$where}
                ORDER BY id DESC
                LIMIT ?";

        $rows = $this->db->fetchAll($sql, $params);
        $hasMore = count($rows) > $limit;

        if ($hasMore) {
            array_pop($rows);
        }

        $nextCursor = null;
        if ($hasMore && !empty($rows)) {
            $lastItem = end($rows);
            $nextCursor = Paginator::encodeCursor(['id' => (int)$lastItem['id']]);
        }

        // Attach item count to summary
        foreach ($rows as &$row) {
            $row['item_count'] = (int)$this->db->fetchColumn(
                'SELECT SUM(quantity) FROM order_items WHERE order_id = ?',
                [$row['id']]
            );
        }

        return Paginator::cursor($rows, $limit, $nextCursor, $hasMore);
    }

    /**
     * Admin orders with offset pagination, multi-field filtering, and whitelisted sort.
     */
    public function paginateAdmin(
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $sort = 'id',
        string $dir = 'desc'
    ): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'o.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['payment_status'])) {
            $where[] = 'o.payment_status = ?';
            $params[] = $filters['payment_status'];
        }

        if (!empty($filters['payment_method'])) {
            $where[] = 'o.payment_method = ?';
            $params[] = $filters['payment_method'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'o.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'o.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $q = trim((string)($filters['q'] ?? $filters['search'] ?? ''));
        if ($q !== '') {
            $term = '%' . $q . '%';
            $where[] = '(o.order_no LIKE ? OR u.name LIKE ? OR u.phone LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereSql = implode(' AND ', $where);

        $totalSql = "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE {$whereSql}";
        $total = (int)$this->db->fetchColumn($totalSql, $params);

        $allowedSorts = ['id', 'order_no', 'created_at', 'total', 'status', 'payment_status'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $offset = max(0, ($page - 1) * $perPage);

        $sql = "SELECT o.*, u.name as customer_name, u.phone as customer_phone, u.email as customer_email
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE {$whereSql}
                ORDER BY o.{$sort} {$dir}, o.id {$dir}
                LIMIT {$perPage} OFFSET {$offset}";

        $rows = $this->db->fetchAll($sql, $params);

        foreach ($rows as &$row) {
            if (isset($row['address_snapshot']) && is_string($row['address_snapshot'])) {
                $row['address_snapshot'] = json_decode($row['address_snapshot'], true);
            }
            $row['item_count'] = (int)$this->db->fetchColumn(
                'SELECT SUM(quantity) FROM order_items WHERE order_id = ?',
                [$row['id']]
            );
        }

        return Paginator::offset($page, $perPage, $total, $rows);
    }

    /**
     * Find pending orders with online payment unpaid beyond given threshold minutes.
     */
    public function findExpiredPendingOrders(int $expiryMinutes = 15): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($expiryMinutes * 60));

        $sql = "SELECT * FROM orders
                WHERE status = 'pending'
                  AND payment_status = 'unpaid'
                  AND payment_method != 'cod'
                  AND created_at <= ?";

        return $this->db->fetchAll($sql, [$cutoff]);
    }
}
