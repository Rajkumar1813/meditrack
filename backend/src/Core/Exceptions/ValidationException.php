<?php

declare(strict_types=1);

namespace MediTrack\Core\Exceptions;

class ValidationException extends HttpException
{
    protected array $errors;

    public function __construct(
        array $errors,
        string $message = 'Validation failed',
        string $errorCode = 'VALIDATION_FAILED',
        array $headers = []
    ) {
        $this->errors = $errors;
        parent::__construct(422, $message, $errorCode, $headers);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

