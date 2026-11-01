<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\PrescriptionRepository;
use MediTrack\Services\PrescriptionService;
use MediTrack\Validators\PrescriptionReviewValidator;

class PrescriptionController
{
    private PrescriptionService $prescriptionService;
    private PrescriptionRepository $prescriptionRepo;

    public function __construct(
        PrescriptionService $prescriptionService,
        PrescriptionRepository $prescriptionRepo
    ) {
        $this->prescriptionService = $prescriptionService;
        $this->prescriptionRepo = $prescriptionRepo;
    }

    public function index(Request $request): Response
    {
        $page = (int)($request->query('page') ?? 1);
        $perPage = (int)($request->query('per_page') ?? 20);
        $status = $request->query('status');
        $q = $request->query('q') ?? $request->query('search');

        $result = $this->prescriptionRepo->paginateAdmin($page, $perPage, $status, $q);

        foreach ($result['data'] as &$rx) {
            $files = $this->prescriptionRepo->getFiles((int)$rx['id']);
            foreach ($files as &$f) {
                $f['signed_url'] = $this->prescriptionService->generateSignedUrl((int)$rx['id'], (int)$f['id']);
                $f['filename'] = basename($f['path']);
            }
            $rx['files'] = $files;
        }

        return ApiResponse::success($result['data'], 200, $result['meta']);
    }

    public function show(Request $request, array $vars): Response
    {
        $id = (int)$vars['id'];
        $prescription = $this->prescriptionService->getPrescriptionWithSignedUrls($id);

        if (!$prescription) {
            return ApiResponse::notFound('Prescription not found.');
        }

        return ApiResponse::success($prescription);
    }

    public function review(Request $request, array $vars): Response
    {
        $pharmacistId = (int)$request->attribute('user_id');
        $prescriptionId = (int)$vars['id'];
        $body = $request->json();

        $validator = PrescriptionReviewValidator::validate($body);
        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $prescription = $this->prescriptionService->review($prescriptionId, $pharmacistId, $body);

        return ApiResponse::success(
            $prescription,
            200,
            ['message' => "Prescription marked as {$body['status']} successfully."]
        );
    }
}

