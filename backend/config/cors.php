<?php

declare(strict_types=1);

$defaultOrigins = [
    'http://localhost:5173',
    'http://localhost:3000',
    'http://localhost:8080',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:8080',
];

$envOrigins = !empty($_ENV['CORS_ALLOWED_ORIGINS'])
    ? array_filter(array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'])))
    : [];

return [
    'allowed_origins' => array_values(array_unique(array_merge($defaultOrigins, $envOrigins))),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Idempotency-Key',
        'Accept-Language',
        'If-None-Match',
    ],
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
        'ETag',
    ],
    'max_age' => 86400,
    'supports_credentials' => true,
];

