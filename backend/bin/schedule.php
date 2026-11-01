<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;

$basePath = dirname(__DIR__);
if (file_exists($basePath . '/.env')) {
    Dotenv::createImmutable($basePath)->safeLoad();
}

/** @var \MediTrack\Core\Router $router */
require_once $basePath . '/bootstrap/app.php';

echo "[" . date('Y-m-d H:i:s') . "] Running MediTrack Scheduled Tasks...\n";

// 1. Expire unpaid online orders (> 15 min) and restore stock
try {
    $container = \MediTrack\Core\Container::getInstance();
    if ($container->has(\MediTrack\Services\OrderService::class)) {
        /** @var \MediTrack\Services\OrderService $orderService */
        $orderService = $container->get(\MediTrack\Services\OrderService::class);
        $cancelled = $orderService->expireUnpaidOrders(15);
        if ($cancelled > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Expired {$cancelled} unpaid order(s) and restored reserved stock.\n";
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error running ExpireUnpaidOrders: " . $e->getMessage() . "\n";
}

// Future phases schedule:
// - ExpiryAlert (daily 08:00)
// - RefillReminder (hourly)
// - LowStockDigest (nightly)

echo "Scheduled tasks completed.\n";

