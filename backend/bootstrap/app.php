<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use MediTrack\Core\Container;
use MediTrack\Core\Router;
use MediTrack\Core\Database;
use MediTrack\Core\Cache;
use MediTrack\Core\RateLimiter;
use MediTrack\Core\Queue;
use MediTrack\Core\Lang;
use MediTrack\Core\Jwt;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Exceptions\HttpException;
use Predis\Client as RedisClient;

// 1. Load Environment Variables
$basePath = dirname(__DIR__);
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv::createImmutable($basePath);
    $dotenv->safeLoad();
}

// 2. Set Up Structured Logging (Monolog)
$logPath = $basePath . '/storage/logs';
if (!is_dir($logPath)) {
    mkdir($logPath, 0755, true);
}

$logger = new Logger('meditrack');
$logger->pushHandler(new StreamHandler($logPath . '/app.log', Logger::DEBUG));

// 3. Global Uncaught Exception & Error Handler (Returns Clean JSON)
$isTesting = defined('PHPUNIT_RUNNING')
    || defined('PHPUNIT_COMPOSER_INSTALL')
    || (isset($_SERVER['argv'][0]) && str_contains($_SERVER['argv'][0], 'phpunit'))
    || (getenv('APP_ENV') === 'testing')
    || (($_ENV['APP_ENV'] ?? '') === 'testing');

if (!$isTesting) {
    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    set_exception_handler(function (\Throwable $e) use ($logger) {
        $requestId = bin2hex(random_bytes(8));
        $isDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $logger->error($e->getMessage(), [
            'request_id' => $requestId,
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        if ($e instanceof \MediTrack\Core\Exceptions\RateLimitException) {
            $response = ApiResponse::error(
                $e->getErrorCode(),
                $e->getMessage(),
                $e->getStatusCode(),
                ['retry_after' => $e->getRetryAfter()],
                array_merge($e->getHeaders(), ['X-Request-Id' => $requestId])
            );
        } elseif ($e instanceof HttpException) {
            $response = ApiResponse::error(
                $e->getErrorCode(),
                $e->getMessage(),
                $e->getStatusCode(),
                method_exists($e, 'getErrors') ? $e->getErrors() : null,
                array_merge($e->getHeaders(), ['X-Request-Id' => $requestId])
            );
        } else {
            $message = $isDebug ? $e->getMessage() : 'An unexpected server error occurred.';
            $response = ApiResponse::error(
                'SERVER_ERROR',
                $message,
                500,
                null,
                ['X-Request-Id' => $requestId]
            );
        }

        $response->send();
        exit(1);
    });
}

// 4. Initialize Container and Register Singletons
$container = Container::getInstance();
$container->instance(Logger::class, $logger);

// Database Singleton
$container->singleton(Database::class, function () use ($basePath) {
    $dbConfig = require $basePath . '/config/database.php';
    return new Database($dbConfig);
});

// Redis Singleton
$container->singleton(RedisClient::class, function () use ($basePath) {
    $redisConfig = require $basePath . '/config/redis.php';
    if (!($redisConfig['enabled'] ?? true) || empty($redisConfig['host']) || $redisConfig['host'] === 'null') {
        return null;
    }
    try {
        return new RedisClient([
            'scheme' => $redisConfig['scheme'] ?? 'tcp',
            'host' => $redisConfig['host'] ?? '127.0.0.1',
            'port' => $redisConfig['port'] ?? 6379,
            'password' => $redisConfig['password'] ?? null,
            'database' => $redisConfig['database'] ?? 0,
            'timeout' => $redisConfig['timeout'] ?? 1.5,
        ]);
    } catch (\Throwable) {
        return null;
    }
});

// Cache Singleton
$container->singleton(Cache::class, function ($c) {
    return new Cache($c->has(RedisClient::class) ? $c->get(RedisClient::class) : null);
});

// RateLimiter Singleton
$container->singleton(RateLimiter::class, function ($c) use ($basePath) {
    $redis = $c->has(RedisClient::class) ? $c->get(RedisClient::class) : null;
    $limits = require $basePath . '/config/rate_limits.php';
    return new RateLimiter($redis, $limits);
});

// Queue Singleton
$container->singleton(Queue::class, function ($c) {
    $redis = $c->has(RedisClient::class) ? $c->get(RedisClient::class) : null;
    $db = $c->has(Database::class) ? $c->get(Database::class) : null;
    return new Queue($redis, $db);
});

// Lang Singleton
$container->singleton(Lang::class, function () use ($basePath) {
    return new Lang($basePath . '/lang', 'en');
});

// JWT Singleton
$container->singleton(Jwt::class, function () {
    return new Jwt();
});

// SMS Provider Singleton
$container->singleton(\MediTrack\Services\Sms\SmsProviderInterface::class, function ($c) {
    return new \MediTrack\Services\Sms\LogProvider($c->has(Logger::class) ? $c->get(Logger::class) : null);
});

// Invoice Service Singleton
$container->singleton(\MediTrack\Services\InvoiceService::class, function ($c) {
    return new \MediTrack\Services\InvoiceService(
        $c->get(Database::class),
        $c->get(\MediTrack\Repositories\InvoiceRepository::class),
        $c->get(\MediTrack\Repositories\OrderRepository::class),
        $c->get(\MediTrack\Repositories\UserRepository::class),
        $c->get(\MediTrack\Repositories\SettingRepository::class),
        $c->has(\MediTrack\Repositories\NotificationRepository::class) ? $c->get(\MediTrack\Repositories\NotificationRepository::class) : null,
        $c->has(Logger::class) ? $c->get(Logger::class) : null
    );
});

// Customer Service Singleton
$container->singleton(\MediTrack\Services\CustomerService::class, function ($c) {
    return new \MediTrack\Services\CustomerService(
        $c->get(\MediTrack\Repositories\UserRepository::class),
        $c->has(\MediTrack\Repositories\AuditLogRepository::class) ? $c->get(\MediTrack\Repositories\AuditLogRepository::class) : null,
        $c->has(Logger::class) ? $c->get(Logger::class) : null
    );
});

// Router Singleton
$router = new Router();
$container->instance(Router::class, $router);

// 5. Load Routes
require $basePath . '/routes/api.php';

return $router;
