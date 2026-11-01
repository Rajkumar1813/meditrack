<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'MediTrack',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
    'key' => $_ENV['APP_KEY'] ?? '',
    'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'default-secret-change-in-production-min32',
    'jwt_ttl' => (int)($_ENV['JWT_TTL'] ?? 900), // 15 minutes
    'jwt_refresh_ttl' => (int)($_ENV['JWT_REFRESH_TTL'] ?? 2592000), // 30 days
    'timezone' => 'UTC',
    'default_locale' => 'en',
    'locales' => ['en', 'hi'],
];

