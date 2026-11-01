<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Database;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\PaymentRepository;
use MediTrack\Repositories\RefundRepository;
use MediTrack\Repositories\WebhookEventRepository;
use MediTrack\Services\Payment\CodGateway;
use MediTrack\Services\Payment\PaymentGatewayInterface;
use MediTrack\Services\Payment\RazorpayGateway;

class PaymentService
{
    private PaymentRepository $paymentRepo;
    private RefundRepository $refundRepo;
    private WebhookEventRepository $webhookRepo;
    private OrderRepository $orderRepo;
    private OrderService $orderService;
    private Database $db;
    private RazorpayGateway $razorpayGateway;
    private CodGateway $codGateway;

    public function __construct(
        PaymentRepository $paymentRepo,
        RefundRepository $refundRepo,
        WebhookEventRepository $webhookRepo,
        OrderRepository $orderRepo,
        OrderService $orderService,
        Database $db,
        ?RazorpayGateway $razorpayGateway = null,
        ?CodGateway $codGateway = null
    ) {
        $this->paymentRepo = $paymentRepo;
        $this->refundRepo = $refundRepo;
        $this->webhookRepo = $webhookRepo;
        $this->orderRepo = $orderRepo;
        $this->orderService = $orderService;
        $this->db = $db;
        $this->razorpayGateway = $razorpayGateway ?? new RazorpayGateway();
        $this->codGateway = $codGateway ?? new CodGateway();
    }

    public function getRazorpayGateway(): RazorpayGateway
    {
        return $this->razorpayGateway;
    }

    /**
     * Create/prepare a payment transaction.
     * Amount is ALWAYS taken directly from order.total on the server.
     */
    public function createPayment(int $orderId, int $userId, ?string $gatewayName = null): array
    {
        $order = $this->orderRepo->findByIdAndUser($orderId, $userId);
        if (!$order) {
            throw new ValidationException(['order_id' => ['Order not found or does not belong to you.']]);
        }

        if ($order['status'] !== 'pending') {
            throw new ValidationException(['order' => ["Cannot initiate payment for order in '{$order['status']}' state."]]);
        }

        if ($order['payment_status'] === 'paid') {
            throw new ValidationException(['payment' => ['This order is already paid.']]);
        }

        $gateway = match ($gatewayName ?? $order['payment_method']) {
            'cod' => $this->codGateway,
            default => $this->razorpayGateway,
        };

        // Server-enforced amount (never trust client)
        $orderData = [
            'id' => (int)$order['id'],
            'order_no' => $order['order_no'],
            'total' => (float)$order['total'],
        ];

        $gatewayPayload = $gateway->createOrder($orderData);

        // Record payment in database
        $paymentId = $this->paymentRepo->create([
            'order_id' => $orderId,
            'gateway' => $gatewayPayload['gateway'],
            'gateway_order_id' => $gatewayPayload['gateway_order_id'] ?? null,
            'amount' => (float)$order['total'],
            'status' => 'created',
            'raw_response' => $gatewayPayload,
        ]);

        $gatewayPayload['payment_id'] = $paymentId;
        return $gatewayPayload;
    }

    /**
     * Verify payment signature (HMAC-SHA256, idempotent).
     */
    public function verifyPayment(int $orderId, int $userId, array $data): array
    {
        $order = $this->orderRepo->findByIdAndUser($orderId, $userId);
        if (!$order) {
            throw new ValidationException(['order_id' => ['Order not found or does not belong to you.']]);
        }

        // Idempotent: If already paid, return early without error
        if ($order['payment_status'] === 'paid') {
            $payment = $this->paymentRepo->findByOrderId($orderId);
            return [
                'order' => $order,
                'payment' => $payment,
                'verified' => true,
                'already_paid' => true,
            ];
        }

        if ($order['payment_method'] === 'cod') {
            throw new ValidationException(['payment' => ['Cash on Delivery orders are payable upon delivery.']]);
        }

        $razorpayOrderId = $data['razorpay_order_id'] ?? '';
        $razorpayPaymentId = $data['razorpay_payment_id'] ?? '';
        $signature = $data['razorpay_signature'] ?? '';

        // Strict HMAC verification
        $valid = $this->razorpayGateway->verifyPayment([
            'razorpay_order_id' => $razorpayOrderId,
            'razorpay_payment_id' => $razorpayPaymentId,
        ], $signature);

        if (!$valid) {
            // Update payment record as failed if exists
            $payment = $this->paymentRepo->findByOrderId($orderId);
            if ($payment) {
                $this->paymentRepo->updateStatus((int)$payment['id'], 'failed', $data);
            }
            throw new ValidationException(['signature' => ['Invalid payment signature. Verification failed.']]);
        }

        // Process successful capture in transaction
        $this->db->transaction(function () use ($orderId, $userId, $order, $razorpayOrderId, $razorpayPaymentId, $data) {
            $payment = $this->paymentRepo->findByOrderId($orderId);
            if ($payment) {
                $this->paymentRepo->updateGatewayDetails(
                    (int)$payment['id'],
                    $razorpayPaymentId,
                    'captured',
                    $data['method'] ?? 'online',
                    $data
                );
            } else {
                $this->paymentRepo->create([
                    'order_id' => $orderId,
                    'gateway' => 'razorpay',
                    'gateway_order_id' => $razorpayOrderId,
                    'gateway_payment_id' => $razorpayPaymentId,
                    'method' => $data['method'] ?? 'online',
                    'amount' => (float)$order['total'],
                    'status' => 'captured',
                    'raw_response' => $data,
                ]);
            }

            // Update order payment status
            $this->orderRepo->updatePaymentStatus($orderId, 'paid');

            // If no prescription required, auto-confirm order
            if ((int)$order['rx_required'] === 0 && $order['status'] === 'pending') {
                try {
                    $this->orderService->transition($orderId, 'confirmed', $userId, 'Payment received via Razorpay Checkout');
                } catch (\Throwable) {
                    // Non-fatal if order state changed concurrently
                }
            }
        });

        $updatedOrder = $this->orderRepo->findById($orderId);
        $updatedPayment = $this->paymentRepo->findByOrderId($orderId);

        return [
            'order' => $updatedOrder,
            'payment' => $updatedPayment,
            'verified' => true,
        ];
    }

