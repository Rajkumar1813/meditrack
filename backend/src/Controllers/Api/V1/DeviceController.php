<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\DeviceTokenRepository;

class DeviceController
{
    private DeviceTokenRepository $deviceTokenRepo;

    public function __construct(DeviceTokenRepository $deviceTokenRepo)
    {
        $this->deviceTokenRepo = $deviceTokenRepo;
    }

    /**
     * POST /api/v1/devices
     * Register device FCM token for push notifications.
     */
    public function register(Request $request): Response
    {
        $user = $request->attribute('user');
        $userId = (int)($user['id'] ?? 0);

        $body = $request->body();
        $token = trim((string)($body['fcm_token'] ?? $body['token'] ?? ''));
        $platform = strtolower(trim((string)($body['platform'] ?? 'android')));

        if ($token === '') {
            return ApiResponse::validationError(['fcm_token' => ['The fcm_token field is required.']]);
        }

        if (!in_array($platform, ['android', 'ios', 'web'], true)) {
            $platform = 'android';
        }

        $this->deviceTokenRepo->save($userId, $token, $platform);

        return ApiResponse::success(
            [
                'fcm_token' => $token,
                'platform' => $platform,
            ],
            200,
            [],
            'Device registered successfully.'
        );
    }

    /**
     * DELETE /api/v1/devices/{token}
     * Unregister device FCM token.
     */
    public function unregister(Request $request, string|int|array $tokenOrVars): Response
    {
        $user = $request->attribute('user');
        $userId = (int)($user['id'] ?? 0);

        $token = is_array($tokenOrVars) ? (string)($tokenOrVars['token'] ?? '') : (string)$tokenOrVars;
        if ($token === '') {
            $body = $request->body();
            $token = trim((string)($body['fcm_token'] ?? $body['token'] ?? ''));
        }

        if ($token === '') {
            return ApiResponse::validationError(['fcm_token' => ['The fcm_token field is required.']]);
        }

        $deleted = $this->deviceTokenRepo->deleteToken($token, $userId);

        return ApiResponse::success(
            ['deleted' => $deleted],
            200,
            [],
            'Device unregistered successfully.'
        );
    }
}
