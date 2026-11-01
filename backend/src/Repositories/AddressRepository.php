<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class AddressRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findByUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC',
            [$userId]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM addresses WHERE id = ?', [$id]);
    }

    public function findByIdAndUser(int $id, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM addresses WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    public function getDefault(int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM addresses WHERE user_id = ? AND is_default = 1 LIMIT 1',
            [$userId]
        );
    }

    public function create(int $userId, array $data): int
    {
        return $this->db->transaction(function (Database $db) use ($userId, $data) {
            $count = (int)$db->fetchColumn('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$userId]);
            $isDefault = (!empty($data['is_default']) || $count === 0) ? 1 : 0;

            if ($isDefault === 1) {
                $db->execute('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
            }

            $sql = 'INSERT INTO addresses (
                        user_id, label, recipient_name, phone, line1, line2,
                        landmark, city, state, pincode, lat, lng, is_default,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )';

            $db->execute($sql, [
                $userId,
                $data['label'] ?? null,
                $data['recipient_name'],
                $data['phone'],
                $data['line1'],
                $data['line2'] ?? null,
                $data['landmark'] ?? null,
                $data['city'],
                $data['state'],
                $data['pincode'],
                $data['lat'] ?? null,
                $data['lng'] ?? null,
                $isDefault,
            ]);

            return (int)$db->lastInsertId();
        });
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $address = $this->findByIdAndUser($id, $userId);
        if (!$address) {
            return false;
        }

        return $this->db->transaction(function (Database $db) use ($id, $userId, $data, $address) {
            $isDefault = isset($data['is_default']) ? ($data['is_default'] ? 1 : 0) : (int)$address['is_default'];

            if ($isDefault === 1) {
                $db->execute('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
            }

            $sql = 'UPDATE addresses SET
                        label = ?, recipient_name = ?, phone = ?, line1 = ?, line2 = ?,
                        landmark = ?, city = ?, state = ?, pincode = ?, lat = ?, lng = ?,
                        is_default = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND user_id = ?';

            return $db->execute($sql, [
                $data['label'] ?? $address['label'],
                $data['recipient_name'] ?? $address['recipient_name'],
                $data['phone'] ?? $address['phone'],
                $data['line1'] ?? $address['line1'],
                $data['line2'] ?? $address['line2'],
                $data['landmark'] ?? $address['landmark'],
                $data['city'] ?? $address['city'],
                $data['state'] ?? $address['state'],
                $data['pincode'] ?? $address['pincode'],
                $data['lat'] ?? $address['lat'],
                $data['lng'] ?? $address['lng'],
                $isDefault,
                $id,
                $userId,
            ]) > 0;
        });
    }

    public function delete(int $id, int $userId): bool
    {
        return $this->db->transaction(function (Database $db) use ($id, $userId) {
            $address = $this->findByIdAndUser($id, $userId);
            if (!$address) {
                return false;
            }

            $deleted = $db->execute('DELETE FROM addresses WHERE id = ? AND user_id = ?', [$id, $userId]) > 0;

            // If the deleted address was the default, make the most recent remaining one default
            if ($deleted && (int)$address['is_default'] === 1) {
                $next = $db->fetchOne(
                    'SELECT id FROM addresses WHERE user_id = ? ORDER BY id DESC LIMIT 1',
                    [$userId]
                );
                if ($next) {
                    $db->execute('UPDATE addresses SET is_default = 1 WHERE id = ?', [$next['id']]);
                }
            }

            return $deleted;
        });
    }

    public function setDefault(int $id, int $userId): bool
    {
        $address = $this->findByIdAndUser($id, $userId);
        if (!$address) {
            return false;
        }

        return $this->db->transaction(function (Database $db) use ($id, $userId) {
            $db->execute('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
            return $db->execute('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?', [$id, $userId]) > 0;
        });
    }
}

