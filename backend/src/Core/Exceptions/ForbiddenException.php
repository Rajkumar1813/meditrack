<?php

declare(strict_types=1);

namespace MediTrack\Core\Exceptions;

class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden', string $errorCode = 'FORBIDDEN', array $headers = [])
    {
        parent::__construct(403, $message, $errorCode, $headers);
    }
}

