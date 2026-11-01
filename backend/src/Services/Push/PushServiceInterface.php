<?php

declare(strict_types=1);

namespace MediTrack\Services\Push;

interface PushServiceInterface
{
    /**
     * Send push notification to a specific user (across all registered active devices).
     * Automatically cleans up invalid/unregistered tokens.
     *
     * @return array [ 'sent' => int, 'failed' => int, 'invalid_tokens' => array ]
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): array;

    /**
     * Send push notification to a specific list of FCM tokens.
     * Automatically deletes any tokens reported dead or invalid.
     *
     * @return array [ 'sent' => int, 'failed' => int, 'invalid_tokens' => array ]
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array;
}

