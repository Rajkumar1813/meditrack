<?php

declare(strict_types=1);

namespace MediTrack\Core;

use Predis\Client as RedisClient;

class Queue
{
    private ?RedisClient $redis;
    private ?Database $db;

    public function __construct(?RedisClient $redis = null, ?Database $db = null)
    {
        $this->redis = $redis;
        $this->db = $db;
    }

    public function setRedis(?RedisClient $redis): void
    {
        $this->redis = $redis;
    }

    public function setDatabase(?Database $db): void
    {
        $this->db = $db;
    }

    public function push(string $queue, mixed $job): bool
    {
        if ($this->redis === null) {
            return false;
        }

        $payload = is_string($job) ? $job : json_encode($job, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        try {
            $this->redis->lpush("queue:{$queue}", [$payload]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function pop(string $queue, int $timeout = 0): ?array
    {
        if ($this->redis === null) {
            return null;
        }

        try {
            if ($timeout > 0) {
                // Blocking pop
                $result = $this->redis->brpop(["queue:{$queue}"], $timeout);
                if (!empty($result) && isset($result[1])) {
                    return json_decode($result[1], true);
                }
            } else {
                $raw = $this->redis->rpop("queue:{$queue}");
                if ($raw !== null) {
                    return json_decode($raw, true);
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function fail(string $queue, mixed $payload, \Throwable $e): void
    {
        if ($this->db === null) {
            return;
        }

        $jsonPayload = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES);
        $errorMessage = $e->getMessage() . "\n" . $e->getTraceAsString();

        try {
            $this->db->execute(
                'INSERT INTO failed_jobs (queue, payload, error, failed_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)',
                [$queue, $jsonPayload, $errorMessage]
            );
        } catch (\Throwable) {
            // Log or silent fail
        }
    }
}
