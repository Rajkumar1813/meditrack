<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use MediTrack\Core\Container;
use MediTrack\Services\OrderService;

$basePath = dirname(__DIR__);
if (file_exists($basePath . '/.env')) {
    Dotenv::createImmutable($basePath)->safeLoad();
}

require_once $basePath . '/bootstrap/app.php';

$container = Container::getInstance();
/** @var OrderService $orderService */
$orderService = $container->get(OrderService::class);

echo "[" . date('Y-m-d H:i:s') . "] Checking for expired unpaid online orders (15 min timeout)...\n";
$expiredCount = $orderService->expireUnpaidOrders(15);
echo "[" . date('Y-m-d H:i:s') . "] Done. Cancelled {$expiredCount} expired order(s) and restored reserved stock.\n";

