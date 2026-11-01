<?php

declare(strict_types=1);

/**
 * Rate limit configuration based on docs/04-scalability-pagination-rate-limit.md
 */
return [
    // Endpoint group => ['limit' => max_requests, 'window' => seconds, 'fail_closed' => bool]
    'otp_request' => [
        'limit' => 5,
        'window' => 3600, // 5 / hour per identifier
        'fail_closed' => true,
    ],
    'otp_verify' => [
        'limit' => 5,
        'window' => 300, // 5 attempts / 5 min
        'fail_closed' => true,
    ],
    'search_suggest' => [
        'limit' => 60,
        'window' => 60, // 60 / min
        'fail_closed' => false,
    ],
    'public_catalog' => [
        'limit' => 120,
        'window' => 60, // 120 / min
        'fail_closed' => false,
    ],
    'authenticated' => [
        'limit' => 120,
        'window' => 60, // 120 / min
        'fail_closed' => false,
    ],
    'prescriptions_upload' => [
        'limit' => 10,
        'window' => 3600, // 10 / hour
        'fail_closed' => true,
    ],
    'orders_create' => [
        'limit' => 10,
        'window' => 60, // 10 / min
        'fail_closed' => true,
    ],
    'payments' => [
        'limit' => 20,
        'window' => 60, // 20 / min
        'fail_closed' => true,
    ],
    'admin' => [
        'limit' => 300,
        'window' => 60, // 300 / min
        'fail_closed' => false,
    ],
    'admin_import' => [
        'limit' => 5,
        'window' => 3600, // 5 / hour
        'fail_closed' => true,
    ],
];

