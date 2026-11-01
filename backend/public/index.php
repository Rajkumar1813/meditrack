<?php

declare(strict_types=1);

// Prevent direct execution outside web server if desired, or run as front controller
define('MEDITRACK_START', microtime(true));

/** @var \MediTrack\Core\Router $router */
$router = require_once dirname(__DIR__) . '/bootstrap/app.php';

$request = \MediTrack\Core\Request::capture();

try {
    $response = $router->dispatch($request);
    $response->send();
} catch (\MediTrack\Core\Exceptions\HttpException $e) {
    $response = \MediTrack\Core\ApiResponse::error(
        $e->getErrorCode(),
        $e->getMessage(),
        $e->getStatusCode(),
        method_exists($e, 'getErrors') ? $e->getErrors() : null,
        $e->getHeaders()
    );
    $response->send();
} catch (\Throwable $e) {
    $isDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $response = \MediTrack\Core\ApiResponse::error(
        'SERVER_ERROR',
        $isDebug ? $e->getMessage() : 'An unexpected error occurred.',
        500
    );
    $response->send();
}

