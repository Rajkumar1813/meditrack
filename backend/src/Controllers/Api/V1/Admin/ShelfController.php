<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Exceptions\NotFoundException;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Repositories\ShelfRepository;
use MediTrack\Repositories\RackRepository;
use MediTrack\Repositories\AuditLogRepository;

class ShelfController
{
    private ShelfRepository $shelfRepo;
    private RackRepository $rackRepo;
    private AuditLogRepository $auditRepo;

    public function __construct(
        ShelfRepository $shelfRepo,
        RackRepository $rackRepo,
        AuditLogRepository $auditRepo
    ) {
        $this->shelfRepo = $shelfRepo;
        $this->rackRepo = $rackRepo;
        $this->auditRepo = $auditRepo;
    }

    /**
     * GET /api/v1/admin/shelves
     */
    public function index(Request $request): Response
    {
        $rackId = $request->query('rack_id');
        if ($rackId) {
            $shelves = $this->shelfRepo->getByRackId((int)$rackId);
        } else {
            $shelves = $this->shelfRepo->all();
        }
        return ApiResponse::success($shelves);
    }

    /**
     * POST /api/v1/admin/shelves
     */
    public function store(Request $request): Response
    {
        $rackId = (int)$request->input('rack_id');
        $name = trim((string)$request->input('name'));

        if ($rackId <= 0) {
            throw new ValidationException(['rack_id' => ['Valid rack ID is required.']]);
        }
        if ($name === '') {
            throw new ValidationException(['name' => ['Shelf name is required.']]);
        }

        $rack = $this->rackRepo->findById($rackId);
        if (!$rack) {
            throw new NotFoundException("Rack with ID '{$rackId}' not found.");
        }

        $existing = $this->shelfRepo->findByRackAndName($rackId, $name);
        if ($existing) {
            throw new ValidationException(['name' => ['A shelf with this name already exists in this rack.']]);
        }

        $id = $this->shelfRepo->create($rackId, $name);
        $shelf = $this->shelfRepo->findById($id);

        $user = $request->getAttribute('user');
        $this->auditRepo->log('create', 'shelves', $id, null, $shelf, $user['id'] ?? null, $request->ip());

        return ApiResponse::success(
            $shelf,
            201,
            ['message' => function_exists('t') ? t('inventory.shelf_created') : 'Shelf created successfully.']
        );
    }

    /**
     * GET /api/v1/admin/shelves/{id}
     */
    public function show(Request $request, string $id): Response
    {
        $shelf = $this->shelfRepo->findById((int)$id);
        if (!$shelf) {
            throw new NotFoundException("Shelf with ID '{$id}' not found.");
        }
        return ApiResponse::success($shelf);
    }

    /**
     * PUT /api/v1/admin/shelves/{id}
     */
    public function update(Request $request, string $id): Response
    {
        $shelfId = (int)$id;
        $existing = $this->shelfRepo->findById($shelfId);
        if (!$existing) {
            throw new NotFoundException("Shelf with ID '{$id}' not found.");
        }

        $name = trim((string)$request->input('name'));
        $rackId = $request->input('rack_id') ? (int)$request->input('rack_id') : (int)$existing['rack_id'];

        if ($name === '') {
            throw new ValidationException(['name' => ['Shelf name is required.']]);
        }

        $duplicate = $this->shelfRepo->findByRackAndName($rackId, $name);
        if ($duplicate && (int)$duplicate['id'] !== $shelfId) {
            throw new ValidationException(['name' => ['A shelf with this name already exists in this rack.']]);
        }

        $this->shelfRepo->update($shelfId, $name, $rackId);
        $updated = $this->shelfRepo->findById($shelfId);

        $user = $request->getAttribute('user');
        $this->auditRepo->log('update', 'shelves', $shelfId, $existing, $updated, $user['id'] ?? null, $request->ip());

        return ApiResponse::success(
            $updated,
            200,
            ['message' => function_exists('t') ? t('inventory.shelf_updated') : 'Shelf updated successfully.']
        );
    }

    /**
     * DELETE /api/v1/admin/shelves/{id}
     */
    public function destroy(Request $request, string $id): Response
    {
        $shelfId = (int)$id;
        $existing = $this->shelfRepo->findById($shelfId);
        if (!$existing) {
            throw new NotFoundException("Shelf with ID '{$id}' not found.");
        }

        $this->shelfRepo->delete($shelfId);

        $user = $request->getAttribute('user');
        $this->auditRepo->log('delete', 'shelves', $shelfId, $existing, null, $user['id'] ?? null, $request->ip());

        return ApiResponse::success(
            null,
            200,
            ['message' => function_exists('t') ? t('inventory.shelf_deleted') : 'Shelf deleted successfully.']
        );
    }
}

