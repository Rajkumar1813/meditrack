<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class AuditLogRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function log(
        string $action,
        string $entity,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?string $ip = null
    ): int {
        $sql = 'INSERT INTO audit_logs (user_id, action, entity, entity_id, old_values, new_values, ip, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)';

        $this->db->execute($sql, [
            $userId,
            $action,
            $entity,
            $entityId,
            $oldValues !== null ? json_encode($oldValues, JSON_THROW_ON_ERROR) : null,
            $newValues !== null ? json_encode($newValues, JSON_THROW_ON_ERROR) : null,
            $ip,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getByEntity(string $entity, int $entityId, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM audit_logs WHERE entity = ? AND entity_id = ? ORDER BY id DESC LIMIT ? OFFSET ?';
        return $this->db->fetchAll($sql, [$entity, $entityId, $limit, $offset]);
    }

    public function getByUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM audit_logs WHERE user_id = ? ORDER BY id DESC LIMIT ? OFFSET ?';
        return $this->db->fetchAll($sql, [$userId, $limit, $offset]);
    }

    public function paginate(int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT * FROM audit_logs ORDER BY id DESC LIMIT ? OFFSET ?';
        return $this->db->fetchAll($sql, [$limit, $offset]);
    }

    public function count(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) as total FROM audit_logs');
        return (int)($row['total'] ?? 0);
    }
}

