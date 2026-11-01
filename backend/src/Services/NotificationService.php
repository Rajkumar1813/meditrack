<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Queue;
use MediTrack\Jobs\SendPushJob;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\NotificationPreferenceRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\Push\PushServiceInterface;

class NotificationService
{
    private NotificationRepository $notificationRepo;
    private NotificationPreferenceRepository $preferenceRepo;
    private UserRepository $userRepo;
    private PushServiceInterface $pushService;
    private ?Queue $queue;

    public function __construct(
        NotificationRepository $notificationRepo,
        NotificationPreferenceRepository $preferenceRepo,
        UserRepository $userRepo,
        PushServiceInterface $pushService,
        ?Queue $queue = null
    ) {
        $this->notificationRepo = $notificationRepo;
        $this->preferenceRepo = $preferenceRepo;
        $this->userRepo = $userRepo;
        $this->pushService = $pushService;
        $this->queue = $queue;
    }

    public function notifyUser(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): int {
        // 1. Create in-app notification row
        $id = $this->notificationRepo->create($userId, $type, $title, $body, $data);

        // 2. Check user notification preference
        $preferenceChannel = match ($type) {
            'order_status', 'payment_failed' => 'order_updates',
            'refill' => 'refill_reminders',
            'low_stock', 'expiry' => 'stock_alerts',
            'promotional' => 'promotional_push',
            default => null,
        };

        $shouldPush = true;
        if ($preferenceChannel !== null) {
            $shouldPush = $this->preferenceRepo->isAllowed($userId, $preferenceChannel);
        }

        // 3. Dispatch push if permitted
        if ($shouldPush) {
            $pushData = array_merge($data, [
                'notification_id' => $id,
                'type' => $type,
            ]);

            SendPushJob::dispatch($userId, [], $title, $body, $pushData);
        }

        return $id;
    }

    public function notifyRole(
        string $role,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): int {
        $users = $this->userRepo->findByRole($role);
        $count = 0;
        foreach ($users as $user) {
            $this->notifyUser((int)$user['id'], $type, $title, $body, $data);
            $count++;
        }
        return $count;
    }

    public function getCursorPaginated(int $userId, ?int $cursor = null, int $limit = 20, bool $unreadOnly = false): array
    {
        return $this->notificationRepo->paginateCursor($userId, $cursor, $limit, $unreadOnly);
    }

    public function markAsRead(int $userId, int $id): bool
    {
        return $this->notificationRepo->markAsRead($id, $userId);
    }

    public function markAllAsRead(int $userId): int
    {
        return $this->notificationRepo->markAllAsRead($userId);
    }

    public function getPreferences(int $userId): array
    {
        return $this->preferenceRepo->getPreferences($userId);
    }

    public function updatePreferences(int $userId, array $data): array
    {
        return $this->preferenceRepo->updatePreferences($userId, $data);
    }
}

