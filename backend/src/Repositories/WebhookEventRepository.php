<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class WebhookEventRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findByProviderAndEventId(string $provider, string $eventId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM webhook_events WHERE provider = ? AND event_id = ?',
            [$provider, $eventId]
        );
    }

    public function record(string $provider, string $eventId, array $payload): ?int
    {
        $existing = $this->findByProviderAndEventId($provider, $eventId);
        if ($existing) {
            return null; // Already recorded (idempotent duplicate)
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $sql = 'INSERT INTO webhook_events (provider, event_id, payload, created_at)
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP)';
            $this->db->execute($sql, [$provider, $eventId, $json]);
            return (int)$this->db->lastInsertId();
        } catch (\Throwable) {
            return null; // Handled concurrent duplicate race condition
        }
    }

    public function markProcessed(int $id): bool
    {
        return $this->db->execute(
            'UPDATE webhook_events SET processed_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$id]
        ) > 0;
    }
}

