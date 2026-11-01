<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class BatchRepository
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
            'SELECT b.*, r.name as rack_name, s.name as shelf_name, sp.name as supplier_name
             FROM batches b
             LEFT JOIN racks r ON b.rack_id = r.id
             LEFT JOIN shelves s ON b.shelf_id = s.id
             LEFT JOIN suppliers sp ON b.supplier_id = sp.id
             WHERE b.id = ?',
            [$id]
        );
    }

    public function findByMedicineAndBatchNumber(int $medicineId, string $batchNumber): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM batches WHERE medicine_id = ? AND batch_number = ?',
            [$medicineId, strtoupper(trim($batchNumber))]
        );
    }

    /**
     * Get valid active non-expired batches for a medicine ordered strictly by FEFO (earliest expiry first).
     */
    public function findAvailableByMedicine(int $medicineId, bool $lockForUpdate = false): array
    {
        $today = date('Y-m-d');
        $sql = 'SELECT * FROM batches
                WHERE medicine_id = ?
                  AND exp_date > ?
                  AND (quantity - reserved_quantity) > 0
                  AND disposal_status = \'none\'
                ORDER BY exp_date ASC, id ASC';

        if ($lockForUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->db->fetchAll($sql, [$medicineId, $today]);
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO batches (
                    medicine_id, batch_number, quantity, reserved_quantity,
                    purchase_price, mrp, selling_price, mfg_date, exp_date,
                    supplier_id, rack_id, shelf_id, disposal_status, notes,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )';

        $this->db->execute($sql, [
            $data['medicine_id'],
            strtoupper(trim($data['batch_number'])),
            $data['quantity'] ?? 0,
            $data['reserved_quantity'] ?? 0,
            $data['purchase_price'],
            $data['mrp'],
            $data['selling_price'] ?? $data['mrp'],
            $data['mfg_date'],
            $data['exp_date'],
            $data['supplier_id'] ?? null,
            $data['rack_id'] ?? null,
            $data['shelf_id'] ?? null,
            $data['disposal_status'] ?? 'none',
            $data['notes'] ?? null,
        ]);

        $this->cache?->bumpVersion('catalog');

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        foreach ($data as $col => $val) {
            if ($col === 'id') continue;
            if ($col === 'batch_number') {
                $val = strtoupper(trim((string)$val));
            }
            $fields[] = "`{$col}` = ?";
            $params[] = $val;
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = '`updated_at` = CURRENT_TIMESTAMP';
        $params[] = $id;

        $sql = 'UPDATE batches SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $updated = $this->db->execute($sql, $params) > 0;
        if ($updated) {
            $this->cache?->bumpVersion('catalog');
        }
        return $updated;
    }

    public function reserveQuantity(int $id, int $qty): bool
    {
        $sql = 'UPDATE batches SET reserved_quantity = reserved_quantity + ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND (quantity - reserved_quantity) >= ?';
        $updated = $this->db->execute($sql, [$qty, $id, $qty]) > 0;
        if ($updated) {
            $this->cache?->bumpVersion('catalog');
        }
        return $updated;
    }

    public function releaseReservedQuantity(int $id, int $qty): bool
    {
        $sql = 'UPDATE batches SET reserved_quantity = GREATEST(0, reserved_quantity - ?), updated_at = CURRENT_TIMESTAMP
                WHERE id = ?';
        $updated = $this->db->execute($sql, [$qty, $id]) > 0;
        if ($updated) {
            $this->cache?->bumpVersion('catalog');
        }
        return $updated;
    }

    public function deductQuantity(int $id, int $qty): bool
    {
        // Decrement both reserved_quantity and quantity upon fulfillment
        $sql = 'UPDATE batches SET
                    quantity = quantity - ?,
                    reserved_quantity = GREATEST(0, reserved_quantity - ?),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND quantity >= ?';
        $updated = $this->db->execute($sql, [$qty, $qty, $id, $qty]) > 0;
        if ($updated) {
            $this->cache?->bumpVersion('catalog');
        }
        return $updated;
    }

    public function getBatchesForMedicine(int $medicineId): array
    {
        return $this->db->fetchAll(
            'SELECT b.*, r.name as rack_name, s.name as shelf_name, sp.name as supplier_name
             FROM batches b
             LEFT JOIN racks r ON b.rack_id = r.id
             LEFT JOIN shelves s ON b.shelf_id = s.id
             LEFT JOIN suppliers sp ON b.supplier_id = sp.id
             WHERE b.medicine_id = ?
             ORDER BY b.exp_date ASC',
            [$medicineId]
        );
    }

    public function dispose(int $id, string $status, ?string $notes = null): bool
    {
        $status = in_array($status, ['returned', 'disposed'], true) ? $status : 'disposed';
        $sql = 'UPDATE batches SET disposal_status = ?, notes = COALESCE(?, notes), updated_at = CURRENT_TIMESTAMP WHERE id = ?';
        $updated = $this->db->execute($sql, [$status, $notes, $id]) > 0;
        if ($updated) {
            $this->cache?->bumpVersion('catalog');
        }
        return $updated;
    }

    public function delete(int $id): bool
    {
        $deleted = $this->db->execute('DELETE FROM batches WHERE id = ?', [$id]) > 0;
        if ($deleted) {
            $this->cache?->bumpVersion('catalog');
        }
        return $deleted;
    }

    public function getExpiring(int $days = 30, int $limit = 20, int $offset = 0): array
    {
        $today = date('Y-m-d');
        $targetDate = date('Y-m-d', strtotime("+{$days} days"));

        $countSql = "SELECT COUNT(*) as total FROM batches b
                     JOIN medicines m ON b.medicine_id = m.id
                     WHERE m.deleted_at IS NULL AND b.exp_date >= ? AND b.exp_date <= ?
                       AND b.disposal_status = 'none'";
        $totalRow = $this->db->fetchOne($countSql, [$today, $targetDate]);
        $total = (int)($totalRow['total'] ?? 0);

        $sql = "SELECT b.*, m.name as medicine_name, m.slug as medicine_slug,
                       r.name as rack_name, s.name as shelf_name, sp.name as supplier_name,
                       DATEDIFF(b.exp_date, ?) as days_left
                FROM batches b
                JOIN medicines m ON b.medicine_id = m.id
                LEFT JOIN racks r ON b.rack_id = r.id
                LEFT JOIN shelves s ON b.shelf_id = s.id
                LEFT JOIN suppliers sp ON b.supplier_id = sp.id
                WHERE m.deleted_at IS NULL AND b.exp_date >= ? AND b.exp_date <= ?
                  AND b.disposal_status = 'none'
                ORDER BY b.exp_date ASC, b.id ASC
                LIMIT ? OFFSET ?";

        $items = $this->db->fetchAll($sql, [$today, $today, $targetDate, $limit, $offset]);

        return ['total' => $total, 'items' => $items];
    }

    public function getExpired(int $limit = 20, int $offset = 0): array
    {
        $today = date('Y-m-d');

        $countSql = "SELECT COUNT(*) as total FROM batches b
                     JOIN medicines m ON b.medicine_id = m.id
                     WHERE m.deleted_at IS NULL AND b.exp_date < ?";
        $totalRow = $this->db->fetchOne($countSql, [$today]);
        $total = (int)($totalRow['total'] ?? 0);

        $sql = "SELECT b.*, m.name as medicine_name, m.slug as medicine_slug,
                       r.name as rack_name, s.name as shelf_name, sp.name as supplier_name,
                       DATEDIFF(?, b.exp_date) as days_expired
                FROM batches b
                JOIN medicines m ON b.medicine_id = m.id
                LEFT JOIN racks r ON b.rack_id = r.id
                LEFT JOIN shelves s ON b.shelf_id = s.id
                LEFT JOIN suppliers sp ON b.supplier_id = sp.id
                WHERE m.deleted_at IS NULL AND b.exp_date < ?
                ORDER BY b.exp_date DESC, b.id DESC
                LIMIT ? OFFSET ?";

        $items = $this->db->fetchAll($sql, [$today, $today, $limit, $offset]);

        return ['total' => $total, 'items' => $items];
    }

    public function expiringWithin(int $days = 30): array
    {
        $today = date('Y-m-d');
        $targetDate = date('Y-m-d', strtotime("+{$days} days"));

        $sql = "SELECT b.*, m.name as medicine_name, m.slug as medicine_slug
                FROM batches b
                JOIN medicines m ON b.medicine_id = m.id
                WHERE m.deleted_at IS NULL AND b.exp_date >= ? AND b.exp_date <= ?
                  AND b.disposal_status = 'none'
                ORDER BY b.exp_date ASC, b.id ASC";

        return $this->db->fetchAll($sql, [$today, $targetDate]);
    }

    public function expired(): array
    {
        $today = date('Y-m-d');

        $sql = "SELECT b.*, m.name as medicine_name, m.slug as medicine_slug
                FROM batches b
                JOIN medicines m ON b.medicine_id = m.id
                WHERE m.deleted_at IS NULL AND b.exp_date < ?
                ORDER BY b.exp_date DESC, b.id DESC";

        return $this->db->fetchAll($sql, [$today]);
    }
}

