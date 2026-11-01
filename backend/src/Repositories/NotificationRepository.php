<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class NotificationRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function create(int $userId, string $type, string $title, string $body, ?array $data = null): int
    {
        $jsonData = $data ? json_encode($data) : null;
        $sql = 'INSERT INTO notifications (user_id, type, title, body, data, created_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)';
        $this->db->execute($sql, [$userId, $type, $title, $body, $jsonData]);
        return (int)$this->db->lastInsertId();
    }

    public function paginateForUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ? OFFSET ?';
        return $this->db->fetchAll($sql, [$userId, $limit, $offset]);
    }

    public function findByUser(int $userId): array
    {
        return $this->paginateForUser($userId, 100);
    }

    public function markAsRead(int $id, int $userId): bool
    {
        $sql = 'UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND read_at IS NULL';
        return $this->db->execute($sql, [$id, $userId]) > 0;
    }

    public function markAllAsRead(int $userId): bool
    {
        $sql = 'UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND read_at IS NULL';
        return $this->db->execute($sql, [$userId]) > 0;
    }

    public function getUnreadCount(int $userId): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
        return (int)($row['cnt'] ?? 0);
    }
}

