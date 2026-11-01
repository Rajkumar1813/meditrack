<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Services\AdminDashboardService;

class DashboardController
{
    private AdminDashboardService $dashboardService;

    public function __construct(AdminDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * GET /api/v1/admin/dashboard/stats
     */
    public function stats(Request $request): Response
    {
        $stats = $this->dashboardService->getStats();
        return ApiResponse::success($stats);
    }
}

