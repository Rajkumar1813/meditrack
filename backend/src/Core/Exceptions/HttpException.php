<?php

declare(strict_types=1);

namespace MediTrack\Core\Exceptions;

class HttpException extends \Exception
{
    protected int $statusCode;
    protected string $errorCode;
    protected array $headers;

    public function __construct(
        int $statusCode = 500,
        string $message = 'Internal Server Error',
        string $errorCode = 'SERVER_ERROR',
        array $headers = [],
        ?\Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->headers = $headers;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}

