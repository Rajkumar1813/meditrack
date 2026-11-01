<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use MediTrack\Core\Container;
use MediTrack\Core\Queue;

$basePath = dirname(__DIR__);
if (file_exists($basePath . '/.env')) {
    Dotenv::createImmutable($basePath)->safeLoad();
}

/** @var \MediTrack\Core\Router $router */
require_once $basePath . '/bootstrap/app.php';

echo "MediTrack Worker started. Listening for background jobs...\n";

$container = Container::getInstance();
$queue = $container->has(Queue::class) ? $container->get(Queue::class) : null;

if (!$queue) {
    echo "Queue service not available. Exiting.\n";
    exit(1);
}

// In production / docker, process jobs in loop with sleep/timeout
$queues = ['default', 'notifications', 'push', 'invoices', 'imports'];

while (true) {
    $jobHandled = false;
    foreach ($queues as $q) {
        $job = $queue->pop($q, 2);
        if ($job !== null) {
            echo "Processing job from queue [{$q}]...\n";
            $jobHandled = true;
            if (is_array($job) && isset($job['job']) && class_exists($job['job'])) {
                try {
                    $jobClass = $job['job'];
                    $data = $job['data'] ?? [];
                    $ref = new \ReflectionClass($jobClass);
                    $constructor = $ref->getConstructor();
                    if ($constructor) {
                        $args = [];
                        foreach ($constructor->getParameters() as $param) {
                            $name = $param->getName();
                            $snake = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
                            $args[] = $data[$name] ?? ($data[$snake] ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null));
                        }
                        $instance = $ref->newInstanceArgs($args);
                    } else {
                        $instance = new $jobClass();
                    }
                    if (method_exists($instance, 'handle')) {
                        $instance->handle($container);
                    }
                    echo "Job [{$job['job']}] completed successfully.\n";
                } catch (\Throwable $e) {
                    echo "Job execution failed: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    if (!$jobHandled) {
        usleep(500000); // 0.5 sec sleep
    }
}

