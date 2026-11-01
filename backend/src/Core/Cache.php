<?php

declare(strict_types=1);

namespace MediTrack\Core;

use Predis\Client as RedisClient;

class Cache
{
    private ?RedisClient $redis;
    private array $memory = [];
    private array $versions = [];

    public function __construct(?RedisClient $redis = null)
    {
        $this->redis = $redis;
    }

    public function setRedis(?RedisClient $redis): void
    {
        $this->redis = $redis;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->redis === null) {
            return array_key_exists($key, $this->memory) ? $this->memory[$key] : $default;
        }

        try {
            $value = $this->redis->get($key);
            if ($value === null) {
                return $default;
            }
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
        } catch (\Throwable) {
            return array_key_exists($key, $this->memory) ? $this->memory[$key] : $default;
        }
    }

    public function put(string $key, mixed $value, int $ttlSeconds = 3600): bool
    {
        $this->memory[$key] = $value;

        if ($this->redis === null) {
            return false;
        }

        try {
            $serialized = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->redis->setex($key, max(1, $ttlSeconds), $serialized);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 3600): bool
    {
        return $this->put($key, $value, $ttlSeconds);
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $fresh = $callback();
        $this->put($key, $fresh, $ttlSeconds);
        return $fresh;
    }

    public function forget(string $key): bool
    {
        unset($this->memory[$key]);

        if ($this->redis === null) {
            return true;
        }

        try {
            $this->redis->del([$key]);
            return true;
        } catch (\Throwable) {
            return true;
        }
    }

    public function delete(string $key): bool
    {
        return $this->forget($key);
    }

    /**
     * Versioned prefix tag invalidation.
     * e.g. catalog -> catalog:v1 -> bumpVersion('catalog') -> catalog:v2
     */
    public function getVersion(string $tag): int
    {
        if ($this->redis === null) {
            return $this->versions[$tag] ?? 1;
        }
        try {
            $v = $this->redis->get("version:{$tag}");
            return $v !== null ? (int)$v : 1;
        } catch (\Throwable) {
            return $this->versions[$tag] ?? 1;
        }
    }

    public function bumpVersion(string $tag): int
    {
        $this->versions[$tag] = ($this->versions[$tag] ?? 1) + 1;

        if ($this->redis === null) {
            return $this->versions[$tag];
        }
        try {
            return (int)$this->redis->incr("version:{$tag}");
        } catch (\Throwable) {
            return $this->versions[$tag];
        }
    }
}

