<?php

declare(strict_types=1);

namespace MediTrack\Core\Exceptions;

class RateLimitException extends HttpException
{
    protected int $retryAfter;

    public function __construct(
        int $retryAfter = 60,
        string $message = 'Too many requests. Please try again later.',
        string $errorCode = 'RATE_LIMITED',
        array $headers = []
    ) {
        $this->retryAfter = $retryAfter;
        $headers['Retry-After'] = (string)$retryAfter;
        parent::__construct(429, $message, $errorCode, $headers);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}

