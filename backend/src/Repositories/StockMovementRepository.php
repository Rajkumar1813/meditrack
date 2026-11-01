<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class StockMovementRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function log(
        int $batchId,
        string $type,
        int $qtyChange,
        ?string $refType = null,
        ?int $refId = null,
        ?int $userId = null
    ): int {
        $sql = 'INSERT INTO stock_movements (batch_id, type, qty_change, ref_type, ref_id, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)';

        $this->db->execute($sql, [
            $batchId,
            $type,
            $qtyChange,
            $refType,
            $refId,
            $userId,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getByBatch(int $batchId, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT sm.*, u.name as user_name
                FROM stock_movements sm
                LEFT JOIN users u ON sm.user_id = u.id
                WHERE sm.batch_id = ?
                ORDER BY sm.id DESC
                LIMIT ? OFFSET ?';

        return $this->db->fetchAll($sql, [$batchId, $limit, $offset]);
    }

    public function paginate(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['batch_id'])) {
            $where[] = 'sm.batch_id = ?';
            $params[] = (int)$filters['batch_id'];
        }

        if (!empty($filters['type'])) {
            $where[] = 'sm.type = ?';
            $params[] = (string)$filters['type'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'sm.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) as total FROM stock_movements sm WHERE {$whereSql}";
        $totalRow = $this->db->fetchOne($countSql, $params);
        $total = (int)($totalRow['total'] ?? 0);

        $sql = "SELECT sm.*, b.batch_number, m.name as medicine_name, m.slug as medicine_slug, u.name as user_name
                FROM stock_movements sm
                JOIN batches b ON sm.batch_id = b.id
                JOIN medicines m ON b.medicine_id = m.id
                LEFT JOIN users u ON sm.user_id = u.id
                WHERE {$whereSql}
                ORDER BY sm.id DESC
                LIMIT ? OFFSET ?";

        $queryParams = array_merge($params, [$limit, $offset]);
        $rows = $this->db->fetchAll($sql, $queryParams);

        return [
            'total' => $total,
            'items' => $rows,
        ];
    }
}

