<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Exceptions\NotFoundException;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\StockMovementRepository;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Validators\BatchValidator;
use MediTrack\Services\StockService;

class BatchController
{
    private BatchRepository $batchRepo;
    private MedicineRepository $medRepo;
    private StockMovementRepository $movementRepo;
    private AuditLogRepository $auditRepo;
    private BatchValidator $validator;
    private StockService $stockService;

    public function __construct(
        BatchRepository $batchRepo,
        MedicineRepository $medRepo,
        StockMovementRepository $movementRepo,
        AuditLogRepository $auditRepo,
        BatchValidator $validator,
        StockService $stockService
    ) {
        $this->batchRepo = $batchRepo;
        $this->medRepo = $medRepo;
        $this->movementRepo = $movementRepo;
        $this->auditRepo = $auditRepo;
        $this->validator = $validator;
        $this->stockService = $stockService;
    }

    /**
     * GET /api/v1/admin/medicines/{id}/batches
     */
    public function index(Request $request, string $id): Response
    {
        $medId = (int)$id;
        $medicine = $this->medRepo->findById($medId);
        if (!$medicine) {
            throw new NotFoundException("Medicine with ID '{$id}' not found.");
        }

        $batches = $this->batchRepo->getBatchesForMedicine($medId);
        return ApiResponse::success($batches);
    }

    /**
     * POST /api/v1/admin/medicines/{id}/batches
     */
    public function store(Request $request, string $id): Response
    {
        $medId = (int)$id;
        $medicine = $this->medRepo->findById($medId);
        if (!$medicine) {
            throw new NotFoundException("Medicine with ID '{$id}' not found.");
        }

        $data = $request->all();
        $data['medicine_id'] = $medId;

        $validation = $this->validator->validate($data);
        if ($validation->fails()) {
            throw new ValidationException($validation->errors());
        }

        $batchId = $this->batchRepo->create($data);

        $qty = (int)($data['quantity'] ?? 0);
        $user = $request->getAttribute('user');
        $userId = $user['id'] ?? null;

        // Log initial stock movement
        if ($qty > 0) {
            $this->movementRepo->log($batchId, 'purchase', $qty, 'initial_batch', null, $userId);
        }

        $batch = $this->batchRepo->findById($batchId);
        $this->auditRepo->log('create', 'batches', $batchId, null, $batch, $userId, $request->ip());

        return ApiResponse::success(
            $batch,
            201,
            ['message' => function_exists('t') ? t('inventory.batch_created') : 'Batch added successfully.']
        );
    }

    /**
     * PUT /api/v1/admin/batches/{id}
     */
    public function update(Request $request, string $id): Response
    {
        $batchId = (int)$id;
        $existing = $this->batchRepo->findById($batchId);
        if (!$existing) {
            throw new NotFoundException("Batch with ID '{$id}' not found.");
        }

        $data = $request->all();
        $data['medicine_id'] = (int)$existing['medicine_id'];

        $validation = $this->validator->validate($data, $batchId);
        if ($validation->fails()) {
            throw new ValidationException($validation->errors());
        }

        $oldQty = (int)$existing['quantity'];
        $newQty = (int)($data['quantity'] ?? $oldQty);
        $qtyDiff = $newQty - $oldQty;

        $this->batchRepo->update($batchId, $data);

        $user = $request->getAttribute('user');
        $userId = $user['id'] ?? null;

        if ($qtyDiff !== 0) {
            $this->movementRepo->log(
                $batchId,
                'adjustment',
                $qtyDiff,
                'manual_edit',
                null,
                $userId
            );
        }

        $updated = $this->batchRepo->findById($batchId);
        $this->auditRepo->log('update', 'batches', $batchId, $existing, $updated, $userId, $request->ip());

        return ApiResponse::success(
            $updated,
            200,
            ['message' => function_exists('t') ? t('inventory.batch_updated') : 'Batch updated successfully.']
        );
    }

    /**
     * POST /api/v1/admin/batches/{id}/dispose
     */
    public function dispose(Request $request, string $id): Response
    {
        $batchId = (int)$id;
        $existing = $this->batchRepo->findById($batchId);
        if (!$existing) {
            throw new NotFoundException("Batch with ID '{$id}' not found.");
        }

        $body = $request->all();
        $status = (string)($body['status'] ?? $body['action'] ?? 'disposed');
        $notes = isset($body['notes']) ? (string)$body['notes'] : null;

        $user = $request->getAttribute('user');
        $userId = $user['id'] ?? null;

        $this->stockService->dispose($batchId, $status, $notes, $userId);

        $updated = $this->batchRepo->findById($batchId);
        $this->auditRepo->log('dispose', 'batches', $batchId, $existing, $updated, $userId, $request->ip());

        return ApiResponse::success(
            $updated,
            200,
            ['message' => function_exists('t') ? t('inventory.batch_disposed') : 'Batch marked as disposed/returned.']
        );
    }

    /**
     * DELETE /api/v1/admin/batches/{id}
     */
    public function destroy(Request $request, string $id): Response
    {
        $batchId = (int)$id;
        $existing = $this->batchRepo->findById($batchId);
        if (!$existing) {
            throw new NotFoundException("Batch with ID '{$id}' not found.");
        }

        $this->batchRepo->delete($batchId);

        $user = $request->getAttribute('user');
        $this->auditRepo->log('delete', 'batches', $batchId, $existing, null, $user['id'] ?? null, $request->ip());

        return ApiResponse::success(null, 200, ['message' => 'Batch deleted successfully.']);
    }
}

