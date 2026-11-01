<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Services\RefillService;

class RefillController
{
    private RefillService $refillService;

    public function __construct(RefillService $refillService)
    {
        $this->refillService = $refillService;
    }

    /**
     * GET /api/v1/refills
     * List active and sent refill reminders for authenticated customer.
     */
    public function index(Request $request): Response
    {
        $user = $request->attribute('user');
        $userId = (int)($user['id'] ?? 0);

        $query = $request->query();
        $status = $query['status'] ?? null;

        $reminders = $this->refillService->listCustomerReminders($userId, $status);

        return ApiResponse::success($reminders);
    }

    /**
     * POST /api/v1/refills/{id}/snooze
     */
    public function snooze(Request $request, string|int|array $idOrVars): Response
    {
        $user = $request->attribute('user');
        $userId = (int)($user['id'] ?? 0);
        $id = (int)(is_array($idOrVars) ? ($idOrVars['id'] ?? 0) : $idOrVars);

        $body = $request->body();
        $days = (int)($body['days'] ?? 7);

        $updated = $this->refillService->snoozeReminder($userId, $id, $days);

        return ApiResponse::success(
            $updated,
            200,
            [],
            "Refill reminder snoozed for {$days} day(s)."
        );
    }

    /**
     * POST /api/v1/refills/{id}/dismiss
     */
    public function dismiss(Request $request, string|int|array $idOrVars): Response
    {
        $user = $request->attribute('user');
        $userId = (int)($user['id'] ?? 0);
        $id = (int)(is_array($idOrVars) ? ($idOrVars['id'] ?? 0) : $idOrVars);

        $updated = $this->refillService->dismissReminder($userId, $id);

        return ApiResponse::success(
            $updated,
            200,
            [],
            'Refill reminder dismissed.'
        );
    }

    /**
     * POST /api/v1/refills/{id}/reorder
     * 1-tap reorder: adds medicine to cart and updates reminder status.
     */
    public function reorder(Request $request, string|int|array $idOrVars): Response
    {
        $user = $request->attribute('user');
        $userId = (int)($user['id'] ?? 0);
        $id = (int)(is_array($idOrVars) ? ($idOrVars['id'] ?? 0) : $idOrVars);

        $result = $this->refillService->reorderReminder($userId, $id);

        return ApiResponse::success(
            $result,
            200,
            [],
            "Added {$result['medicine_name']} to your cart. Ready for checkout!"
        );
    }
}

