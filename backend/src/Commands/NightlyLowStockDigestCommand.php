<?php

declare(strict_types=1);

namespace MediTrack\Commands;

use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Services\NotificationService;

class NightlyLowStockDigestCommand implements CommandInterface
{
    private MedicineRepository $medicineRepo;
    private SettingRepository $settingRepo;
    private NotificationService $notificationService;

    public function __construct(
        MedicineRepository $medicineRepo,
        SettingRepository $settingRepo,
        NotificationService $notificationService
    ) {
        $this->medicineRepo = $medicineRepo;
        $this->settingRepo = $settingRepo;
        $this->notificationService = $notificationService;
    }

    public function name(): string
    {
        return 'nightly_low_stock_digest';
    }

    public function description(): string
    {
        return 'Nightly 22:00 digest checking medicines at or below low stock threshold.';
    }

    public function shouldRun(\DateTimeImmutable $now): bool
    {
        // Nightly at 22:00
        return $now->format('H:i') === '22:00';
    }

    public function execute(): int|string
    {
        $threshold = (int)$this->settingRepo->get('low_stock_threshold', '10');
        $lowStockItems = $this->medicineRepo->lowStock($threshold, 100);

        $count = count($lowStockItems);
        if ($count === 0) {
            return "No low stock medicines found (Threshold: {$threshold}).";
        }

        $title = 'Nightly Low Stock Digest';
        $body = "{$count} medicine(s) are at or below reorder threshold ({$threshold} units).";
        $data = [
            'screen' => 'admin_low_stock',
            'threshold' => $threshold,
            'low_stock_count' => $count,
        ];

        $this->notificationService->notifyRole('admin', 'low_stock', $title, $body, $data);

        return "Sent low stock digest for {$count} medicine(s).";
    }
}
