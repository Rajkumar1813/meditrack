<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Paginator;
use MediTrack\Repositories\AuditLogRepository;

class AuditLogController
{
    private AuditLogRepository $auditRepo;

    public function __construct(AuditLogRepository $auditRepo)
    {
        $this->auditRepo = $auditRepo;
    }

    /**
     * GET /api/v1/admin/audit-logs
     */
    public function index(Request $request): Response
    {
        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(Paginator::MAX_OFFSET_PER_PAGE, max(1, (int)($request->query('per_page') ?? Paginator::DEFAULT_OFFSET_PER_PAGE)));
        $offset = ($page - 1) * $perPage;

        $items = $this->auditRepo->paginate($perPage, $offset);
        $total = count($items);

        $paginated = Paginator::offset($page, $perPage, $total, $items);

        return ApiResponse::success(
            $paginated['data'],
            200,
            ['pagination' => $paginated['meta']]
        );
    }
}

