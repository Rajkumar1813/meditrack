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
        ];
        $isHealthy = true;

        // 1. Check Database
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

        // 2. Check Redis
        if ($this->redis !== null) {
            try {
                $ping = $this->redis->ping();
                if ($ping !== 'PONG' && $ping !== true && (string)$ping !== '1') {
                    $services['redis'] = 'unexpected response';
                }
            } catch (\Throwable $e) {
                $services['redis'] = 'error: ' . $e->getMessage();
                // In local dev without docker, redis might be offline; don't break health completely if not strictly required, but mark status
            }
        } else {
            $services['redis'] = 'not_configured';
        }

        // 3. Check Storage write
        $storagePath = dirname(__DIR__, 2) . '/storage';
        if (is_dir($storagePath) && is_writable($storagePath)) {
            $services['storage'] = 'ok';
        } else {
            $services['storage'] = 'directory not writable or missing';
        }

        $statusCode = $isHealthy ? 200 : 503;

        return ApiResponse::success(
            [
                'status' => $isHealthy ? 'ok' : 'degraded',
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'services' => $services,
            ],
            $statusCode
        );
    }
}

