<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Paginator;
use MediTrack\Core\Exceptions\NotFoundException;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\SaltRepository;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Validators\MedicineValidator;

class MedicineController
{
    private MedicineRepository $medRepo;
    private BatchRepository $batchRepo;
    private SaltRepository $saltRepo;
    private AuditLogRepository $auditRepo;
    private MedicineValidator $validator;

    public function __construct(
        MedicineRepository $medRepo,
        BatchRepository $batchRepo,
        SaltRepository $saltRepo,
        AuditLogRepository $auditRepo,
        MedicineValidator $validator
    ) {
        $this->medRepo = $medRepo;
        $this->batchRepo = $batchRepo;
        $this->saltRepo = $saltRepo;
        $this->auditRepo = $auditRepo;
        $this->validator = $validator;
    }

    /**
     * GET /api/v1/admin/medicines
     */
    public function index(Request $request): Response
    {
        $query = $request->query();

        $page = max(1, (int)($query['page'] ?? 1));
        $perPage = min(Paginator::MAX_OFFSET_PER_PAGE, max(1, (int)($query['per_page'] ?? Paginator::DEFAULT_OFFSET_PER_PAGE)));
        $sort = (string)($query['sort'] ?? 'name');
        $dir = (string)($query['dir'] ?? 'asc');

        $result = $this->medRepo->paginateAdmin($query, $page, $perPage, $sort, $dir);

        $paginated = Paginator::offset($page, $perPage, $result['total'], $result['items']);

        return ApiResponse::success(
            $paginated['data'],
            200,
            ['pagination' => $paginated['meta']]
        );
    }

    /**
     * POST /api/v1/admin/medicines
     */
    public function store(Request $request): Response
    {
        $data = $request->all();
        $validation = $this->validator->validate($data);

        if ($validation->fails()) {
            throw new ValidationException($validation->errors());
        }

        // Generate slug if omitted
        $name = trim((string)$data['name']);
        $slug = !empty($data['slug']) ? trim((string)$data['slug']) : strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $data['slug'] = $slug;

        $user = $request->getAttribute('user');
        $data['created_by'] = $user['id'] ?? null;

        $id = $this->medRepo->create($data);

        // Sync composition salts if provided
        if (!empty($data['salts']) && is_array($data['salts'])) {
            $this->medRepo->syncSalts($id, $data['salts']);
        }

        $medicine = $this->medRepo->findById($id);
        $medicine['salts'] = $this->saltRepo->getByMedicineId($id);

        $this->auditRepo->log('create', 'medicines', $id, null, $medicine, $user['id'] ?? null, $request->ip());

        return ApiResponse::success(
            $medicine,
            201,
            ['message' => function_exists('t') ? t('inventory.medicine_created') : 'Medicine created successfully.']
        );
    }

    /**
     * GET /api/v1/admin/medicines/{id}
     */
    public function show(Request $request, string $id): Response
    {
        $medicine = $this->medRepo->findById((int)$id);
        if (!$medicine) {
            throw new NotFoundException("Medicine with ID '{$id}' not found.");
        }

        $medicine['salts'] = $this->saltRepo->getByMedicineId((int)$id);
        $medicine['batches'] = $this->batchRepo->getBatchesForMedicine((int)$id);

        return ApiResponse::success($medicine);
    }

    /**
     * PUT /api/v1/admin/medicines/{id}
     */
    public function update(Request $request, string $id): Response
    {
        $intId = (int)$id;
        $existing = $this->medRepo->findById($intId);
        if (!$existing) {
            throw new NotFoundException("Medicine with ID '{$id}' not found.");
        }

        $data = $request->all();
        $validation = $this->validator->validate($data, $intId);

        if ($validation->fails()) {
            throw new ValidationException($validation->errors());
        }

        $this->medRepo->update($intId, $data);

        if (isset($data['salts']) && is_array($data['salts'])) {
            $this->medRepo->syncSalts($intId, $data['salts']);
        }

        $updated = $this->medRepo->findById($intId);
        $updated['salts'] = $this->saltRepo->getByMedicineId($intId);

        $user = $request->getAttribute('user');
        $this->auditRepo->log('update', 'medicines', $intId, $existing, $updated, $user['id'] ?? null, $request->ip());

        return ApiResponse::success(
            $updated,
            200,
            ['message' => function_exists('t') ? t('inventory.medicine_updated') : 'Medicine updated successfully.']
        );
    }

    /**
     * DELETE /api/v1/admin/medicines/{id}
     */
    public function destroy(Request $request, string $id): Response
    {
        $intId = (int)$id;
        $existing = $this->medRepo->findById($intId);
        if (!$existing) {
            throw new NotFoundException("Medicine with ID '{$id}' not found.");
        }

        $this->medRepo->softDelete($intId);

        $user = $request->getAttribute('user');
        $this->auditRepo->log('delete', 'medicines', $intId, $existing, null, $user['id'] ?? null, $request->ip());

        return ApiResponse::success(
            null,
            200,
            ['message' => function_exists('t') ? t('inventory.medicine_deleted') : 'Medicine deleted successfully.']
        );
    }
}

