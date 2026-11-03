<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class UserRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public function findByAuthgearId(string $authgearUserId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE authgear_user_id = ? AND deleted_at IS NULL', [$authgearUserId]);
    }

    public function findByPhone(string $phone): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE phone = ? AND deleted_at IS NULL', [$phone]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
    }

    public function findByIdentifier(string $identifier): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM users WHERE (phone = ? OR email = ?) AND deleted_at IS NULL',
            [$identifier, $identifier]
        );
    }

    public function findByRole(string $role): array
    {
        $sql = 'SELECT * FROM users WHERE role = ? AND is_blocked = 0 AND deleted_at IS NULL';
        return $this->db->fetchAll($sql, [$role]);
    }

    public function findByRoles(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql = "SELECT * FROM users WHERE role IN ({$placeholders}) AND is_blocked = 0 AND deleted_at IS NULL";
        return $this->db->fetchAll($sql, array_values($roles));
    }

    public function create(array $data): int
    {
        $fields = ['name', 'phone', 'email', 'role', 'locale', 'is_blocked', 'pharmacist_reg_no', 'phone_verified_at', 'email_verified_at'];
        $values = [
            $data['name'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['role'] ?? 'customer',
            $data['locale'] ?? 'en',
            isset($data['is_blocked']) ? (int)$data['is_blocked'] : 0,
            $data['pharmacist_reg_no'] ?? null,
            $data['phone_verified_at'] ?? null,
            $data['email_verified_at'] ?? null,
        ];

        if (array_key_exists('authgear_user_id', $data) && $data['authgear_user_id'] !== null) {
            array_unshift($fields, 'authgear_user_id');
            array_unshift($values, $data['authgear_user_id']);
        }

        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $fieldNames = implode(', ', $fields);
        $sql = "INSERT INTO users ({$fieldNames}, created_at, updated_at) VALUES ({$placeholders}, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

        $this->db->execute($sql, $values);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
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

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        return $this->db->execute($sql, $params) > 0;
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->execute('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
    }

    public function block(int $id, bool $blocked = true): bool
    {
        return $this->db->execute('UPDATE users SET is_blocked = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [(int)$blocked, $id]) > 0;
    }

    public function setBlocked(int $id, bool $blocked = true): bool
    {
        $res = $this->block($id, $blocked);
        if ($blocked) {
            $this->revokeAllRefreshTokens($id);
        }
        return $res;
    }

    public function revokeAllRefreshTokens(int $userId): bool
    {
        try {
            $this->db->execute(
                'UPDATE refresh_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND revoked_at IS NULL',
                [$userId]
            );
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function paginate(int $page = 1, int $perPage = 20, ?string $q = null): array
    {
        return $this->paginateCustomers(['q' => $q], $page, $perPage);
    }

    /**
     * Paginate customers with search, lifetime value, and order counts.
     */
    public function paginateCustomers(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ['u.deleted_at IS NULL'];
        $params = [];

        if (isset($filters['role']) && !empty($filters['role'])) {
            $where[] = 'u.role = ?';
            $params[] = $filters['role'];
        } else {
            $where[] = "u.role = 'customer'";
        }

        if (!empty($filters['search']) || !empty($filters['q'])) {
            $search = '%' . trim((string)($filters['search'] ?? $filters['q'])) . '%';
            $where[] = '(u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (isset($filters['is_blocked']) && $filters['is_blocked'] !== '') {
            $where[] = 'u.is_blocked = ?';
            $params[] = (int)$filters['is_blocked'];
        }

        $whereClause = implode(' AND ', $where);

        $countRow = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM users u WHERE {$whereClause}", $params);
        $total = (int)($countRow['cnt'] ?? 0);

        $sql = "SELECT u.id, u.name, u.phone, u.email, u.role, u.locale, u.is_blocked, u.pharmacist_reg_no, u.last_login_at, u.created_at,
                       (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as orders_count,
                       (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.user_id = u.id AND o.status != 'cancelled') as lifetime_value
                FROM users u
                WHERE {$whereClause}
                ORDER BY u.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);

        // Convert numeric strings to floats/ints for clean JSON
        foreach ($items as &$item) {
            $item['orders_count'] = (int)($item['orders_count'] ?? 0);
            $item['lifetime_value'] = (float)($item['lifetime_value'] ?? 0.0);
            $item['is_blocked'] = (int)($item['is_blocked'] ?? 0);
        }

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Get detailed customer profile with recent orders, addresses, and statistics.
     */
    public function getCustomerDetails(int $userId): ?array
    {
        $user = $this->findById($userId);
        if (!$user) {
            return null;
        }

        // Addresses
        $addresses = [];
        try {
            $addresses = $this->db->fetchAll(
                'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC',
                [$userId]
            );
        } catch (\Throwable) {
        }

        // Recent orders
        $orders = [];
        try {
            $orders = $this->db->fetchAll(
                'SELECT id, order_no, status, total, payment_method, payment_status, created_at
                 FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 10',
                [$userId]
            );
        } catch (\Throwable) {
        }

        // Aggregate statistics
        $ordersCount = 0;
        $lifetimeValue = 0.0;
        try {
            $stats = $this->db->fetchOne(
                "SELECT COUNT(*) as orders_count,
                        COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END), 0) as lifetime_value
                 FROM orders WHERE user_id = ?",
                [$userId]
            );
            if ($stats) {
                $ordersCount = (int)$stats['orders_count'];
                $lifetimeValue = (float)$stats['lifetime_value'];
            }
        } catch (\Throwable) {
        }

        // Prescriptions count
        $prescriptionsCount = 0;
        try {
            $rxRow = $this->db->fetchOne(
                'SELECT COUNT(*) as cnt FROM prescriptions WHERE user_id = ?',
                [$userId]
            );
            if ($rxRow) {
                $prescriptionsCount = (int)$rxRow['cnt'];
            }
        } catch (\Throwable) {
        }

        return [
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'phone' => $user['phone'],
                'email' => $user['email'],
                'role' => $user['role'],
                'locale' => $user['locale'],
                'is_blocked' => (int)$user['is_blocked'],
                'phone_verified_at' => $user['phone_verified_at'],
                'email_verified_at' => $user['email_verified_at'],
                'pharmacist_reg_no' => $user['pharmacist_reg_no'],
                'last_login_at' => $user['last_login_at'],
                'created_at' => $user['created_at'],
            ],
            'statistics' => [
                'orders_count' => $ordersCount,
                'lifetime_value' => $lifetimeValue,
                'prescriptions_count' => $prescriptionsCount,
            ],
            'addresses' => $addresses,
            'recent_orders' => $orders,
        ];
    }

    /**
     * Export full customer data under DPDP Act 2023.
     */
    public function exportCustomerData(int $userId): ?array
    {
        $user = $this->findById($userId);
        if (!$user) {
            return null;
        }

        $addresses = [];
        try {
            $addresses = $this->db->fetchAll('SELECT * FROM addresses WHERE user_id = ?', [$userId]);
        } catch (\Throwable) {}

        $orders = [];
        try {
            $orders = $this->db->fetchAll('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC', [$userId]);
            foreach ($orders as &$ord) {
                if (isset($ord['address_snapshot']) && is_string($ord['address_snapshot'])) {
                    $ord['address_snapshot'] = json_decode($ord['address_snapshot'], true);
                }
                $ord['items'] = $this->db->fetchAll(
                    'SELECT oi.*, m.name as medicine_name FROM order_items oi JOIN medicines m ON oi.medicine_id = m.id WHERE oi.order_id = ?',
                    [$ord['id']]
                );
            }
        } catch (\Throwable) {}

        $prescriptions = [];
        try {
            $prescriptions = $this->db->fetchAll('SELECT * FROM prescriptions WHERE user_id = ? ORDER BY id DESC', [$userId]);
            foreach ($prescriptions as &$rx) {
                $rx['files'] = $this->db->fetchAll('SELECT id, original_name, mime_type, file_size, created_at FROM prescription_files WHERE prescription_id = ?', [$rx['id']]);
            }
        } catch (\Throwable) {}

        $refills = [];
        try {
            $refills = $this->db->fetchAll('SELECT * FROM refill_reminders WHERE user_id = ?', [$userId]);
        } catch (\Throwable) {}

        $deleteRequests = [];
        try {
            $deleteRequests = $this->db->fetchAll('SELECT * FROM account_deletion_requests WHERE user_id = ?', [$userId]);
        } catch (\Throwable) {}

        return [
            'dpdp_compliance' => 'Digital Personal Data Protection (DPDP) Act, 2023 Data Export',
            'exported_at' => date('Y-m-d H:i:s'),
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'phone' => $user['phone'],
                'email' => $user['email'],
                'role' => $user['role'],
                'locale' => $user['locale'],
                'is_blocked' => (int)$user['is_blocked'],
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at'],
            ],
            'addresses' => $addresses,
            'orders' => $orders,
            'prescriptions' => $prescriptions,
            'refill_reminders' => $refills,
            'account_deletion_requests' => $deleteRequests,
        ];
    }

    /**
     * Record an account deletion request under DPDP Act 2023.
     */
    public function createDeleteRequest(int $userId, ?string $reason = null): int
    {
        $this->db->execute(
            'INSERT INTO account_deletion_requests (user_id, reason, status, requested_at)
             VALUES (?, ?, \'pending\', CURRENT_TIMESTAMP)',
            [$userId, $reason]
        );
        return (int)$this->db->lastInsertId();
    }

    /**
     * Get account deletion requests for admin review.
     */
    public function getDeleteRequests(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $countRow = $this->db->fetchOne('SELECT COUNT(*) as cnt FROM account_deletion_requests');
        $total = (int)($countRow['cnt'] ?? 0);

        $items = $this->db->fetchAll(
            'SELECT r.*, u.name as user_name, u.phone as user_phone, u.email as user_email
             FROM account_deletion_requests r
             JOIN users u ON r.user_id = u.id
             ORDER BY r.id DESC LIMIT ? OFFSET ?',
            [$perPage, $offset]
        );

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }
}

