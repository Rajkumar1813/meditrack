<?php

declare(strict_types=1);

namespace MediTrack\Core\Exceptions;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Resource not found', string $errorCode = 'NOT_FOUND', array $headers = [])
    {
        parent::__construct(404, $message, $errorCode, $headers);
    }
}

