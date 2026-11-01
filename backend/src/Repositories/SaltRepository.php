<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class SaltRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findByName(string $name): ?array
    {
        return $this->db->fetchOne('SELECT * FROM salts WHERE name = ?', [trim($name)]);
    }

    public function findOrCreate(string $name): int
    {
        $clean = trim($name);
        $existing = $this->findByName($clean);
        if ($existing) {
            return (int)$existing['id'];
        }

        $this->db->execute('INSERT INTO salts (name) VALUES (?)', [$clean]);
        return (int)$this->db->lastInsertId();
    }

    public function attachToMedicine(int $medicineId, int $saltId, ?string $strength = null): void
    {
        if ($this->db->driver() === 'sqlite') {
            $sql = 'INSERT INTO medicine_salt (medicine_id, salt_id, strength) VALUES (?, ?, ?)
                    ON CONFLICT(medicine_id, salt_id) DO UPDATE SET strength = excluded.strength';
        } else {
            $sql = 'INSERT INTO medicine_salt (medicine_id, salt_id, strength) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE strength = VALUES(strength)';
        }
        $this->db->execute($sql, [$medicineId, $saltId, $strength]);
    }

    public function getByMedicineId(int $medicineId): array
    {
        $sql = 'SELECT s.id, s.name, ms.strength
                FROM salts s
                JOIN medicine_salt ms ON s.id = ms.salt_id
                WHERE ms.medicine_id = ?
                ORDER BY s.name ASC';
        return $this->db->fetchAll($sql, [$medicineId]);
    }

    public function search(string $query, int $limit = 8): array
    {
        $term = trim($query) . '%';
        $sql = 'SELECT id, name FROM salts WHERE name LIKE ? ORDER BY name ASC LIMIT ?';
        return $this->db->fetchAll($sql, [$term, $limit]);
    }
}
