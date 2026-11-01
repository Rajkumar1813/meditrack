<?php

declare(strict_types=1);

namespace MediTrack\Commands;

use MediTrack\Services\RefillService;

class HourlyRefillReminderCommand implements CommandInterface
{
    private RefillService $refillService;

    public function __construct(RefillService $refillService)
    {
        $this->refillService = $refillService;
    }

    public function name(): string
    {
        return 'hourly_refill_reminder';
    }

    public function description(): string
    {
        return 'Finds due refill reminders and dispatches push and in-app notifications.';
    }

    public function shouldRun(\DateTimeImmutable $now): bool
    {
        // Run once every hour at minute 0
        return (int)$now->format('i') === 0;
    }

    public function execute(): int|string
    {
        $processed = $this->refillService->processDueReminders();
        return "Processed {$processed} due refill reminder(s).";
    }
}

