<?php

declare(strict_types=1);

namespace MediTrack\Commands;

use MediTrack\Repositories\BatchRepository;
use MediTrack\Services\NotificationService;

class DailyExpiryAlertCommand implements CommandInterface
{
    private BatchRepository $batchRepo;
    private NotificationService $notificationService;

    public function __construct(BatchRepository $batchRepo, NotificationService $notificationService)
    {
        $this->batchRepo = $batchRepo;
        $this->notificationService = $notificationService;
    }

    public function name(): string
    {
        return 'daily_expiry_alert';
    }

    public function description(): string
    {
        return 'Daily 08:00 alert checking expiring and expired medicine batches.';
    }

    public function shouldRun(\DateTimeImmutable $now): bool
    {
        // Daily at 08:00
        return $now->format('H:i') === '08:00';
    }

    public function execute(): int|string
    {
        $expiring30 = $this->batchRepo->expiringWithin(30);
        $expired = $this->batchRepo->expired();

        $count30 = count($expiring30);
        $countExpired = count($expired);

        if ($count30 === 0 && $countExpired === 0) {
            return 'No expiring or expired batches today. All stocks safe.';
        }

        $title = 'Daily Expiry Digest';
        $body = "{$count30} batch(es) expiring in 30 days, {$countExpired} batch(es) already expired.";
        $data = [
            'screen' => 'admin_expiry',
            'expiring_30_count' => $count30,
            'expired_count' => $countExpired,
        ];

        $this->notificationService->notifyRole('admin', 'expiry', $title, $body, $data);
        $this->notificationService->notifyRole('pharmacist', 'expiry', $title, $body, $data);

        return "Sent expiry digest: {$count30} expiring in 30d, {$countExpired} expired.";
    }
}

