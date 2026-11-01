<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class SettingRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $row = $this->db->fetchOne('SELECT `value` FROM settings WHERE `key` = ?', [$key]);
        return $row ? (string)$row['value'] : $default;
    }

    public function set(string $key, ?string $value): void
    {
        if ($this->db->driver() === 'sqlite') {
            $sql = 'INSERT INTO settings (`key`, `value`, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT(`key`) DO UPDATE SET `value` = excluded.`value`, updated_at = CURRENT_TIMESTAMP';
        } else {
            $sql = 'INSERT INTO settings (`key`, `value`, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = CURRENT_TIMESTAMP';
        }
        $this->db->execute($sql, [$key, $value]);
    }

    public function all(): array
    {
        $rows = $this->db->fetchAll('SELECT `key`, `value` FROM settings ORDER BY `key` ASC');
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }

    public function getAll(): array
    {
        return $this->all();
    }

    public function has(string $key): bool
    {
        $row = $this->db->fetchOne('SELECT 1 FROM settings WHERE `key` = ?', [$key]);
        return $row !== null;
    }

    public function delete(string $key): bool
    {
        return $this->db->execute('DELETE FROM settings WHERE `key` = ?', [$key]) > 0;
    }
}
