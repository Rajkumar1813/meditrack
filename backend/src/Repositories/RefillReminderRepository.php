<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class RefillReminderRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function create(
        int $userId,
        int $medicineId,
        ?int $sourceOrderId,
        int $courseDays,
        string $remindAt
    ): int {
        // If there's an existing active reminder for this user and medicine, update it
        $existing = $this->db->fetchOne(
            'SELECT id FROM refill_reminders WHERE user_id = ? AND medicine_id = ? AND status IN (\'active\', \'sent\')',
            [$userId, $medicineId]
        );

        if ($existing) {
            $sql = 'UPDATE refill_reminders 
                    SET source_order_id = ?, course_days = ?, remind_at = ?, snoozed_until = NULL, status = \'active\'
                    WHERE id = ?';
            $this->db->execute($sql, [$sourceOrderId, $courseDays, $remindAt, $existing['id']]);
            return (int)$existing['id'];
        }

        $sql = 'INSERT INTO refill_reminders (user_id, medicine_id, source_order_id, course_days, remind_at, status, created_at)
                VALUES (?, ?, ?, ?, ?, \'active\', CURRENT_TIMESTAMP)';
        $this->db->execute($sql, [$userId, $medicineId, $sourceOrderId, $courseDays, $remindAt]);
        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $sql = 'SELECT r.*, m.name as medicine_name, m.slug as medicine_slug, m.pack_size, m.image as medicine_image
                FROM refill_reminders r
                INNER JOIN medicines m ON m.id = r.medicine_id
                WHERE r.id = ?';
        $row = $this->db->fetchOne($sql, [$id]);
        return $row ?: null;
    }

    public function listForUser(int $userId, ?string $status = null): array
    {
        $conditions = ['r.user_id = ?'];
        $params = [$userId];

        if ($status !== null && $status !== '') {
            $conditions[] = 'r.status = ?';
            $params[] = $status;
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT r.*, 
                       m.name as medicine_name, 
                       m.slug as medicine_slug, 
                       m.pack_size, 
                       m.image as medicine_image,
                       COALESCE(
                           (SELECT b.selling_price 
                            FROM batches b 
                            WHERE b.medicine_id = m.id AND b.quantity > 0 AND b.exp_date > CURRENT_DATE
                            ORDER BY b.exp_date ASC LIMIT 1),
                           0.00
                       ) as estimated_price
                FROM refill_reminders r
                INNER JOIN medicines m ON m.id = r.medicine_id
                WHERE {$where}
                ORDER BY r.remind_at ASC, r.id DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function getDueReminders(string $date): array
    {
        $sql = "SELECT r.*, 
                       m.name as medicine_name, 
                       m.slug as medicine_slug,
                       u.name as user_name,
                       u.email as user_email,
                       u.phone as user_phone
                FROM refill_reminders r
                INNER JOIN medicines m ON m.id = r.medicine_id
                INNER JOIN users u ON u.id = r.user_id
                WHERE r.status = 'active'
                  AND r.remind_at <= ?
                  AND (r.snoozed_until IS NULL OR r.snoozed_until <= ?)
                  AND u.is_blocked = 0";

        return $this->db->fetchAll($sql, [$date, $date]);
    }

    public function snooze(int $id, int $userId, int $days): bool
    {
        $days = max(1, min(60, $days));
        $snoozedDate = date('Y-m-d', strtotime("+{$days} days"));

        $sql = 'UPDATE refill_reminders 
                SET snoozed_until = ?,
                    remind_at = ?,
                    status = \'active\'
                WHERE id = ? AND user_id = ?';
        return $this->db->execute($sql, [$snoozedDate, $snoozedDate, $id, $userId]) > 0;
    }

    public function dismiss(int $id, int $userId): bool
    {
        $sql = 'UPDATE refill_reminders SET status = \'dismissed\' WHERE id = ? AND user_id = ?';
        return $this->db->execute($sql, [$id, $userId]) > 0;
    }

    public function markOrdered(int $id, int $userId): bool
    {
        $sql = 'UPDATE refill_reminders SET status = \'ordered\' WHERE id = ? AND user_id = ?';
        return $this->db->execute($sql, [$id, $userId]) > 0;
    }

    public function markSent(int $id): bool
    {
        $sql = 'UPDATE refill_reminders SET status = \'sent\', last_notified_at = CURRENT_TIMESTAMP WHERE id = ?';
        return $this->db->execute($sql, [$id]) > 0;
    }
}
