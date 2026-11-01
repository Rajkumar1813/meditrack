<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Database;
use MediTrack\Core\Cache;

class BackupService
{
    private Database $db;
    private Cache $cache;
    private string $storagePath;

    public function __construct(Database $db, Cache $cache, ?string $storagePath = null)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->storagePath = $storagePath ?? dirname(__DIR__, 2) . '/storage/backups';
    }

    /**
     * Create a structured backup file and return signed download token.
     */
    public function createBackup(): array
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $tables = [
            'settings', 'categories', 'manufacturers', 'salts', 'medicine_salt',
            'racks', 'shelves', 'suppliers', 'medicines', 'batches',
            'stock_movements', 'audit_logs'
        ];

        $data = [
            'meta' => [
                'app' => 'MediTrack',
                'version' => '1.0.0',
                'exported_at' => date('c'),
            ],
            'tables' => [],
        ];

        foreach ($tables as $table) {
            try {
                $rows = $this->db->fetchAll("SELECT * FROM {$table}");
                $data['tables'][$table] = $rows;
            } catch (\Throwable) {
                // Skip if table doesn't exist yet
            }
        }

        $timestamp = date('Y-m-d_His');
        $fileName = "meditrack_backup_{$timestamp}.json";
        $filePath = $this->storagePath . '/' . $fileName;

        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $downloadToken = bin2hex(random_bytes(24));
        $this->cache->set("backup_download:{$downloadToken}", $filePath, 900); // 15 min expiry

        return [
            'file_name' => $fileName,
            'file_size' => filesize($filePath),
            'download_token' => $downloadToken,
            'download_url' => "/api/v1/admin/backup/download?token={$downloadToken}",
            'expires_in' => 900,
        ];
    }

    /**
     * Retrieve the backup file path for a valid signed download token.
     */
    public function getBackupFileForToken(string $token): ?string
    {
        $path = $this->cache->get("backup_download:{$token}");
        if ($path && file_exists($path)) {
            return $path;
        }
        return null;
    }
}

