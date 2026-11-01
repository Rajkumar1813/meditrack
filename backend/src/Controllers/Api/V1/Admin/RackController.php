<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Paginator;
use MediTrack\Core\Exceptions\NotFoundException;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Repositories\RackRepository;
use MediTrack\Repositories\AuditLogRepository;

class RackController
{
    private RackRepository $rackRepo;
    private AuditLogRepository $auditRepo;

    public function __construct(RackRepository $rackRepo, AuditLogRepository $auditRepo)
    {
        $this->rackRepo = $rackRepo;
        $this->auditRepo = $auditRepo;
    }

    /**
     * GET /api/v1/admin/racks
     */
    public function index(Request $request): Response
    {
        $racks = $this->rackRepo->allWithCounts();
        return ApiResponse::success($racks);
    }

    /**
     * POST /api/v1/admin/racks
     */
    public function store(Request $request): Response
    {
        $name = trim((string)$request->input('name'));
        if ($name === '') {
            throw new ValidationException(['name' => ['Rack name is required.']]);
        }

        $existing = $this->rackRepo->findByName($name);
        if ($existing) {
            throw new ValidationException(['name' => ['A rack with this name already exists.']]);
        }

        $id = $this->rackRepo->create($name);
        $rack = $this->rackRepo->findById($id);

        $user = $request->getAttribute('user');
        $this->auditRepo->log('create', 'racks', $id, null, $rack, $user['id'] ?? null, $request->ip());

        return ApiResponse::success(
            $rack,
            201,
            ['message' => function_exists('t') ? t('inventory.rack_created') : 'Rack created successfully.']
        );
    }

    /**
     * GET /api/v1/admin/racks/{id}
     */
    public function show(Request $request, string $id): Response
    {
        $rack = $this->rackRepo->findById((int)$id);
        if (!$rack) {
            throw new NotFoundException("Rack with ID '{$id}' not found.");
        }
        return ApiResponse::success($rack);
    }

    /**
     * PUT /api/v1/admin/racks/{id}
     */
    public function update(Request $request, string $id): Response
    {
        $rackId = (int)$id;
        $existing = $this->rackRepo->findById($rackId);
        if (!$existing) {
            throw new NotFoundException("Rack with ID '{$id}' not found.");
        }

        $name = trim((string)$request->input('name'));
        if ($name === '') {
            throw new ValidationException(['name' => ['Rack name is required.']]);
        }

        $duplicate = $this->rackRepo->findByName($name);
        if ($duplicate && (int)$duplicate['id'] !== $rackId) {
            throw new ValidationException(['name' => ['A rack with this name already exists.']]);
        }

        $this->rackRepo->update($rackId, $name);
        $updated = $this->rackRepo->findById($rackId);

        $user = $request->getAttribute('user');
        $this->auditRepo->log('update', 'racks', $rackId, $existing, $updated, $user['id'] ?? null, $request->ip());

        return ApiResponse::success(
            $updated,
            200,
            ['message' => function_exists('t') ? t('inventory.rack_updated') : 'Rack updated successfully.']
        );
    }

    /**
     * DELETE /api/v1/admin/racks/{id}
     */
    public function destroy(Request $request, string $id): Response
    {
        $rackId = (int)$id;
        $existing = $this->rackRepo->findById($rackId);
        if (!$existing) {
            throw new NotFoundException("Rack with ID '{$id}' not found.");
        }

        $this->rackRepo->delete($rackId);

        $user = $request->getAttribute('user');
        $this->auditRepo->log('delete', 'racks', $rackId, $existing, null, $user['id'] ?? null, $request->ip());

        return ApiResponse::success(
            null,
            200,
            ['message' => function_exists('t') ? t('inventory.rack_deleted') : 'Rack deleted successfully.']
        );
    }

    /**
     * GET /api/v1/admin/racks/{id}/medicines
     */
    public function medicines(Request $request, string $id): Response
    {
        $rackId = (int)$id;
        $rack = $this->rackRepo->findById($rackId);
        if (!$rack) {
            throw new NotFoundException("Rack with ID '{$id}' not found.");
        }

        $page = max(1, (int)($request->query('page') ?? 1));
        $perPage = min(Paginator::MAX_OFFSET_PER_PAGE, max(1, (int)($request->query('per_page') ?? Paginator::DEFAULT_OFFSET_PER_PAGE)));
        $offset = ($page - 1) * $perPage;

        $result = $this->rackRepo->getMedicinesInRack($rackId, $perPage, $offset);
        $paginated = Paginator::offset($page, $perPage, $result['total'], $result['items']);

        return ApiResponse::success(
            $paginated['data'],
            200,
            ['pagination' => $paginated['meta'], 'rack' => $rack]
        );
    }
}