    /**
     * Process incoming Razorpay webhook event with raw body HMAC verification and deduplication.
     */
    public function handleRazorpayWebhook(string $rawBody, string $signature): array
    {
        // 1. Verify raw body signature
        if (!$this->razorpayGateway->verifyWebhookSignature($rawBody, $signature)) {
            throw new ValidationException(['signature' => ['Invalid webhook signature.']]);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new ValidationException(['payload' => ['Invalid JSON webhook payload.']]);
        }

        $eventId = (string)($payload['id'] ?? $payload['event_id'] ?? '');
        $event = (string)($payload['event'] ?? '');

        if ($eventId === '') {
            $eventId = 'evt_' . md5($rawBody);
        }

        // 2. Idempotent check (deduplication via unique key in webhook_events)
        $recordId = $this->webhookRepo->record('razorpay', $eventId, $payload);
        if ($recordId === null) {
            return [
                'status' => 'already_processed',
                'event_id' => $eventId,
                'event' => $event,
            ];
        }

        // 3. Process event types
        $this->db->transaction(function () use ($event, $payload) {
            if ($event === 'payment.captured' || $event === 'order.paid') {
                $paymentEntity = $payload['payload']['payment']['entity'] ?? [];
                $gwOrderId = $paymentEntity['order_id'] ?? null;
                $gwPaymentId = $paymentEntity['id'] ?? null;
                $notes = $paymentEntity['notes'] ?? [];

                $orderId = isset($notes['order_id']) ? (int)$notes['order_id'] : null;

                if ($orderId === null && $gwOrderId !== null) {
                    $p = $this->paymentRepo->findByGatewayOrderId($gwOrderId);
                    if ($p) {
                        $orderId = (int)$p['order_id'];
                    }
                }

                if ($orderId !== null) {
                    $order = $this->orderRepo->findById($orderId);
                    if ($order && $order['payment_status'] !== 'paid') {
                        $payment = $this->paymentRepo->findByOrderId($orderId);
                        if ($payment) {
                            $this->paymentRepo->updateGatewayDetails(
                                (int)$payment['id'],
                                (string)$gwPaymentId,
                                'captured',
                                $paymentEntity['method'] ?? 'online',
                                $paymentEntity
                            );
                        }

                        $this->orderRepo->updatePaymentStatus($orderId, 'paid');

                        if ((int)$order['rx_required'] === 0 && $order['status'] === 'pending') {
                            try {
                                $this->orderService->transition($orderId, 'confirmed', null, 'Payment verified via Razorpay webhook');
                            } catch (\Throwable) {
                                // Continue
                            }
                        }
                    }
                }
            } elseif ($event === 'payment.failed') {
                $paymentEntity = $payload['payload']['payment']['entity'] ?? [];
                $gwOrderId = $paymentEntity['order_id'] ?? null;
                if ($gwOrderId) {
                    $p = $this->paymentRepo->findByGatewayOrderId($gwOrderId);
                    if ($p) {
                        $this->paymentRepo->updateStatus((int)$p['id'], 'failed', $paymentEntity);
                        $this->orderRepo->updatePaymentStatus((int)$p['order_id'], 'failed');

                        try {
                            $container = \MediTrack\Core\Container::getInstance();
                            if ($container->has(NotificationService::class)) {
                                $order = $this->orderRepo->findById((int)$p['order_id']);
                                if ($order) {
                                    $container->get(NotificationService::class)->notifyUser(
                                        (int)$order['user_id'],
                                        'payment_failed',
                                        'Payment Failed',
                                        "Payment for order #{$order['order_no']} failed. Tap to retry payment.",
                                        ['screen' => 'checkout', 'order_id' => (int)$order['id']]
                                    );
                                }
                            }
                        } catch (\Throwable) {
                            // Non-fatal
                        }
                    }
                }
            } elseif ($event === 'refund.processed') {
                $refundEntity = $payload['payload']['refund']['entity'] ?? [];
                $gwRefundId = $refundEntity['id'] ?? null;
                $gwPaymentId = $refundEntity['payment_id'] ?? null;

                if ($gwRefundId) {
                    $refund = $this->refundRepo->findByGatewayRefundId($gwRefundId);
                    if ($refund) {
                        $this->refundRepo->updateStatus((int)$refund['id'], 'processed', $gwRefundId);
                    }
                }

                if ($gwPaymentId) {
                    $payment = $this->paymentRepo->findByGatewayPaymentId($gwPaymentId);
                    if ($payment) {
                        $orderId = (int)$payment['order_id'];
                        $order = $this->orderRepo->findById($orderId);
                        $totalRefunded = $this->refundRepo->getTotalRefundedForPayment((int)$payment['id']);
                        $newStatus = ($totalRefunded >= (float)$order['total']) ? 'refunded' : 'partially_refunded';
                        $this->orderRepo->updatePaymentStatus($orderId, $newStatus);
                    }
                }
            }
        });

        $this->webhookRepo->markProcessed($recordId);

        return [
            'status' => 'processed',
            'event_id' => $eventId,
            'event' => $event,
        ];
    }

