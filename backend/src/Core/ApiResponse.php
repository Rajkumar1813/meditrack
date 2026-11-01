<?php

declare(strict_types=1);

namespace MediTrack\Core;

class ApiResponse extends Response
{
    public static function success(
        mixed $data = null,
        int|array|null $statusOrMeta = 200,
        int|array $metaOrStatus = [],
        ?string $message = null,
        array $headers = []
    ): self {
        $statusCode = 200;
        $meta = [];

        if (is_int($statusOrMeta)) {
            $statusCode = $statusOrMeta;
            if (is_array($metaOrStatus)) {
                $meta = $metaOrStatus;
            }
        } elseif (is_array($statusOrMeta)) {
            $meta = $statusOrMeta;
            if (is_int($metaOrStatus)) {
                $statusCode = $metaOrStatus;
            }
        } elseif ($statusOrMeta === null) {
            if (is_int($metaOrStatus)) {
                $statusCode = $metaOrStatus;
            }
        }

        $payload = [
            'success' => true,
            'data' => $data,
            'meta' => empty($meta) ? new \stdClass() : $meta,
            'message' => $message,
        ];

        $headers['Content-Type'] = 'application/json; charset=utf-8';
        return new self(
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $statusCode,
            $headers
        );
    }

    public static function error(
        string $code,
        string $message,
        int $statusCode = 400,
        ?array $fields = null,
        array $headers = []
    ): self {
        $errorObject = [
            'code' => $code,
            'message' => $message,
        ];

        if ($fields !== null) {
            if (isset($fields['retry_after'])) {
                $errorObject['retry_after'] = (int)$fields['retry_after'];
                unset($fields['retry_after']);
            }
            if (!empty($fields)) {
                $errorObject['fields'] = $fields;
            }
        }

        $payload = [
            'success' => false,
            'error' => $errorObject,
        ];

        $headers['Content-Type'] = 'application/json; charset=utf-8';
        return new self(
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $statusCode,
            $headers
        );
    }

    public static function paginated(
        array $items,
        array $paginationMeta,
        int $statusCode = 200,
        array $headers = []
    ): self {
        return self::success(
            $items,
            $statusCode,
            ['pagination' => $paginationMeta],
            null,
            $headers
        );
    }

    public static function forbidden(string $message = 'Forbidden', array $headers = []): self
    {
        return self::error('FORBIDDEN', $message, 403, null, $headers);
    }

    public static function notFound(string $message = 'Resource not found', array $headers = []): self
    {
        return self::error('NOT_FOUND', $message, 404, null, $headers);
    }

    public static function unauthorized(string $message = 'Unauthorized', array $headers = []): self
    {
        return self::error('UNAUTHORIZED', $message, 401, null, $headers);
    }

    public static function validationError(array $errors, string $message = 'Validation failed', array $headers = []): self
    {
        return self::error('VALIDATION_ERROR', $message, 422, $errors, $headers);
    }
}

