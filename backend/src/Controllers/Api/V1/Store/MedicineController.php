<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Store;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Exceptions\NotFoundException;
use MediTrack\Services\CatalogService;

class MedicineController
{
    private CatalogService $catalogService;

    public function __construct(CatalogService $catalogService)
    {
        $this->catalogService = $catalogService;
    }

    /**
     * GET /api/v1/medicines
     */
    public function index(Request $request): Response
    {
        $query = $request->query();

        $filters = [
            'category' => $query['category'] ?? null,
            'manufacturer' => $query['manufacturer'] ?? null,
            'requires_rx' => $query['requires_rx'] ?? null,
            'in_stock' => $query['in_stock'] ?? null,
            'min_price' => $query['min_price'] ?? null,
            'max_price' => $query['max_price'] ?? null,
            'q' => $query['q'] ?? null,
            'sort' => $query['sort'] ?? null,
        ];

        $pagination = [
            'type' => $query['type'] ?? (isset($query['cursor']) ? 'cursor' : 'offset'),
            'page' => (int)($query['page'] ?? 1),
            'per_page' => (int)($query['per_page'] ?? 20),
            'cursor' => $query['cursor'] ?? null,
            'limit' => (int)($query['limit'] ?? 20),
        ];

        $paginated = $this->catalogService->getMedicines($filters, $pagination);

        return ApiResponse::success(
            $paginated['data'],
            200,
            ['pagination' => $paginated['meta']]
        );
    }

    /**
     * GET /api/v1/medicines/{slug}
     */
    public function show(Request $request, string $slug): Response
    {
        $medicine = $this->catalogService->getMedicineBySlug($slug);
        if (!$medicine) {
            throw new NotFoundException("Medicine with slug '{$slug}' not found.");
        }

        return ApiResponse::success($medicine);
    }

    /**
     * GET /api/v1/search/suggest?q=
     */
    public function suggest(Request $request): Response
    {
        $q = (string)($request->query('q') ?? '');
        $suggestions = $this->catalogService->getSuggestions($q);

        return ApiResponse::success($suggestions);
    }
}
