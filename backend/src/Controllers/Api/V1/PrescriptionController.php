<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Policies\OwnershipPolicy;
use MediTrack\Repositories\PrescriptionRepository;
use MediTrack\Services\PrescriptionService;

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

    public function store(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $rawFiles = $request->files();

        $normalizedFiles = $this->normalizeFiles($rawFiles);

        $data = [
            'order_id' => $request->body('order_id'),
            'doctor_name' => $request->body('doctor_name'),
            'patient_name' => $request->body('patient_name'),
        ];

        $prescription = $this->prescriptionService->upload($userId, $normalizedFiles, $data);

        return ApiResponse::success($prescription, 201, ['message' => 'Prescription uploaded successfully.']);
    }

    public function index(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $page = (int)($request->query('page') ?? 1);
        $perPage = (int)($request->query('per_page') ?? 20);

        $result = $this->prescriptionRepo->paginateForUser($userId, $page, $perPage);

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
        $currentUser = $request->attribute('user');
        $id = (int)$vars['id'];

        $prescription = $this->prescriptionService->getPrescriptionWithSignedUrls($id);
        if (!$prescription) {
            return ApiResponse::notFound('Prescription not found.');
        }

        if (!OwnershipPolicy::canAccessPrescription($currentUser, $prescription)) {
            return ApiResponse::forbidden('You do not have permission to view this prescription.');
        }

        return ApiResponse::success($prescription);
    }

    public function downloadFile(Request $request, array $vars): Response
    {
        $prescriptionId = (int)$vars['id'];
        $fileId = (int)$vars['file_id'];
        $expires = (int)($request->query('expires') ?? 0);
        $signature = (string)($request->query('signature') ?? '');
        $currentUser = $request->attribute('user');

        try {
            $file = $this->prescriptionService->getFileForDownload(
                $prescriptionId,
                $fileId,
                $expires,
                $signature,
                $currentUser
            );
        } catch (ForbiddenException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }

        $content = file_get_contents($file['path']);
        $filename = basename($file['path']);

        return new Response($content, 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function reusable(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $activeRx = $this->prescriptionService->getActiveReusablePrescription($userId);

        if (!$activeRx) {
            return ApiResponse::success(null, 200, ['reusable' => false]);
        }

        return ApiResponse::success($activeRx, 200, ['reusable' => true]);
    }

    /**
     * Normalize $_FILES array structure to uniform list of file descriptors.
     */
    private function normalizeFiles(array $rawFiles): array
    {
        $normalized = [];

        foreach ($rawFiles as $key => $fileData) {
            if (empty($fileData['tmp_name'])) {
                continue;
            }

            if (is_array($fileData['tmp_name'])) {
                // Multi-file input e.g. files[]
                $count = count($fileData['tmp_name']);
                for ($i = 0; $i < $count; $i++) {
                    if (!empty($fileData['tmp_name'][$i])) {
                        $normalized[] = [
                            'name' => $fileData['name'][$i] ?? '',
                            'tmp_name' => $fileData['tmp_name'][$i],
                            'size' => $fileData['size'][$i] ?? 0,
                            'error' => $fileData['error'][$i] ?? UPLOAD_ERR_OK,
                        ];
                    }
                }
            } else {
                // Single file
                $normalized[] = [
                    'name' => $fileData['name'] ?? '',
                    'tmp_name' => $fileData['tmp_name'],
                    'size' => $fileData['size'] ?? 0,
                    'error' => $fileData['error'] ?? UPLOAD_ERR_OK,
                ];
            }
        }

        return $normalized;
    }
}

