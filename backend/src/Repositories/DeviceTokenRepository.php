<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class DeviceTokenRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function save(int $userId, string $fcmToken, string $platform = 'android'): bool
    {
        $platform = in_array($platform, ['android', 'ios', 'web'], true) ? $platform : 'android';

        // Check if token already exists
        $existing = $this->db->fetchOne('SELECT id, user_id FROM device_tokens WHERE fcm_token = ?', [$fcmToken]);
        if ($existing) {
            $sql = 'UPDATE device_tokens SET user_id = ?, platform = ?, last_seen_at = CURRENT_TIMESTAMP WHERE id = ?';
            $this->db->execute($sql, [$userId, $platform, $existing['id']]);
            return true;
        }

        $sql = 'INSERT INTO device_tokens (user_id, fcm_token, platform, last_seen_at, created_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)';
        $this->db->execute($sql, [$userId, $fcmToken, $platform]);
        return true;
    }

    public function deleteToken(string $fcmToken, ?int $userId = null): bool
    {
        if ($userId !== null) {
            $sql = 'DELETE FROM device_tokens WHERE fcm_token = ? AND user_id = ?';
            return $this->db->execute($sql, [$fcmToken, $userId]) > 0;
        }

        $sql = 'DELETE FROM device_tokens WHERE fcm_token = ?';
        return $this->db->execute($sql, [$fcmToken]) > 0;
    }

    public function deleteTokens(array $tokens): int
    {
        if (empty($tokens)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($tokens), '?'));
        $sql = "DELETE FROM device_tokens WHERE fcm_token IN ({$placeholders})";
        return $this->db->execute($sql, array_values($tokens));
    }

    public function getTokensByUserId(int $userId): array
    {
        try {
            $sql = 'SELECT fcm_token, platform FROM device_tokens WHERE user_id = ?';
            $rows = $this->db->fetchAll($sql, [$userId]);
            return array_column($rows, 'fcm_token');
        } catch (\Throwable) {
            return [];
        }
    }

    public function getTokensByUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $sql = "SELECT fcm_token FROM device_tokens WHERE user_id IN ({$placeholders})";
            $rows = $this->db->fetchAll($sql, array_values($userIds));
            return array_column($rows, 'fcm_token');
        } catch (\Throwable) {
            return [];
        }
    }

    public function getTokensByRoles(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($roles), '?'));
            $sql = "SELECT dt.fcm_token 
                    FROM device_tokens dt
                    INNER JOIN users u ON u.id = dt.user_id
                    WHERE u.role IN ({$placeholders}) AND u.is_blocked = 0";
            $rows = $this->db->fetchAll($sql, array_values($roles));
            return array_column($rows, 'fcm_token');
        } catch (\Throwable) {
            return [];
        }
    }

    public function touchLastSeen(string $fcmToken): void
    {
        $this->db->execute('UPDATE device_tokens SET last_seen_at = CURRENT_TIMESTAMP WHERE fcm_token = ?', [$fcmToken]);
    }

    public function countForUser(int $userId): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) as cnt FROM device_tokens WHERE user_id = ?', [$userId]);
        return (int)($row['cnt'] ?? 0);
    }
}
