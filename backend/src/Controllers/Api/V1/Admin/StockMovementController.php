<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Paginator;
use MediTrack\Repositories\StockMovementRepository;

class StockMovementController
{
    private StockMovementRepository $movementRepo;

    public function __construct(StockMovementRepository $movementRepo)
    {
        $this->movementRepo = $movementRepo;
    }

    /**
     * GET /api/v1/admin/stock-movements
     */
    public function index(Request $request): Response
    {
        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(Paginator::MAX_OFFSET_PER_PAGE, max(1, (int)($request->query('per_page') ?? Paginator::DEFAULT_OFFSET_PER_PAGE)));
        $offset = ($page - 1) * $perPage;

        $filters = [
            'batch_id' => $request->query('batch_id'),
            'type' => $request->query('type'),
            'user_id' => $request->query('user_id'),
        ];

        $result = $this->movementRepo->paginate($filters, $perPage, $offset);
        $paginated = Paginator::offset($page, $perPage, $result['total'], $result['items']);

        return ApiResponse::success(
            $paginated['data'],
            200,
            ['pagination' => $paginated['meta']]
        );
    }
}

