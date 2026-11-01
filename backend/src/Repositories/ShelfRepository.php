<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class ShelfRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT s.*, r.name as rack_name FROM shelves s JOIN racks r ON s.rack_id = r.id ORDER BY r.name ASC, s.name ASC');
    }

    public function getByRackId(int $rackId): array
    {
        return $this->db->fetchAll('SELECT * FROM shelves WHERE rack_id = ? ORDER BY name ASC', [$rackId]);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT s.*, r.name as rack_name FROM shelves s JOIN racks r ON s.rack_id = r.id WHERE s.id = ?', [$id]);
    }

    public function findByRackAndName(int $rackId, string $name): ?array
    {
        return $this->db->fetchOne('SELECT * FROM shelves WHERE rack_id = ? AND name = ?', [$rackId, trim($name)]);
    }

    public function create(int $rackId, string $name): int
    {
        $this->db->execute('INSERT INTO shelves (rack_id, name) VALUES (?, ?)', [$rackId, trim($name)]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, ?int $rackId = null): bool
    {
        if ($rackId !== null) {
            return $this->db->execute('UPDATE shelves SET name = ?, rack_id = ? WHERE id = ?', [trim($name), $rackId, $id]) > 0;
        }
        return $this->db->execute('UPDATE shelves SET name = ? WHERE id = ?', [trim($name), $id]) > 0;
    }

    public function delete(int $id): bool
    {
        $this->db->execute('UPDATE batches SET shelf_id = NULL WHERE shelf_id = ?', [$id]);
        return $this->db->execute('DELETE FROM shelves WHERE id = ?', [$id]) > 0;
    }
}

