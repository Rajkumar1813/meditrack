<?php

declare(strict_types=1);

namespace MediTrack\Services\Payment;

class CodGateway implements PaymentGatewayInterface
{
    public function getGatewayName(): string
    {
        return 'cod';
    }

    public function createOrder(array $orderData): array
    {
        return [
            'gateway' => 'cod',
            'gateway_order_id' => 'COD-' . ($orderData['order_no'] ?? $orderData['id']),
            'amount' => (float)$orderData['total'],
            'currency' => 'INR',
            'order_id' => $orderData['id'],
            'order_no' => $orderData['order_no'] ?? '',
        ];
    }

    public function verifyPayment(array $paymentData, string $signature): bool
    {
        return true;
    }

    public function refund(string $gatewayPaymentId, float $amount, ?string $reason = null): array
    {
        return [
            'gateway_refund_id' => 'cod_rfnd_' . bin2hex(random_bytes(8)),
            'amount' => $amount,
            'status' => 'initiated',
        ];
    }
}

