<?php

declare(strict_types=1);

namespace MediTrack\Services\Push;

use MediTrack\Repositories\DeviceTokenRepository;

class FakePushService implements PushServiceInterface
{
    private DeviceTokenRepository $deviceTokenRepo;
    private array $sentPushes = [];
    private array $invalidTokens = [];

    public function __construct(DeviceTokenRepository $deviceTokenRepo)
    {
        $this->deviceTokenRepo = $deviceTokenRepo;
    }

    public function markAsInvalid(string ...$tokens): self
    {
        $this->invalidTokens = array_merge($this->invalidTokens, $tokens);
        return $this;
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        $tokens = $this->deviceTokenRepo->getTokensByUserId($userId);
        $result = $this->sendToTokens($tokens, $title, $body, array_merge($data, ['user_id' => $userId]));
        return $result;
    }

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        $invalid = array_intersect($tokens, $this->invalidTokens);
        $valid = array_diff($tokens, $this->invalidTokens);

        $this->sentPushes[] = [
            'tokens' => $tokens,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'valid_count' => count($valid),
            'invalid_count' => count($invalid),
        ];

        if (!empty($invalid)) {
            $this->deviceTokenRepo->deleteTokens(array_values($invalid));
        }

        return [
            'sent' => count($valid),
            'failed' => count($invalid),
            'invalid_tokens' => array_values($invalid),
        ];
    }

    public function sentPushes(): array
    {
        return $this->sentPushes;
    }

    public function hasSent(callable $filter): bool
    {
        return count(array_filter($this->sentPushes, $filter)) > 0;
    }

    public function count(): int
    {
        return count($this->sentPushes);
    }

    public function clear(): void
    {
        $this->sentPushes = [];
        $this->invalidTokens = [];
    }
}

