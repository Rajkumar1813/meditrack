<?php

declare(strict_types=1);

namespace MediTrack\Bin;

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use MediTrack\Core\Container;
use MediTrack\Commands\Scheduler;
use MediTrack\Commands\ExpireUnpaidOrdersCommand;
use MediTrack\Commands\HourlyRefillReminderCommand;
use MediTrack\Commands\DailyExpiryAlertCommand;
use MediTrack\Commands\NightlyLowStockDigestCommand;
use Predis\Client as RedisClient;
use Monolog\Logger;

$basePath = dirname(__DIR__);
if (file_exists($basePath . '/.env')) {
    Dotenv::createImmutable($basePath)->safeLoad();
}

/** @var \MediTrack\Core\Router $router */
require_once $basePath . '/bootstrap/app.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting MediTrack Scheduler...\n";

$container = Container::getInstance();
$redis = $container->has(RedisClient::class) ? $container->get(RedisClient::class) : null;
$logger = $container->has(Logger::class) ? $container->get(Logger::class) : null;

$scheduler = new Scheduler($container, $redis, $logger);

if ($container->has(\MediTrack\Services\OrderService::class)) {
    $scheduler->register(new ExpireUnpaidOrdersCommand($container->get(\MediTrack\Services\OrderService::class)));
}
if ($container->has(\MediTrack\Services\RefillService::class)) {
    $scheduler->register(new HourlyRefillReminderCommand($container->get(\MediTrack\Services\RefillService::class)));
}
if ($container->has(\MediTrack\Repositories\BatchRepository::class) && $container->has(\MediTrack\Services\NotificationService::class)) {
    $scheduler->register(new DailyExpiryAlertCommand(
        $container->get(\MediTrack\Repositories\BatchRepository::class),
        $container->get(\MediTrack\Services\NotificationService::class)
    ));
}
if (
    $container->has(\MediTrack\Repositories\MedicineRepository::class) &&
    $container->has(\MediTrack\Repositories\SettingRepository::class) &&
    $container->has(\MediTrack\Services\NotificationService::class)
) {
    $scheduler->register(new NightlyLowStockDigestCommand(
        $container->get(\MediTrack\Repositories\MedicineRepository::class),
        $container->get(\MediTrack\Repositories\SettingRepository::class),
        $container->get(\MediTrack\Services\NotificationService::class)
    ));
}

$force = in_array('--force', $argv ?? [], true);
$results = $scheduler->run(null, $force);

foreach ($results as $cmd => $res) {
    echo " - [{$cmd}]: " . (is_string($res) ? $res : json_encode($res)) . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Scheduler finished.\n";
