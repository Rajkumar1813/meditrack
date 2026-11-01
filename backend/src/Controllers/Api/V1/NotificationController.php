<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Services\NotificationService;

class NotificationController
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * GET /api/v1/notifications
     * Cursor-based pagination.
     */
    public function index(Request $request): Response
    {
        $user = $request->attribute('user');
        $userId = (int)($user['id'] ?? 0);

        $query = $request->query();
        $cursor = isset($query['cursor']) && $query['cursor'] !== '' ? (int)$query['cursor'] : null;
        $limit = isset($query['limit']) ? (int)$query['limit'] : 20;
        $unreadOnly = filter_var($query['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $result = $this->notificationService->getCursorPaginated($userId, $cursor, $limit, $unreadOnly);

        return ApiResponse::success(
            $result['data'],
            200,
            $result['meta']
        );
    }

    /**
     * POST /api/v1/notifications/read
     * Mark single notification or all notifications as read.
     */
    public function markRead(Request $request): Response
    {
        $user = $request->attribute('user');
        $userId = (int)($user['id'] ?? 0);

        $body = $request->body();
        $all = filter_var($body['all'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $id = isset($body['id']) ? (int)$body['id'] : null;

        if ($all) {
            $updated = $this->notificationService->markAllAsRead($userId);
            return ApiResponse::success(
                ['all_marked' => true, 'count' => $updated],
                200,
                [],
                'All notifications marked as read.'
            );
        }

        if ($id !== null && $id > 0) {
            $updated = $this->notificationService->markAsRead($userId, $id);
            return ApiResponse::success(
                ['marked' => $updated, 'id' => $id],
                200,
                [],
                $updated ? 'Notification marked as read.' : 'Notification already read or not found.'
            );
        }

        return ApiResponse::validationError(['id' => ['Notification id or all=true is required.']]);
    }

    /**
     * GET /api/v1/notifications/preferences
     */
    public function getPreferences(Request $request): Response
    {
        $user = $request->attribute('user');
        $userId = (int)($user['id'] ?? 0);

        $prefs = $this->notificationService->getPreferences($userId);

        return ApiResponse::success($prefs);
    }

    /**
     * PUT /api/v1/notifications/preferences
     */
    public function updatePreferences(Request $request): Response
    {
        $user = $request->attribute('user');
        $userId = (int)($user['id'] ?? 0);

        $body = $request->body();
        $prefs = $this->notificationService->updatePreferences($userId, $body);

        return ApiResponse::success($prefs, 200, [], 'Notification preferences updated successfully.');
    }
}

