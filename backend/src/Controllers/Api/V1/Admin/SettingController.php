<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\ApiResponse;
use MediTrack\Core\Exceptions\NotFoundException;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Validators\SettingValidator;
use MediTrack\Services\BackupService;

class SettingController
{
    private SettingRepository $settingRepo;
    private AuditLogRepository $auditRepo;
    private SettingValidator $validator;
    private BackupService $backupService;

    public function __construct(
        SettingRepository $settingRepo,
        AuditLogRepository $auditRepo,
        SettingValidator $validator,
        BackupService $backupService
    ) {
        $this->settingRepo = $settingRepo;
        $this->auditRepo = $auditRepo;
        $this->validator = $validator;
        $this->backupService = $backupService;
    }

    /**
     * GET /api/v1/admin/settings
     */
    public function show(Request $request): Response
    {
        $all = $this->settingRepo->all();
        return ApiResponse::success([
            'shop_name' => $all['shop_name'] ?? 'Sharma Medical Store',
            'owner_name' => $all['owner_name'] ?? 'Ramesh Sharma',
            'low_stock_threshold' => (int)($all['low_stock_threshold'] ?? 10),
            'gst_number' => $all['gst_number'] ?? null,
            'drug_license_no' => $all['drug_license_no'] ?? null,
            'delivery_fee' => (float)($all['delivery_fee'] ?? 40.00),
        ]);
    }

    /**
     * PUT /api/v1/admin/settings
     */
    public function update(Request $request): Response
    {
        $data = $request->all();
        $validation = $this->validator->validate($data);
        if ($validation->fails()) {
            throw new ValidationException($validation->errors());
        }

        $oldSettings = $this->settingRepo->all();

        $allowedKeys = ['shop_name', 'owner_name', 'low_stock_threshold', 'gst_number', 'drug_license_no', 'delivery_fee'];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $data)) {
                $this->settingRepo->set($key, (string)$data[$key]);
            }
        }

        $newSettings = $this->settingRepo->all();

        $user = $request->getAttribute('user');
        $this->auditRepo->log('update', 'settings', null, $oldSettings, $newSettings, $user['id'] ?? null, $request->ip());

        return ApiResponse::success(
            $newSettings,
            200,
            ['message' => function_exists('t') ? t('inventory.settings_saved') : 'Settings saved successfully.']
        );
    }

    /**
     * POST /api/v1/admin/backup
     */
    public function createBackup(Request $request): Response
    {
        $backupResult = $this->backupService->createBackup();

        $user = $request->getAttribute('user');
        $this->auditRepo->log('create_backup', 'system', null, null, ['file' => $backupResult['file_name']], $user['id'] ?? null, $request->ip());

        return ApiResponse::success(
            $backupResult,
            201,
            ['message' => 'Backup created successfully. Valid for 15 minutes.']
        );
    }

    /**
     * GET /api/v1/admin/backup/download?token=...
     */
    public function downloadBackup(Request $request): Response
    {
        $token = (string)($request->query('token') ?? '');
        if ($token === '') {
            throw new ValidationException(['token' => ['Download token is missing.']]);
        }

        $filePath = $this->backupService->getBackupFileForToken($token);
        if (!$filePath) {
            throw new NotFoundException('Download token is expired or invalid.');
        }

        $content = file_get_contents($filePath);
        $fileName = basename($filePath);

        return new Response(
            $content,
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]
        );
    }
}

