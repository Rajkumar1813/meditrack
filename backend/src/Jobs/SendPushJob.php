<?php

declare(strict_types=1);

namespace MediTrack\Jobs;

use MediTrack\Core\Container;
use MediTrack\Core\Queue;
use MediTrack\Services\Push\PushServiceInterface;

class SendPushJob
{
    private ?int $userId;
    private array $tokens;
    private string $title;
    private string $body;
    private array $data;

    public function __construct(
        ?int $userId = null,
        array $tokens = [],
        string $title = '',
        string $body = '',
        array $data = []
    ) {
        $this->userId = $userId;
        $this->tokens = $tokens;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    public static function forUser(int $userId, string $title, string $body, array $data = []): array
    {
        return [
            'job' => self::class,
            'data' => [
                'user_id' => $userId,
                'tokens' => [],
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ],
        ];
    }

    public static function forTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        return [
            'job' => self::class,
            'data' => [
                'user_id' => null,
                'tokens' => $tokens,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ],
        ];
    }

    public static function dispatch(
        ?int $userId,
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        ?Container $container = null
    ): bool {
        $container = $container ?? Container::getInstance();
        $job = new self($userId, $tokens, $title, $body, $data);

        if ($container->has(Queue::class)) {
            /** @var Queue $queue */
            $queue = $container->get(Queue::class);
            $payload = $userId !== null
                ? self::forUser($userId, $title, $body, $data)
                : self::forTokens($tokens, $title, $body, $data);

            if ($queue->push('push', $payload)) {
                return true;
            }
        }

        return $job->handle($container);
    }

    public function handle(Container $container): bool
    {
        if (!$container->has(PushServiceInterface::class)) {
            return false;
        }

        /** @var PushServiceInterface $pushService */
        $pushService = $container->get(PushServiceInterface::class);

        if ($this->userId !== null) {
            $pushService->sendToUser($this->userId, $this->title, $this->body, $this->data);
            return true;
        }

        if (!empty($this->tokens)) {
            $pushService->sendToTokens($this->tokens, $this->title, $this->body, $this->data);
            return true;
        }

        return false;
    }
}

