<?php

declare(strict_types=1);

namespace MediTrack\Commands;

use MediTrack\Services\OrderService;

class ExpireUnpaidOrdersCommand implements CommandInterface
{
    private OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function name(): string
    {
        return 'expire_unpaid_orders';
    }

    public function description(): string
    {
        return 'Cancels unpaid online orders older than 15 minutes and restores reserved stock.';
    }

    public function shouldRun(\DateTimeImmutable $now): bool
    {
        // Every 5 minutes
        return ((int)$now->format('i')) % 5 === 0;
    }

    public function execute(): int|string
    {
        $cancelled = $this->orderService->expireUnpaidOrders(15);
        return "Expired {$cancelled} unpaid order(s) and restored reserved stock.";
    }
}

