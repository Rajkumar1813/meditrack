<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Store;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Services\CatalogService;

class CategoryController
{
    private CatalogService $catalogService;

    public function __construct(CatalogService $catalogService)
    {
        $this->catalogService = $catalogService;
    }

    /**
     * GET /api/v1/categories
     */
    public function index(Request $request): Response
    {
        $categories = $this->catalogService->getCategories(true);
        return ApiResponse::success($categories);
    }
}