    /**
     * Admin issues a partial or full refund on an order.
     */
    public function processRefund(int $orderId, float $amount, ?string $reason = null, ?int $adminId = null): array
    {
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new ValidationException(['order_id' => ['Order not found.']]);
        }

        if (!in_array($order['payment_status'], ['paid', 'partially_refunded'], true)) {
            throw new ValidationException(['order' => ['Only paid or partially refunded orders can be refunded.']]);
        }

        if ($amount <= 0) {
            throw new ValidationException(['amount' => ['Refund amount must be greater than zero.']]);
        }

        $payment = $this->paymentRepo->findByOrderId($orderId);
        if (!$payment) {
            throw new ValidationException(['payment' => ['Payment record not found for this order.']]);
        }

        $totalRefunded = $this->refundRepo->getTotalRefundedForPayment((int)$payment['id']);
        $availableToRefund = round((float)$order['total'] - $totalRefunded, 2);

        if ($amount > $availableToRefund) {
            throw new ValidationException([
                'amount' => ["Refund amount of ₹{$amount} exceeds available refundable balance of ₹{$availableToRefund}."],
            ]);
        }

        return $this->db->transaction(function () use ($orderId, $order, $payment, $amount, $reason, $adminId, $totalRefunded) {
            $gateway = $order['payment_method'] === 'cod' ? $this->codGateway : $this->razorpayGateway;
            $gwRefund = $gateway->refund($payment['gateway_payment_id'] ?? 'PAYMENT_DEFAULT', $amount, $reason);

            $refundId = $this->refundRepo->create(
                (int)$payment['id'],
                $amount,
                $reason,
                $gwRefund['gateway_refund_id'],
                $gwRefund['status']
            );

            $newTotalRefunded = $totalRefunded + $amount;
            $newPaymentStatus = ($newTotalRefunded >= (float)$order['total']) ? 'refunded' : 'partially_refunded';

            $this->orderRepo->updatePaymentStatus($orderId, $newPaymentStatus);

            // Log refund in status history
            $this->db->execute(
                'INSERT INTO order_status_history (order_id, from_status, to_status, note, changed_by, created_at)
                 VALUES (?, (SELECT status FROM orders WHERE id = ?), (SELECT status FROM orders WHERE id = ?), ?, ?, CURRENT_TIMESTAMP)',
                [
                    $orderId,
                    $orderId,
                    $orderId,
                    "Refund of ₹{$amount} issued ({$reason}). Payment status: {$newPaymentStatus}.",
                    $adminId,
                ]
            );

            return [
                'refund_id' => $refundId,
                'order_id' => $orderId,
                'amount' => $amount,
                'gateway_refund_id' => $gwRefund['gateway_refund_id'],
                'status' => $gwRefund['status'],
                'new_payment_status' => $newPaymentStatus,
            ];
        });
    }
}

