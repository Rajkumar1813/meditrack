<?php

declare(strict_types=1);

namespace MediTrack\Services\Payment;

interface PaymentGatewayInterface
{
    /**
     * Create an order with the payment gateway.
     *
     * @param array $orderData
     * @return array Gateway-specific order payload
     */
    public function createOrder(array $orderData): array;

    /**
     * Verify payment signature from checkout callback.
     *
     * @param array $paymentData e.g. ['razorpay_order_id' => string, 'razorpay_payment_id' => string]
     * @param string $signature
     * @return bool
     */
    public function verifyPayment(array $paymentData, string $signature): bool;

    /**
     * Issue a full or partial refund.
     *
     * @param string $gatewayPaymentId
     * @param float $amount
     * @param string|null $reason
     * @return array ['gateway_refund_id' => string, 'amount' => float, 'status' => string]
     */
    public function refund(string $gatewayPaymentId, float $amount, ?string $reason = null): array;

    /**
     * Return gateway identifier (e.g. 'razorpay', 'cod').
     */
    public function getGatewayName(): string;
}

