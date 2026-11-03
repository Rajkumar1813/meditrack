<?php

declare(strict_types=1);

namespace MediTrack\Controllers;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Database;
use Predis\Client as RedisClient;

class HealthController
{
    private ?Database $db;
    private ?RedisClient $redis;

    public function __construct(?Database $db = null, ?RedisClient $redis = null)
    {
        $this->db = $db;
        $this->redis = $redis;
    }

    public function check(Request $request): Response
    {
        $services = [
            'database' => 'ok',
            'redis' => 'ok',
            'storage' => 'ok',
            'queue' => 'ok',
            'opcache' => 'unknown',
        ];
        $isHealthy = true;

        // 1. Check Database connection
        if ($this->db !== null) {
            try {
                $this->db->query('SELECT 1');
            } catch (\Throwable $e) {
                $services['database'] = 'error: ' . $e->getMessage();
                $isHealthy = false;
            }
        } else {
            $services['database'] = 'not_configured';
        }

        // 2. Check Redis connection & Queue size
        if ($this->redis !== null) {
            try {
                $ping = $this->redis->ping();
                if ($ping !== 'PONG' && $ping !== true && (string)$ping !== '1') {
                    $services['redis'] = 'unexpected response';
                }

                $queueDepth = (int)$this->redis->llen('queue:default');
                $services['queue'] = [
                    'status' => 'ok',
                    'pending_jobs' => $queueDepth,
                ];
            } catch (\Throwable $e) {
                $services['redis'] = 'error: ' . $e->getMessage();
                $services['queue'] = 'unavailable';
            }
        } else {
            $services['redis'] = 'not_configured';
            $services['queue'] = 'not_configured';
        }

        // 3. Check Storage write permissions
        $storagePath = dirname(__DIR__, 2) . '/storage';
        if (is_dir($storagePath) && is_writable($storagePath)) {
            $services['storage'] = 'ok';
        } else {
            $services['storage'] = 'directory not writable or missing';
            $isHealthy = false;
        }

        // 4. Check OPcache status
        if (function_exists('opcache_get_status')) {
            $opcacheStatus = @opcache_get_status(false);
            $services['opcache'] = is_array($opcacheStatus) && ($opcacheStatus['opcache_enabled'] ?? false)
                ? 'enabled'
                : 'disabled';
        } else {
            $services['opcache'] = 'unsupported';
        }

        $statusCode = $isHealthy ? 200 : 503;

        return ApiResponse::success(
            [
                'status' => $isHealthy ? 'ok' : 'degraded',
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'php_version' => PHP_VERSION,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'services' => $services,
            ],
            $statusCode
        );
    }
}
