<?php

declare(strict_types=1);

return [
    'endpoint' => rtrim($_ENV['AUTHGEAR_ENDPOINT'] ?? 'https://your-project.authgear.cloud', '/'),
    'client_id' => $_ENV['AUTHGEAR_CLIENT_ID'] ?? '',
    'redirect_uri' => $_ENV['AUTHGEAR_REDIRECT_URI'] ?? 'http://localhost:5173/auth-callback',
    'jwks_cache_ttl' => (int)($_ENV['AUTHGEAR_JWKS_CACHE_TTL'] ?? 86400), // 24 hours
];
