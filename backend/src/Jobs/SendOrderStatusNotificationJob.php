<?php

declare(strict_types=1);

namespace MediTrack\Jobs;

use MediTrack\Core\Container;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\UserRepository;

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

        if ($container->has(\MediTrack\Core\Queue::class)) {
            $queue = $container->get(\MediTrack\Core\Queue::class);
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
        if (!$container->has(OrderRepository::class) || !$container->has(NotificationRepository::class)) {
            return false;
        }

        /** @var OrderRepository $orderRepo */
        $orderRepo = $container->get(OrderRepository::class);
        /** @var NotificationRepository $notificationRepo */
        $notificationRepo = $container->get(NotificationRepository::class);

        $order = $orderRepo->findById($this->orderId);
        if (!$order) {
            return false;
        }

        $userId = (int)$order['user_id'];
        $orderNo = $order['order_no'];
        $statusUpper = strtoupper($this->toStatus);

        $statusMessages = [
            'pending' => "Order #{$orderNo} has been received and is pending confirmation.",
            'confirmed' => "Order #{$orderNo} has been confirmed.",
            'packed' => "Order #{$orderNo} has been packed by our pharmacist.",
            'shipped' => "Order #{$orderNo} has been shipped.",
            'delivered' => "Order #{$orderNo} has been delivered. Thank you for choosing us!",
            'cancelled' => "Order #{$orderNo} has been cancelled." . ($this->note ? " Reason: {$this->note}" : ''),
        ];

        $title = "Order {$statusUpper}";
        $body = $statusMessages[$this->toStatus] ?? "Order #{$orderNo} is now {$this->toStatus}.";

        $notificationRepo->create($userId, 'order_status', $title, $body, [
            'order_id' => $this->orderId,
            'order_no' => $orderNo,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'note' => $this->note,
        ]);

        return true;
    }
}

