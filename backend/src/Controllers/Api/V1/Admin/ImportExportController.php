<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Services\CsvImportExportService;

class ImportExportController
{
    private CsvImportExportService $csvService;

    public function __construct(CsvImportExportService $csvService)
    {
        $this->csvService = $csvService;
    }

    /**
     * GET /api/v1/admin/medicines/export
     */
    public function export(Request $request): Response
    {
        $csvContent = $this->csvService->export();
        $date = date('Y-m-d');
        $filename = "meditrack_inventory_{$date}.csv";

        return new Response(
            $csvContent,
            200,
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]
        );
    }

    /**
     * POST /api/v1/admin/medicines/import/preview
     */
    public function preview(Request $request): Response
    {
        // Support CSV raw string or uploaded files
        $csvContent = $request->input('csv');
        if (empty($csvContent)) {
            // Check php://input or body
            $body = $request->all();
            $csvContent = $body['csv'] ?? $body['content'] ?? null;
        }

        if (empty($csvContent) && isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $csvContent = file_get_contents($_FILES['file']['tmp_name']);
        }

        if (empty($csvContent)) {
            throw new ValidationException(['file' => ['Please upload a valid CSV file or provide CSV text content.']]);
        }

        $report = $this->csvService->preview((string)$csvContent);

        return ApiResponse::success($report);
    }

    /**
     * POST /api/v1/admin/medicines/import/commit
     */
    public function commit(Request $request): Response
    {
        $token = (string)$request->input('import_token');
        if (empty($token)) {
            throw new ValidationException(['import_token' => ['Import token is required.']]);
        }

        $onDuplicate = (string)($request->input('on_duplicate') ?? 'skip');
        $onDuplicate = in_array($onDuplicate, ['skip', 'update'], true) ? $onDuplicate : 'skip';

        $user = $request->getAttribute('user');
        $userId = $user['id'] ?? null;

        $result = $this->csvService->commit($token, $onDuplicate, $userId);

        return ApiResponse::success(
            $result,
            200,
            ['message' => "Import completed: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped."]
        );
    }
}

