<?php

declare(strict_types=1);

namespace MediTrack\Services\Push;

use MediTrack\Repositories\DeviceTokenRepository;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Psr\Log\LoggerInterface;

class PushService implements PushServiceInterface
{
    private DeviceTokenRepository $deviceTokenRepo;
    private ?Messaging $messaging;
    private ?LoggerInterface $logger;

    public function __construct(
        DeviceTokenRepository $deviceTokenRepo,
        ?Messaging $messaging = null,
        ?LoggerInterface $logger = null
    ) {
        $this->deviceTokenRepo = $deviceTokenRepo;
        $this->messaging = $messaging;
        $this->logger = $logger;
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        $tokens = $this->deviceTokenRepo->getTokensByUserId($userId);
        if (empty($tokens)) {
            return ['sent' => 0, 'failed' => 0, 'invalid_tokens' => []];
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if (empty($tokens)) {
            return ['sent' => 0, 'failed' => 0, 'invalid_tokens' => []];
        }

        // If no Firebase messaging configured (e.g. dev/local without service credentials), log and return
        if ($this->messaging === null) {
            if ($this->logger) {
                $this->logger->info("[PushService Dev Log] Title: '{$title}' | Body: '{$body}' | Tokens: " . count($tokens));
            }
            return ['sent' => count($tokens), 'failed' => 0, 'invalid_tokens' => []];
        }

        $sentCount = 0;
        $failedCount = 0;
        $invalidTokens = [];

        // Cast all data values to string for FCM requirements
        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string)$k] = is_scalar($v) ? (string)$v : json_encode($v);
        }

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification(Notification::create($title, $body))
                    ->withData($stringData);

                $this->messaging->send($message);
                $sentCount++;
                $this->deviceTokenRepo->touchLastSeen($token);
            } catch (NotFound|InvalidMessage $e) {
                $failedCount++;
                $invalidTokens[] = $token;
                if ($this->logger) {
                    $this->logger->warning("Dead FCM token detected and marked for deletion: {$token}. Error: " . $e->getMessage());
                }
            } catch (\Throwable $e) {
                $failedCount++;
                $errMsg = $e->getMessage();
                if (
                    str_contains($errMsg, 'not-registered') ||
                    str_contains($errMsg, 'invalid-registration-token') ||
                    str_contains($errMsg, 'NOT_FOUND')
                ) {
                    $invalidTokens[] = $token;
                }
                if ($this->logger) {
                    $this->logger->error("FCM send failure for token {$token}: " . $errMsg);
                }
            }
        }

        // Cleanup dead tokens
        if (!empty($invalidTokens)) {
            $this->deviceTokenRepo->deleteTokens($invalidTokens);
        }

        return [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'invalid_tokens' => $invalidTokens,
        ];
    }
}

