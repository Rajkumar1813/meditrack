<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class MedicineRepository
{
    private Database $db;
    private ?\MediTrack\Core\Cache $cache;

    public function __construct(Database $db, ?\MediTrack\Core\Cache $cache = null)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT m.*, c.name as category_name, mf.name as manufacturer_name
             FROM medicines m
             LEFT JOIN categories c ON m.category_id = c.id
             LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
             WHERE m.id = ? AND m.deleted_at IS NULL',
            [$id]
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT m.*, c.name as category_name, mf.name as manufacturer_name
             FROM medicines m
             LEFT JOIN categories c ON m.category_id = c.id
             LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
             WHERE m.slug = ? AND m.deleted_at IS NULL',
            [$slug]
        );
    }

    public function create(array $data): int
    {
        // Enforce rule: requires_rx = schedule != 'OTC'
        $schedule = $data['schedule'] ?? 'OTC';
        $requiresRx = $schedule !== 'OTC' ? 1 : (int)($data['requires_rx'] ?? 0);

        $sql = 'INSERT INTO medicines (
                    name, slug, generic_name, manufacturer_id, category_id,
                    schedule, requires_rx, pack_size, gst_percent, description,
                    usage_info, side_effects, storage_info, image, is_active,
                    is_featured, notes, created_by, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )';

        $this->db->execute($sql, [
            $data['name'],
            $data['slug'],
            $data['generic_name'] ?? null,
            $data['manufacturer_id'] ?? null,
            $data['category_id'],
            $schedule,
            $requiresRx,
            $data['pack_size'] ?? null,
            $data['gst_percent'] ?? 12.00,
            $data['description'] ?? null,
            $data['usage_info'] ?? null,
            $data['side_effects'] ?? null,
            $data['storage_info'] ?? null,
            $data['image'] ?? null,
            isset($data['is_active']) ? (int)$data['is_active'] : 1,
            isset($data['is_featured']) ? (int)$data['is_featured'] : 0,
            $data['notes'] ?? null,
            $data['created_by'] ?? null,
        ]);

        $id = (int)$this->db->lastInsertId();
        $this->cache?->bumpVersion('catalog');
        return $id;
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['schedule'])) {
            $data['requires_rx'] = $data['schedule'] !== 'OTC' ? 1 : 0;
        }

        $fields = [];
        $params = [];

        foreach ($data as $col => $val) {
            if ($col === 'id') continue;
            $fields[] = "`{$col}` = ?";
            $params[] = $val;
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = '`updated_at` = CURRENT_TIMESTAMP';
        $params[] = $id;

        $sql = 'UPDATE medicines SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
        $updated = $this->db->execute($sql, $params) > 0;
        if ($updated) {
            $this->cache?->bumpVersion('catalog');
        }
        return $updated;
    }

    public function delete(int $id): bool
    {
        $deleted = $this->db->execute(
            'UPDATE medicines SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$id]
        ) > 0;
        if ($deleted) {
            $this->cache?->bumpVersion('catalog');
        }
        return $deleted;
    }

    /**
     * Query helper: Search medicines by name, generic name, or composition salt.
     */
    public function search(string $q, int $limit = 20, int $offset = 0): array
    {
        $term = '%' . trim($q) . '%';
        $sql = 'SELECT DISTINCT m.id, m.name, m.slug, m.generic_name, m.category_id,
                       m.schedule, m.requires_rx, m.pack_size, m.image, c.name as category_name
                FROM medicines m
                LEFT JOIN categories c ON m.category_id = c.id
                LEFT JOIN medicine_salt ms ON m.id = ms.medicine_id
                LEFT JOIN salts s ON ms.salt_id = s.id
                WHERE m.deleted_at IS NULL AND m.is_active = 1
                  AND (m.name LIKE ? OR m.generic_name LIKE ? OR s.name LIKE ?)
                ORDER BY m.name ASC
                LIMIT ? OFFSET ?';

        return $this->db->fetchAll($sql, [$term, $term, $term, $limit, $offset]);
    }

    /**
     * Query helper: In-stock medicines with aggregate active non-expired stock.
     */
    public function inStock(int $limit = 20, int $offset = 0): array
    {
        $today = date('Y-m-d');
        $sql = 'SELECT m.id, m.name, m.slug, m.category_id, c.name as category_name,
                       SUM(b.quantity - b.reserved_quantity) as total_stock,
                       MIN(b.selling_price) as starting_price
                FROM medicines m
                JOIN categories c ON m.category_id = c.id
                JOIN batches b ON m.id = b.medicine_id
                WHERE m.deleted_at IS NULL AND m.is_active = 1
                  AND b.exp_date > ? AND (b.quantity - b.reserved_quantity) > 0
                GROUP BY m.id, m.name, m.slug, m.category_id, c.name
                ORDER BY m.name ASC
                LIMIT ? OFFSET ?';

        return $this->db->fetchAll($sql, [$today, $limit, $offset]);
    }

    /**
     * Query helper: Low stock medicines where total available stock is below or equal to threshold.
     */
    public function lowStock(int $threshold = 10, int $limit = 20, int $offset = 0): array
    {
        $today = date('Y-m-d');
        $sql = 'SELECT m.id, m.name, m.slug, c.name as category_name,
                       COALESCE(SUM(CASE WHEN b.exp_date > ? THEN (b.quantity - b.reserved_quantity) ELSE 0 END), 0) as available_stock
                FROM medicines m
                LEFT JOIN categories c ON m.category_id = c.id
                LEFT JOIN batches b ON m.id = b.medicine_id
                WHERE m.deleted_at IS NULL AND m.is_active = 1
                GROUP BY m.id, m.name, m.slug, c.name
                HAVING available_stock <= ?
                ORDER BY available_stock ASC, m.name ASC
                LIMIT ? OFFSET ?';

        return $this->db->fetchAll($sql, [$today, $threshold, $limit, $offset]);
    }

    /**
     * Query helper: Batches expiring within the given number of days (e.g. 30 or 90 days).
     */
    public function expiringWithin(int $days = 30, int $limit = 20, int $offset = 0): array
    {
        $today = date('Y-m-d');
        $targetDate = date('Y-m-d', strtotime("+{$days} days"));

        $sql = 'SELECT b.id as batch_id, b.batch_number, b.quantity, b.exp_date, b.mfg_date,
                       b.purchase_price, b.mrp, b.selling_price, r.name as rack_name, sh.name as shelf_name,
                       m.id as medicine_id, m.name as medicine_name, m.slug as medicine_slug, c.name as category_name,
                       DATEDIFF(b.exp_date, ?) as days_left
                FROM batches b
                JOIN medicines m ON b.medicine_id = m.id
                LEFT JOIN categories c ON m.category_id = c.id
                LEFT JOIN racks r ON b.rack_id = r.id
                LEFT JOIN shelves sh ON b.shelf_id = sh.id
                WHERE m.deleted_at IS NULL
                  AND b.exp_date >= ? AND b.exp_date <= ?
                  AND b.quantity > 0 AND b.disposal_status = \'none\'
                ORDER BY b.exp_date ASC
                LIMIT ? OFFSET ?';

        return $this->db->fetchAll($sql, [$today, $today, $targetDate, $limit, $offset]);
    }

    /**
     * Query helper: Expired batches.
     */
    public function expired(int $limit = 20, int $offset = 0): array
    {
        $today = date('Y-m-d');

        $sql = 'SELECT b.id as batch_id, b.batch_number, b.quantity, b.exp_date, b.mfg_date,
                       b.purchase_price, b.mrp, b.disposal_status, r.name as rack_name, sh.name as shelf_name,
                       m.id as medicine_id, m.name as medicine_name, m.slug as medicine_slug, c.name as category_name
                FROM batches b
                JOIN medicines m ON b.medicine_id = m.id
                LEFT JOIN categories c ON m.category_id = c.id
                LEFT JOIN racks r ON b.rack_id = r.id
                LEFT JOIN shelves sh ON b.shelf_id = sh.id
                WHERE m.deleted_at IS NULL
                  AND b.exp_date < ?
                ORDER BY b.exp_date DESC
                LIMIT ? OFFSET ?';

        return $this->db->fetchAll($sql, [$today, $limit, $offset]);
    }

    /**
     * Admin server-side search, multi-field filtering, whitelisted sort, and pagination.
     */
    public function paginateAdmin(
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $sort = 'name',
        string $dir = 'asc'
    ): array {
        $today = date('Y-m-d');
        $where = ['m.deleted_at IS NULL'];
        $params = [];

        // 1. Search (name, generic_name, manufacturer, batch_number, rack)
        $q = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($q !== '') {
            $term = '%' . $q . '%';
            $where[] = '(
                m.name LIKE ?
                OR m.generic_name LIKE ?
                OR mf.name LIKE ?
                OR EXISTS (
                    SELECT 1 FROM batches b_srch
                    WHERE b_srch.medicine_id = m.id AND b_srch.batch_number LIKE ?
                )
                OR EXISTS (
                    SELECT 1 FROM batches b_rck
                    JOIN racks r_srch ON b_rck.rack_id = r_srch.id
                    WHERE b_rck.medicine_id = m.id AND r_srch.name LIKE ?
                )
            )';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        // 2. Category Filter
        if (!empty($filters['category_id'])) {
            $where[] = 'm.category_id = ?';
            $params[] = (int)$filters['category_id'];
        } elseif (!empty($filters['category'])) {
            $where[] = '(c.slug = ? OR c.name = ?)';
            $params[] = (string)$filters['category'];
            $params[] = (string)$filters['category'];
        }

        // 3. Manufacturer Filter
        if (!empty($filters['manufacturer_id'])) {
            $where[] = 'm.manufacturer_id = ?';
            $params[] = (int)$filters['manufacturer_id'];
        }

        // 4. Schedule Filter
        if (!empty($filters['schedule'])) {
            $where[] = 'm.schedule = ?';
            $params[] = strtoupper(trim((string)$filters['schedule']));
        }

        // 5. Requires Rx Filter
        if (isset($filters['requires_rx']) && $filters['requires_rx'] !== '') {
            $where[] = 'm.requires_rx = ?';
            $params[] = (int)(bool)$filters['requires_rx'];
        }

        // 6. Active Filter
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'm.is_active = ?';
            $params[] = (int)(bool)$filters['is_active'];
        }

        // 7. In Stock Filter
        if (!empty($filters['in_stock'])) {
            $where[] = 'EXISTS (
                SELECT 1 FROM batches b_stk
                WHERE b_stk.medicine_id = m.id
                  AND b_stk.exp_date > ?
                  AND (b_stk.quantity - b_stk.reserved_quantity) > 0
                  AND b_stk.disposal_status = \'none\'
            )';
            $params[] = $today;
        }

        // 8. Rack Filter
        if (!empty($filters['rack_id'])) {
            $where[] = 'EXISTS (
                SELECT 1 FROM batches b_rk
                WHERE b_rk.medicine_id = m.id AND b_rk.rack_id = ?
            )';
            $params[] = (int)$filters['rack_id'];
        }

        $whereSql = implode(' AND ', $where);

        // Count Total
        $countSql = "
            SELECT COUNT(DISTINCT m.id) as total
            FROM medicines m
            LEFT JOIN categories c ON m.category_id = c.id
            LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
            WHERE {$whereSql}
        ";
        $totalRow = $this->db->fetchOne($countSql, $params);
        $total = (int)($totalRow['total'] ?? 0);

        // Sorting
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $orderBy = match (strtolower(trim($sort))) {
            'stock' => "ORDER BY total_stock {$dir}, m.id {$dir}",
            'price' => "ORDER BY selling_price {$dir}, m.id {$dir}",
            'created_at' => "ORDER BY m.created_at {$dir}, m.id {$dir}",
            'schedule' => "ORDER BY m.schedule {$dir}, m.id {$dir}",
            default => "ORDER BY m.name {$dir}, m.id {$dir}",
        };

        $offset = max(0, ($page - 1) * $perPage);

        $sql = "
            SELECT
                m.*,
                c.name as category_name,
                c.slug as category_slug,
                mf.name as manufacturer_name,
                (
                    SELECT b_prc.selling_price FROM batches b_prc
                    WHERE b_prc.medicine_id = m.id
                      AND b_prc.exp_date > '{$today}'
                      AND (b_prc.quantity - b_prc.reserved_quantity) > 0
                      AND b_prc.disposal_status = 'none'
                    ORDER BY b_prc.exp_date ASC, b_prc.id ASC
                    LIMIT 1
                ) as selling_price,
                (
                    SELECT b_prc.mrp FROM batches b_prc
                    WHERE b_prc.medicine_id = m.id
                      AND b_prc.exp_date > '{$today}'
                      AND (b_prc.quantity - b_prc.reserved_quantity) > 0
                      AND b_prc.disposal_status = 'none'
                    ORDER BY b_prc.exp_date ASC, b_prc.id ASC
                    LIMIT 1
                ) as mrp,
                COALESCE(
                    (
                        SELECT SUM(b_stk.quantity - b_stk.reserved_quantity) FROM batches b_stk
                        WHERE b_stk.medicine_id = m.id
                          AND b_stk.exp_date > '{$today}'
                          AND b_stk.disposal_status = 'none'
                    ),
                    0
                ) as total_stock,
                (
                    SELECT COUNT(*) FROM batches b_cnt
                    WHERE b_cnt.medicine_id = m.id
                ) as batch_count
            FROM medicines m
            LEFT JOIN categories c ON m.category_id = c.id
            LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
            WHERE {$whereSql}
            {$orderBy}
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $items = $this->db->fetchAll($sql, $params);

        return [
            'total' => $total,
            'items' => $items,
        ];
    }

    /**
     * Sync composition salts for a medicine.
     */
    public function syncSalts(int $medicineId, array $salts): void
    {
        $this->db->execute('DELETE FROM medicine_salt WHERE medicine_id = ?', [$medicineId]);

        $sql = 'INSERT INTO medicine_salt (medicine_id, salt_id, strength) VALUES (?, ?, ?)';
        foreach ($salts as $salt) {
            $saltId = (int)($salt['salt_id'] ?? $salt['id'] ?? 0);
            $strength = isset($salt['strength']) ? trim((string)$salt['strength']) : null;
            if ($saltId > 0) {
                $this->db->execute($sql, [$medicineId, $saltId, $strength]);
            }
        }

        $this->cache?->bumpVersion('catalog');
    }
}

