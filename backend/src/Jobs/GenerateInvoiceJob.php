<?php

declare(strict_types=1);

namespace MediTrack\Jobs;

use MediTrack\Core\Container;
use MediTrack\Services\InvoiceService;

class GenerateInvoiceJob
{
    private int $orderId;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    public static function dispatch(int $orderId, ?Container $container = null): bool
    {
        $container = $container ?? Container::getInstance();
        $job = new self($orderId);

        if ($container->has(\MediTrack\Core\Queue::class)) {
            $queue = $container->get(\MediTrack\Core\Queue::class);
            $pushed = $queue->push('invoices', [
                'job' => self::class,
                'data' => [
                    'order_id' => $orderId,
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
        if (!$container->has(InvoiceService::class)) {
            return false;
        }

        /** @var InvoiceService $invoiceService */
        $invoiceService = $container->get(InvoiceService::class);
        $invoiceService->generateForOrder($this->orderId);

        return true;
    }
}
