<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class SupplierRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM suppliers ORDER BY name ASC');
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM suppliers WHERE id = ?', [$id]);
    }

    public function findByName(string $name): ?array
    {
        return $this->db->fetchOne('SELECT * FROM suppliers WHERE name = ?', [trim($name)]);
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO suppliers (name, phone, email, gstin) VALUES (?, ?, ?, ?)';
        $this->db->execute($sql, [
            $data['name'],
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['gstin'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }
}

