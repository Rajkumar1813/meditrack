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

    public function findById(int $id): ?array
    {
        $sql = 'SELECT * FROM notifications WHERE id = ?';
        $row = $this->db->fetchOne($sql, [$id]);
        if ($row && isset($row['data']) && is_string($row['data'])) {
            $row['data'] = json_decode($row['data'], true);
        }
        return $row ?: null;
    }

    public function paginateCursor(int $userId, ?int $cursor = null, int $limit = 20, bool $unreadOnly = false): array
    {
        $limit = max(1, min(50, $limit));

        $conditions = ['user_id = ?'];
        $params = [$userId];

        if ($unreadOnly) {
            $conditions[] = 'read_at IS NULL';
        }

        if ($cursor !== null && $cursor > 0) {
            $conditions[] = 'id < ?';
            $params[] = $cursor;
        }

        $where = implode(' AND ', $conditions);
        // Query +1 to check if there is a next page
        $fetchLimit = $limit + 1;
        $params[] = $fetchLimit;

        $sql = "SELECT id, user_id, type, title, body, data, read_at, created_at 
                FROM notifications 
                WHERE {$where} 
                ORDER BY id DESC 
                LIMIT ?";

        $rows = $this->db->fetchAll($sql, $params);

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        foreach ($rows as &$row) {
            if (isset($row['data']) && is_string($row['data'])) {
                $row['data'] = json_decode($row['data'], true);
            }
        }
        unset($row);

        $nextCursor = null;
        if (!empty($rows)) {
            $lastItem = end($rows);
            $nextCursor = $hasMore ? (int)$lastItem['id'] : null;
        }

        return [
            'data' => $rows,
            'meta' => [
                'cursor' => [
                    'next_cursor' => $nextCursor,
                    'has_more' => $hasMore,
                ],
                'unread_count' => $this->getUnreadCount($userId),
            ],
        ];
    }

    public function paginateForUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ? OFFSET ?';
        $rows = $this->db->fetchAll($sql, [$userId, $limit, $offset]);
        foreach ($rows as &$row) {
            if (isset($row['data']) && is_string($row['data'])) {
                $row['data'] = json_decode($row['data'], true);
            }
        }
        return $rows;
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

    public function markAllAsRead(int $userId): int
    {
        $sql = 'UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND read_at IS NULL';
        return $this->db->execute($sql, [$userId]);
    }

    public function getUnreadCount(int $userId): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
        return (int)($row['cnt'] ?? 0);
    }
}
