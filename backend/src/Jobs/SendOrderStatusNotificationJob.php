<?php

declare(strict_types=1);

namespace MediTrack\Jobs;

use MediTrack\Core\Container;
use MediTrack\Core\Queue;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Services\NotificationService;
use MediTrack\Services\RefillService;

class SendOrderStatusNotificationJob
{
    private int $orderId;
    private ?string $fromStatus;
    private string $toStatus;
    private ?string $note;

    public function __construct(int $orderId, ?string $fromStatus, string $toStatus, ?string $note = null)
    {
        $this->orderId = $orderId;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
        $this->note = $note;
    }

    public static function dispatch(
        int $orderId,
        ?string $fromStatus,
        string $toStatus,
        ?string $note = null,
        ?Container $container = null
    ): bool {
        $container = $container ?? Container::getInstance();
        $job = new self($orderId, $fromStatus, $toStatus, $note);

        if ($container->has(Queue::class)) {
            /** @var Queue $queue */
            $queue = $container->get(Queue::class);
            $pushed = $queue->push('notifications', [
                'job' => self::class,
                'data' => [
                    'order_id' => $orderId,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'note' => $note,
                ],
            ]);
            if ($pushed) {
                return true;
            }
        }

        return $job->handle($container);
    }

    public function handle(Container $container): bool
    {
        if (!$container->has(OrderRepository::class)) {
            return false;
        }

        /** @var OrderRepository $orderRepo */
        $orderRepo = $container->get(OrderRepository::class);

        $order = $orderRepo->findById($this->orderId);
        if (!$order) {
            return false;
        }

        $userId = (int)$order['user_id'];
        $orderNo = $order['order_no'];
        $statusUpper = strtoupper($this->toStatus);

        $courier = $order['courier'] ?? null;
        $trackingNo = $order['tracking_number'] ?? null;

        $shippedMsg = "Order #{$orderNo} has been shipped.";
        if ($courier || $trackingNo) {
            $shippedMsg = "Order #{$orderNo} has been shipped via " . ($courier ?? 'Courier') .
                ($trackingNo ? " (Tracking: {$trackingNo})" : "") . ".";
        }

        $statusMessages = [
            'pending' => "Order #{$orderNo} has been received and is pending confirmation.",
            'confirmed' => "Order #{$orderNo} has been confirmed.",
            'packed' => "Order #{$orderNo} has been packed by our pharmacist.",
            'shipped' => $shippedMsg,
            'delivered' => "Order #{$orderNo} has been delivered. Thank you for choosing MediTrack!",
            'cancelled' => "Order #{$orderNo} has been cancelled." . ($this->note ? " Reason: {$this->note}" : ''),
        ];

        $title = "Order {$statusUpper}";
        $body = $statusMessages[$this->toStatus] ?? "Order #{$orderNo} is now {$this->toStatus}.";

        $data = [
            'screen' => 'order_detail',
            'order_id' => $this->orderId,
            'order_no' => $orderNo,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'note' => $this->note,
            'courier' => $courier,
            'tracking_number' => $trackingNo,
        ];

        if ($container->has(NotificationService::class)) {
            /** @var NotificationService $notifService */
            $notifService = $container->get(NotificationService::class);
            $notifService->notifyUser($userId, 'order_status', $title, $body, $data);
        }

        // On DELIVERED: create refill reminders for the order medicines
        if ($this->toStatus === 'delivered' && $container->has(RefillService::class)) {
            try {
                /** @var RefillService $refillService */
                $refillService = $container->get(RefillService::class);
                $refillService->createFromOrder($this->orderId);
            } catch (\Throwable) {
                // Non-fatal if order items empty
            }
        }

        return true;
    }
}
