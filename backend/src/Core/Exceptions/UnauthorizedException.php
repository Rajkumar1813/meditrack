<?php

declare(strict_types=1);

namespace MediTrack\Core\Exceptions;

class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Unauthenticated', string $errorCode = 'UNAUTHORIZED', array $headers = [])
    {
        parent::__construct(401, $message, $errorCode, $headers);
    }
}

