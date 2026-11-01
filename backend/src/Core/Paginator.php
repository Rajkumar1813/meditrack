<?php

declare(strict_types=1);

namespace MediTrack\Core;

class Paginator
{
    public const DEFAULT_OFFSET_PER_PAGE = 20;
    public const MAX_OFFSET_PER_PAGE = 100;
    public const DEFAULT_CURSOR_LIMIT = 20;
    public const MAX_CURSOR_LIMIT = 50;

    /**
     * Build standard offset pagination envelope.
     */
    public static function offset(int $page, int $perPage, int $total, array $items): array
    {
        $page = max(1, $page);
        $perPage = min(self::MAX_OFFSET_PER_PAGE, max(1, $perPage));
        $lastPage = $total > 0 ? (int)ceil($total / $perPage) : 1;
        $from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
        $to = $total > 0 ? min($total, $page * $perPage) : 0;

        return [
            'data' => $items,
            'meta' => [
                'type' => 'offset',
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    /**
     * Build standard cursor pagination envelope.
     */
    public static function cursor(array $items, int $limit, ?string $nextCursor, bool $hasMore): array
    {
        $limit = min(self::MAX_CURSOR_LIMIT, max(1, $limit));

        return [
            'data' => $items,
            'meta' => [
                'type' => 'cursor',
                'limit' => $limit,
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
            ],
        ];
    }

    /**
     * Encode cursor payload into URL-safe base64 string.
     */
    public static function encodeCursor(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * Decode cursor string back into array.
     */
    public static function decodeCursor(?string $cursor): ?array
    {
        if (empty($cursor)) {
            return null;
        }

        $remainder = strlen($cursor) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $cursor .= str_repeat('=', $padlen);
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);
        return is_array($data) ? $data : null;
    }
}

