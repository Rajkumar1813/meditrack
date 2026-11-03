<?php

declare(strict_types=1);

namespace MediTrack\Core;

use Predis\Client as RedisClient;
use Predis\Connection\ConnectionException;

class RateLimiter
{
    private ?RedisClient $redis;
    private array $config;

    public function __construct(?RedisClient $redis = null, array $config = [])
    {
        $this->redis = $redis;
        $this->config = $config;
    }

    public static function create(?array $redisConfig = null): self
    {
        $redisConfig = $redisConfig ?? require dirname(__DIR__, 2) . '/config/redis.php';
        if (!($redisConfig['enabled'] ?? true) || empty($redisConfig['host']) || $redisConfig['host'] === 'null') {
            return new self(null);
        }
        try {
            $client = new RedisClient([
                'scheme' => $redisConfig['scheme'] ?? 'tcp',
                'host' => $redisConfig['host'] ?? '127.0.0.1',
                'port' => $redisConfig['port'] ?? 6379,
                'password' => $redisConfig['password'] ?? null,
                'database' => $redisConfig['database'] ?? 0,
                'timeout' => $redisConfig['timeout'] ?? 0.5,
                'read_write_timeout' => $redisConfig['read_write_timeout'] ?? 0.5,
            ]);
            return new self($client);
        } catch (\Throwable $e) {
            return new self(null);
        }
    }

    public function setRedis(?RedisClient $redis): void
    {
        $this->redis = $redis;
    }

    public function getRedis(): ?RedisClient
    {
        return $this->redis;
    }

    /**
     * Increment attempts and check if rate limit is exceeded.
     * Lua script atomically runs INCR and sets TTL if key is new.
     *
     * @return array [bool $exceeded, int $remaining, int $retryAfter]
     */
    public function check(string $key, int $maxAttempts, int $decaySeconds, bool $failClosed = false): array
    {
        if ($this->redis === null) {
            if ($failClosed) {
                // If sensitive (OTP/auth) and redis is down, reject
                return [true, 0, $decaySeconds];
            }
            // For catalog/search, fail-open
            return [false, $maxAttempts, 0];
        }

        $lua = <<<LUA
local current = redis.call('INCR', KEYS[1])
if current == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
end
local ttl = redis.call('TTL', KEYS[1])
return {current, ttl}
LUA;

        try {
            $result = $this->redis->eval($lua, 1, $key, $decaySeconds);
            $attempts = (int)$result[0];
            $ttl = max(1, (int)$result[1]);

            $remaining = max(0, $maxAttempts - $attempts);
            $exceeded = $attempts > $maxAttempts;

            return [$exceeded, $remaining, $ttl];
        } catch (ConnectionException $e) {
            if ($failClosed) {
                return [true, 0, $decaySeconds];
            }
            return [false, $maxAttempts, 0];
        } catch (\Throwable $e) {
            $this->redis = null;
            if ($failClosed) {
                return [true, 0, $decaySeconds];
            }
            return [false, $maxAttempts, 0];
        }
    }

    public function attempts(string $key): int
    {
        if ($this->redis === null) {
            return 0;
        }
        try {
            return (int)($this->redis->get($key) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function reset(string $key): void
    {
        if ($this->redis !== null) {
            try {
                $this->redis->del([$key]);
            } catch (\Throwable) {}
        }
    }
}

