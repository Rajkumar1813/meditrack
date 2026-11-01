<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Paginator;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\SettingRepository;

class ExpiryController
{
    private BatchRepository $batchRepo;
    private MedicineRepository $medRepo;
    private SettingRepository $settingRepo;

    public function __construct(
        BatchRepository $batchRepo,
        MedicineRepository $medRepo,
        SettingRepository $settingRepo
    ) {
        $this->batchRepo = $batchRepo;
        $this->medRepo = $medRepo;
        $this->settingRepo = $settingRepo;
    }

    /**
     * GET /api/v1/admin/expiring?days=30|90
     */
    public function expiring(Request $request): Response
    {
        $days = (int)($request->query('days') ?? 30);
        $days = in_array($days, [30, 90, 60, 180], true) ? $days : 30;

        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(Paginator::MAX_OFFSET_PER_PAGE, max(1, (int)($request->query('per_page') ?? Paginator::DEFAULT_OFFSET_PER_PAGE)));
        $offset = ($page - 1) * $perPage;

        $result = $this->batchRepo->getExpiring($days, $perPage, $offset);
        $paginated = Paginator::offset($page, $perPage, $result['total'], $result['items']);

        return ApiResponse::success(
            $paginated['data'],
            200,
            ['pagination' => $paginated['meta'], 'days' => $days]
        );
    }

    /**
     * GET /api/v1/admin/expired
     */
    public function expired(Request $request): Response
    {
        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(Paginator::MAX_OFFSET_PER_PAGE, max(1, (int)($request->query('per_page') ?? Paginator::DEFAULT_OFFSET_PER_PAGE)));
        $offset = ($page - 1) * $perPage;

        $result = $this->batchRepo->getExpired($perPage, $offset);
        $paginated = Paginator::offset($page, $perPage, $result['total'], $result['items']);

        return ApiResponse::success(
            $paginated['data'],
            200,
            ['pagination' => $paginated['meta']]
        );
    }

    /**
     * GET /api/v1/admin/low-stock
     */
    public function lowStock(Request $request): Response
    {
        $defaultThreshold = (int)($this->settingRepo->get('low_stock_threshold', '10') ?? 10);
        $threshold = (int)($request->query('threshold') ?? $defaultThreshold);

        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(Paginator::MAX_OFFSET_PER_PAGE, max(1, (int)($request->query('per_page') ?? Paginator::DEFAULT_OFFSET_PER_PAGE)));
        $offset = ($page - 1) * $perPage;

        $items = $this->medRepo->lowStock($threshold, $perPage, $offset);
        $total = count($items); // For grouped queries

        $paginated = Paginator::offset($page, $perPage, $total, $items);

        return ApiResponse::success(
            $paginated['data'],
            200,
            ['pagination' => $paginated['meta'], 'threshold' => $threshold]
        );
    }
}

