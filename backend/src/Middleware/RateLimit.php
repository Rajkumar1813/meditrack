<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\RateLimiter;
use MediTrack\Core\Exceptions\RateLimitException;

class RateLimit implements MiddlewareInterface
{
    private RateLimiter $rateLimiter;
    private array $limits;
    private string $group;

    public function __construct(string $group = 'authenticated', ?RateLimiter $rateLimiter = null)
    {
        $this->group = $group;
        $this->rateLimiter = $rateLimiter ?? RateLimiter::create();
        $this->limits = require dirname(__DIR__, 2) . '/config/rate_limits.php';
    }

    public static function group(string $group): self
    {
        return new self($group);
    }

    public function process(Request $request, callable $next): Response
    {
        $groupConfig = $this->limits[$this->group] ?? [
            'limit' => 120,
            'window' => 60,
            'fail_closed' => false,
        ];

        $limit = $groupConfig['limit'];
        $window = $groupConfig['window'];
        $failClosed = $groupConfig['fail_closed'] ?? false;

        // In testing environment without live Redis, do not fail-closed
        if (($_ENV['APP_ENV'] ?? '') === 'testing') {
            $failClosed = false;
        }

        $identifier = $this->resolveIdentifier($request);
        $cacheKey = "rl:{$this->group}:{$identifier}:{$window}";

        [$exceeded, $remaining, $retryAfter] = $this->rateLimiter->check($cacheKey, $limit, $window, $failClosed);

        if ($exceeded) {
            throw new RateLimitException(
                $retryAfter,
                "Bahut zyada requests. {$retryAfter} sec baad try karein."
            );
        }

        // Also check IP limit for OTP groups if applicable (docs/04 §C)
        if (in_array($this->group, ['otp_request', 'otp_verify'], true)) {
            $ip = $request->ip();
            $ipLimit = $this->group === 'otp_request' ? 10 : 20;
            $ipWindow = 3600;
            $ipKey = "rl:{$this->group}:ip:{$ip}:{$ipWindow}";
            [$ipExceeded, $ipRemaining, $ipRetryAfter] = $this->rateLimiter->check($ipKey, $ipLimit, $ipWindow, $failClosed);
            if ($ipExceeded) {
                throw new RateLimitException(
                    $ipRetryAfter,
                    "Bahut zyada requests. {$ipRetryAfter} sec baad try karein."
                );
            }
        }

        /** @var Response $response */
        $response = $next($request);

        $response->header('X-RateLimit-Limit', (string)$limit);
        $response->header('X-RateLimit-Remaining', (string)$remaining);

        return $response;
    }

    private function resolveIdentifier(Request $request): string
    {
        // If user ID is attached to request by auth middleware
        $userId = $request->getAttribute('user_id');
        if ($userId) {
            return "user:{$userId}";
        }

        // If phone/email identifier in body for OTP
        $bodyIdentifier = $request->input('identifier');
        if ($bodyIdentifier) {
            return 'ident:' . md5((string)$bodyIdentifier);
        }

        return 'ip:' . $request->ip();
    }
}

