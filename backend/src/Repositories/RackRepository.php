<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class RackRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM racks ORDER BY name ASC');
    }

    public function allWithCounts(): array
    {
        $sql = 'SELECT r.*,
                       (SELECT COUNT(*) FROM shelves s WHERE s.rack_id = r.id) as shelf_count,
                       (SELECT COUNT(DISTINCT b.medicine_id) FROM batches b WHERE b.rack_id = r.id) as medicine_count,
                       (SELECT COALESCE(SUM(b.quantity - b.reserved_quantity), 0) FROM batches b WHERE b.rack_id = r.id) as total_units
                FROM racks r
                ORDER BY r.name ASC';

        return $this->db->fetchAll($sql);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM racks WHERE id = ?', [$id]);
    }

    public function findByName(string $name): ?array
    {
        return $this->db->fetchOne('SELECT * FROM racks WHERE name = ?', [trim($name)]);
    }

    public function create(string $name): int
    {
        $this->db->execute('INSERT INTO racks (name) VALUES (?)', [trim($name)]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name): bool
    {
        return $this->db->execute('UPDATE racks SET name = ? WHERE id = ?', [trim($name), $id]) > 0;
    }

    public function delete(int $id): bool
    {
        // Detach rack from batches first if any, or restrict
        $this->db->execute('UPDATE batches SET rack_id = NULL, shelf_id = NULL WHERE rack_id = ?', [$id]);
        $this->db->execute('DELETE FROM shelves WHERE rack_id = ?', [$id]);
        return $this->db->execute('DELETE FROM racks WHERE id = ?', [$id]) > 0;
    }

    public function getMedicinesInRack(int $rackId, int $limit = 20, int $offset = 0): array
    {
        $today = date('Y-m-d');
        $countSql = "SELECT COUNT(DISTINCT m.id) as total
                     FROM medicines m
                     JOIN batches b ON m.id = b.medicine_id
                     WHERE b.rack_id = ? AND m.deleted_at IS NULL";
        $totalRow = $this->db->fetchOne($countSql, [$rackId]);
        $total = (int)($totalRow['total'] ?? 0);

        $sql = "SELECT DISTINCT m.id, m.name, m.slug, m.generic_name, c.name as category_name,
                                b.batch_number, b.quantity, b.exp_date, sh.name as shelf_name
                FROM medicines m
                JOIN batches b ON m.id = b.medicine_id
                LEFT JOIN categories c ON m.category_id = c.id
                LEFT JOIN shelves sh ON b.shelf_id = sh.id
                WHERE b.rack_id = ? AND m.deleted_at IS NULL
                ORDER BY m.name ASC, b.exp_date ASC
                LIMIT ? OFFSET ?";

        $items = $this->db->fetchAll($sql, [$rackId, $limit, $offset]);

        return ['total' => $total, 'items' => $items];
    }
}

