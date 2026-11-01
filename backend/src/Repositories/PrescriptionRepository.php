<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;
use MediTrack\Core\Paginator;

class PrescriptionRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM prescriptions WHERE id = ?', [$id]);
    }

    public function findByIdAndUser(int $id, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM prescriptions WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    public function getFileById(int $fileId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM prescription_files WHERE id = ?', [$fileId]);
    }

    public function getFiles(int $prescriptionId): array
    {
        return $this->db->fetchAll('SELECT * FROM prescription_files WHERE prescription_id = ? ORDER BY id ASC', [$prescriptionId]);
    }

    public function create(
        int $userId,
        ?int $orderId,
        ?string $doctorName,
        ?string $patientName,
        array $files = []
    ): int {
        return $this->db->transaction(function (Database $db) use ($userId, $orderId, $doctorName, $patientName, $files) {
            $sql = 'INSERT INTO prescriptions (user_id, order_id, doctor_name, patient_name, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, "pending", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)';
            $db->execute($sql, [$userId, $orderId, $doctorName, $patientName]);
            $prescriptionId = (int)$db->lastInsertId();

            $fileSql = 'INSERT INTO prescription_files (prescription_id, path, mime, size_kb) VALUES (?, ?, ?, ?)';
            foreach ($files as $file) {
                $db->execute($fileSql, [
                    $prescriptionId,
                    $file['path'],
                    $file['mime'],
                    $file['size_kb'],
                ]);
            }

            return $prescriptionId;
        });
    }

    public function updateStatus(
        int $id,
        string $status,
        ?string $rejectReason = null,
        ?int $reviewedBy = null,
        ?string $validUntil = null
    ): bool {
        $sql = 'UPDATE prescriptions SET
                    status = ?,
                    reject_reason = ?,
                    reviewed_by = ?,
                    reviewed_at = CURRENT_TIMESTAMP,
                    valid_until = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?';

        return $this->db->execute($sql, [$status, $rejectReason, $reviewedBy, $validUntil, $id]) > 0;
    }

    public function findActiveApprovedByUser(int $userId): ?array
    {
        $today = date('Y-m-d');
        $sql = 'SELECT * FROM prescriptions
                WHERE user_id = ?
                  AND status = "approved"
                  AND (valid_until IS NULL OR valid_until >= ?)
                ORDER BY valid_until DESC, id DESC
                LIMIT 1';

        return $this->db->fetchOne($sql, [$userId, $today]);
    }

    public function paginateForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(Paginator::MAX_OFFSET_PER_PAGE, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $totalSql = 'SELECT COUNT(*) FROM prescriptions WHERE user_id = ?';
        $total = (int)$this->db->fetchColumn($totalSql, [$userId]);

        $sql = 'SELECT * FROM prescriptions
                WHERE user_id = ?
                ORDER BY id DESC
                LIMIT ? OFFSET ?';

        $items = $this->db->fetchAll($sql, [$userId, $perPage, $offset]);

        return Paginator::offset($page, $perPage, $total, $items);
    }

    public function paginateAdmin(
        int $page = 1,
        int $perPage = 20,
        ?string $status = null,
        ?string $q = null
    ): array {
        $page = max(1, $page);
        $perPage = min(Paginator::MAX_OFFSET_PER_PAGE, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if ($status !== null && trim($status) !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        if ($q !== null && trim($q) !== '') {
            $term = '%' . trim($q) . '%';
            $where[] = '(u.name LIKE ? OR u.phone LIKE ? OR p.doctor_name LIKE ? OR p.patient_name LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereSql = implode(' AND ', $where);

        $totalSql = "SELECT COUNT(*) FROM prescriptions p
                     JOIN users u ON p.user_id = u.id
                     WHERE {$whereSql}";
        $total = (int)$this->db->fetchColumn($totalSql, $params);

        $sql = "SELECT p.*, u.name as user_name, u.phone as user_phone, u.email as user_email
                FROM prescriptions p
                JOIN users u ON p.user_id = u.id
                WHERE {$whereSql}
                ORDER BY p.id DESC
                LIMIT ? OFFSET ?";

        $queryParams = array_merge($params, [$perPage, $offset]);
        $items = $this->db->fetchAll($sql, $queryParams);

        return Paginator::offset($page, $perPage, $total, $items);
    }
}
