<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class PaymentRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM payments WHERE id = ?', [$id]);
    }

    public function findByOrderId(int $orderId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$orderId]);
    }

    public function findByGatewayOrderId(string $gatewayOrderId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM payments WHERE gateway_order_id = ? ORDER BY id DESC LIMIT 1', [$gatewayOrderId]);
    }

    public function findByGatewayPaymentId(string $paymentId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM payments WHERE gateway_payment_id = ?', [$paymentId]);
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO payments (
                    order_id, gateway, gateway_order_id, gateway_payment_id,
                    method, amount, status, raw_response, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)';

        $this->db->execute($sql, [
            $data['order_id'],
            $data['gateway'],
            $data['gateway_order_id'] ?? null,
            $data['gateway_payment_id'] ?? null,
            $data['method'] ?? null,
            $data['amount'],
            $data['status'] ?? 'created',
            isset($data['raw_response']) ? (is_string($data['raw_response']) ? $data['raw_response'] : json_encode($data['raw_response'])) : null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?array $rawResponse = null): bool
    {
        $rawJson = $rawResponse ? json_encode($rawResponse) : null;
        $sql = 'UPDATE payments SET
                    status = ?,
                    raw_response = COALESCE(?, raw_response),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?';
        return $this->db->execute($sql, [$status, $rawJson, $id]) > 0;
    }

    public function updateGatewayDetails(
        int $id,
        string $gatewayPaymentId,
        string $status,
        ?string $method = null,
        ?array $rawResponse = null
    ): bool {
        $rawJson = $rawResponse ? json_encode($rawResponse) : null;
        $sql = 'UPDATE payments SET
                    gateway_payment_id = ?,
                    status = ?,
                    method = COALESCE(?, method),
                    raw_response = COALESCE(?, raw_response),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?';
        return $this->db->execute($sql, [$gatewayPaymentId, $status, $method, $rawJson, $id]) > 0;
    }
}
