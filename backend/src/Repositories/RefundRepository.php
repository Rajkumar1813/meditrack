<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class RefundRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM refunds WHERE id = ?', [$id]);
    }

    public function findByPaymentId(int $paymentId): array
    {
        return $this->db->fetchAll('SELECT * FROM refunds WHERE payment_id = ? ORDER BY id DESC', [$paymentId]);
    }

    public function findByGatewayRefundId(string $gatewayRefundId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM refunds WHERE gateway_refund_id = ?', [$gatewayRefundId]);
    }

    public function create(
        int $paymentId,
        float $amount,
        ?string $reason = null,
        ?string $gatewayRefundId = null,
        string $status = 'initiated'
    ): int {
        $sql = 'INSERT INTO refunds (payment_id, amount, gateway_refund_id, reason, status, created_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)';

        $this->db->execute($sql, [
            $paymentId,
            $amount,
            $gatewayRefundId,
            $reason,
            $status,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getTotalRefundedForPayment(int $paymentId): float
    {
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE payment_id = ? AND status IN ('initiated', 'processed')";
        return (float)$this->db->fetchColumn($sql, [$paymentId]);
    }

    public function updateStatus(int $id, string $status, ?string $gatewayRefundId = null): bool
    {
        $sql = 'UPDATE refunds SET
                    status = ?,
                    gateway_refund_id = COALESCE(?, gateway_refund_id)
                WHERE id = ?';

        return $this->db->execute($sql, [$status, $gatewayRefundId, $id]) > 0;
    }
}

