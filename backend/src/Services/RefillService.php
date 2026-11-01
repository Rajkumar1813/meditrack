<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Core\Exceptions\NotFoundException;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\RefillReminderRepository;
use MediTrack\Repositories\MedicineRepository;

class RefillService
{
    private RefillReminderRepository $refillRepo;
    private OrderRepository $orderRepo;
    private MedicineRepository $medicineRepo;
    private CartService $cartService;
    private NotificationService $notificationService;

    public function __construct(
        RefillReminderRepository $refillRepo,
        OrderRepository $orderRepo,
        MedicineRepository $medicineRepo,
        CartService $cartService,
        NotificationService $notificationService
    ) {
        $this->refillRepo = $refillRepo;
        $this->orderRepo = $orderRepo;
        $this->medicineRepo = $medicineRepo;
        $this->cartService = $cartService;
        $this->notificationService = $notificationService;
    }

    /**
     * Called when order transitions to 'delivered'.
     * Automatically calculates course days and schedules reminder (delivered + course_days - 3).
     */
    public function createFromOrder(int $orderId): array
    {
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            return [];
        }

        $userId = (int)$order['user_id'];
        $items = $this->orderRepo->getItemsWithBatches($orderId);
        $deliveredDate = date('Y-m-d');

        $createdReminders = [];
        foreach ($items as $item) {
            $medicineId = (int)$item['medicine_id'];

            // Determine course days (default 30)
            $courseDays = 30;

            // Calculate remind_at = delivered + course_days - 3 days
            $remindDays = max(1, $courseDays - 3);
            $remindAt = date('Y-m-d', strtotime("{$deliveredDate} + {$remindDays} days"));

            $id = $this->refillRepo->create($userId, $medicineId, $orderId, $courseDays, $remindAt);
            $createdReminders[] = $id;
        }

        return $createdReminders;
    }

    public function listCustomerReminders(int $userId, ?string $status = null): array
    {
        return $this->refillRepo->listForUser($userId, $status);
    }

    public function snoozeReminder(int $userId, int $reminderId, int $days = 7): array
    {
        $reminder = $this->refillRepo->findById($reminderId);
        if (!$reminder) {
            throw new NotFoundException('Refill reminder not found.');
        }

        if ((int)$reminder['user_id'] !== $userId) {
            throw new ForbiddenException('You do not own this refill reminder.');
        }

        $days = max(1, min(60, $days));
        $this->refillRepo->snooze($reminderId, $userId, $days);

        return $this->refillRepo->findById($reminderId);
    }

    public function dismissReminder(int $userId, int $reminderId): array
    {
        $reminder = $this->refillRepo->findById($reminderId);
        if (!$reminder) {
            throw new NotFoundException('Refill reminder not found.');
        }

        if ((int)$reminder['user_id'] !== $userId) {
            throw new ForbiddenException('You do not own this refill reminder.');
        }

        $this->refillRepo->dismiss($reminderId, $userId);

        return $this->refillRepo->findById($reminderId);
    }

    public function reorderReminder(int $userId, int $reminderId): array
    {
        $reminder = $this->refillRepo->findById($reminderId);
        if (!$reminder) {
            throw new NotFoundException('Refill reminder not found.');
        }

        if ((int)$reminder['user_id'] !== $userId) {
            throw new ForbiddenException('You do not own this refill reminder.');
        }

        $medicineId = (int)$reminder['medicine_id'];

        // 1-tap reorder: add directly into user's cart
        $cart = $this->cartService->addItem($userId, $medicineId, 1);

        // Mark status as ordered
        $this->refillRepo->markOrdered($reminderId, $userId);

        return [
            'reordered' => true,
            'medicine_id' => $medicineId,
            'medicine_name' => $reminder['medicine_name'],
            'cart' => $cart,
        ];
    }

    /**
     * Hourly scheduler worker: processes due reminders and dispatches push + in-app notification.
     */
    public function processDueReminders(?string $date = null): int
    {
        $checkDate = $date ?? date('Y-m-d');
        $due = $this->refillRepo->getDueReminders($checkDate);

        $processed = 0;
        foreach ($due as $rem) {
            $userId = (int)$rem['user_id'];
            $medicineName = $rem['medicine_name'];

            $title = "Refill Reminder: {$medicineName}";
            $body = "Your course of {$medicineName} is almost over. Tap to reorder with 1-click!";
            $data = [
                'screen' => 'refill',
                'reminder_id' => (int)$rem['id'],
                'medicine_id' => (int)$rem['medicine_id'],
            ];

            $this->notificationService->notifyUser($userId, 'refill', $title, $body, $data);
            $this->refillRepo->markSent((int)$rem['id']);
            $processed++;
        }

        return $processed;
    }
}

