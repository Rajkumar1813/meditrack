<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class CategoryRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM categories ' . ($onlyActive ? 'WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC, name ASC';
        return $this->db->fetchAll($sql);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetchOne('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO categories (name, slug, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?)';
        $this->db->execute($sql, [
            $data['name'],
            $data['slug'],
            $data['icon'] ?? null,
            $data['sort_order'] ?? 0,
            isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ]);
        return (int)$this->db->lastInsertId();
    }
}

