<?php

declare(strict_types=1);

return [
    'scheme' => 'tcp',
    'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
    'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
    'password' => !empty($_ENV['REDIS_PASSWORD']) && $_ENV['REDIS_PASSWORD'] !== 'null' ? $_ENV['REDIS_PASSWORD'] : null,
    'database' => (int)($_ENV['REDIS_DB'] ?? 0),
    'prefix' => $_ENV['REDIS_PREFIX'] ?? 'meditrack:',
    'timeout' => 1.5,
];

