<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Database;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\PrescriptionRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\UserRepository;

class PrescriptionService
{
    private PrescriptionRepository $prescriptionRepo;
    private OrderRepository $orderRepo;
    private UserRepository $userRepo;
    private NotificationRepository $notificationRepo;
    private SettingRepository $settingRepo;
    private Database $db;
    private string $storagePath;
    private string $secret;

    public const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
    public const MAX_FILES = 5;

    public const ALLOWED_MIME_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
    ];

    public function __construct(
        PrescriptionRepository $prescriptionRepo,
        OrderRepository $orderRepo,
        UserRepository $userRepo,
        NotificationRepository $notificationRepo,
        SettingRepository $settingRepo,
        Database $db,
        ?string $storagePath = null,
        ?string $secret = null
    ) {
        $this->prescriptionRepo = $prescriptionRepo;
        $this->orderRepo = $orderRepo;
        $this->userRepo = $userRepo;
        $this->notificationRepo = $notificationRepo;
        $this->settingRepo = $settingRepo;
        $this->db = $db;
        $this->storagePath = $storagePath ?? dirname(__DIR__, 2) . '/storage/prescriptions';
        $this->secret = $secret ?? ($_ENV['APP_KEY'] ?? $_ENV['JWT_SECRET'] ?? 'meditrack-rx-secret-salt');

        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Upload and validate prescription files (finfo sniff, extension check, size, count cap).
     *
     * @param int $userId
     * @param array $files Normalized list of file items: [['tmp_name' => string, 'name' => string, 'size' => int, 'error' => int]]
     * @param array $data ['order_id' => ?int, 'doctor_name' => ?string, 'patient_name' => ?string]
     * @return array
     */
    public function upload(int $userId, array $files, array $data = []): array
    {
        // 1. Check file count (1 to 5)
        if (empty($files)) {
            throw new ValidationException(['files' => ['At least one prescription file is required.']]);
        }

        if (count($files) > self::MAX_FILES) {
            throw new ValidationException(['files' => ['You cannot upload more than ' . self::MAX_FILES . ' files per prescription.']]);
        }

        $orderId = !empty($data['order_id']) ? (int)$data['order_id'] : null;
        if ($orderId !== null) {
            $order = $this->orderRepo->findByIdAndUser($orderId, $userId);
            if (!$order) {
                throw new ValidationException(['order_id' => ['Order not found or does not belong to you.']]);
            }
        }

        $validatedFiles = [];
        $errors = [];

        foreach ($files as $index => $file) {
            $fileKey = "file_{$index}";
            $tmpPath = $file['tmp_name'] ?? '';
            $originalName = (string)($file['name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $error = (int)($file['error'] ?? UPLOAD_ERR_OK);

            if ($error !== UPLOAD_ERR_OK || !file_exists($tmpPath) || !is_readable($tmpPath)) {
                $errors[$fileKey][] = "File upload failed or file is unreadable: {$originalName}";
                continue;
            }

            // Check size cap (5 MB)
            if ($size > self::MAX_FILE_SIZE) {
                $errors[$fileKey][] = "File {$originalName} exceeds maximum allowed size of 5 MB.";
                continue;
            }

            // Check extension (must not contain dangerous extensions like .php, .exe)
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $dangerousExts = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'svg'];
            if (in_array($ext, $dangerousExts, true) || str_contains($originalName, '.php')) {
                $errors[$fileKey][] = "File {$originalName} has an invalid or forbidden extension.";
                continue;
            }

            // Use finfo for server-side MIME sniffing
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmpPath);

            if (!array_key_exists($mime, self::ALLOWED_MIME_TYPES)) {
                $errors[$fileKey][] = "File {$originalName} has an invalid file type ({$mime}). Only JPG, PNG, WEBP, and PDF files are allowed.";
                continue;
            }

            $allowedExtensions = self::ALLOWED_MIME_TYPES[$mime];
            if (!in_array($ext, $allowedExtensions, true)) {
                $errors[$fileKey][] = "File extension (.{$ext}) does not match actual content type ({$mime}) for {$originalName}.";
                continue;
            }

            $validatedFiles[] = [
                'tmp_path' => $tmpPath,
                'original_name' => $originalName,
                'mime' => $mime,
                'ext' => $ext,
                'size_kb' => (int)ceil($size / 1024),
            ];
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // 2. Move files to private storage outside web root with random safe names
        $storedFiles = [];
        foreach ($validatedFiles as $vFile) {
            $randomName = bin2hex(random_bytes(16)) . '.' . $vFile['ext'];
            $destPath = $this->storagePath . '/' . $randomName;

            // Support both uploaded files and local scratch files in tests
            if (is_uploaded_file($vFile['tmp_path'])) {
                move_uploaded_file($vFile['tmp_path'], $destPath);
            } else {
                copy($vFile['tmp_path'], $destPath);
            }

            $storedFiles[] = [
                'path' => $destPath,
                'mime' => $vFile['mime'],
                'size_kb' => $vFile['size_kb'],
            ];
        }

        // 3. Save prescription record and file entries
        $prescriptionId = $this->prescriptionRepo->create(
            $userId,
            $orderId,
            $data['doctor_name'] ?? null,
            $data['patient_name'] ?? null,
            $storedFiles
        );

        // If order linked, ensure order's rx_status is set to pending
        if ($orderId !== null) {
            $this->db->execute(
                'UPDATE orders SET rx_status = "pending", updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$orderId]
            );
        }

        // 4. Notify all registered pharmacists & admins of new Rx
        $pharmacists = $this->db->fetchAll(
            'SELECT id FROM users WHERE role IN ("pharmacist", "admin") AND is_active = 1'
        );
        foreach ($pharmacists as $ph) {
            $this->notificationRepo->create(
                (int)$ph['id'],
                'rx_review',
                'New Prescription Uploaded',
                "Customer has uploaded a new prescription (#{$prescriptionId}) for review.",
                ['prescription_id' => $prescriptionId, 'order_id' => $orderId]
            );
        }

        return $this->getPrescriptionWithSignedUrls($prescriptionId);
    }

    /**
     * Generate short-lived HMAC signed URL for secure private file download.
     */
    public function generateSignedUrl(int $prescriptionId, int $fileId, int $ttlSeconds = 300): string
    {
        $expires = time() + $ttlSeconds;
        $signature = hash_hmac('sha256', "rx:{$prescriptionId}:file:{$fileId}:{$expires}", $this->secret);

        return "/api/v1/prescriptions/{$prescriptionId}/files/{$fileId}?expires={$expires}&signature={$signature}";
    }

    /**
     * Verify HMAC signature and expiration on a signed file URL.
     */
    public function verifySignedUrl(int $prescriptionId, int $fileId, int $expires, string $signature): bool
    {
        if ($expires < time()) {
            return false; // Expired
        }

        $expected = hash_hmac('sha256', "rx:{$prescriptionId}:file:{$fileId}:{$expires}", $this->secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Retrieve prescription file content for serving, verifying ownership and signed URL.
     */
    public function getFileForDownload(
        int $prescriptionId,
        int $fileId,
        int $expires,
        string $signature,
        ?array $currentUser = null
    ): array {
        // 1. Verify signed URL
        if (!$this->verifySignedUrl($prescriptionId, $fileId, $expires, $signature)) {
            throw new ForbiddenException('Invalid or expired download link.', 'EXPIRED_SIGNED_URL');
        }

        $prescription = $this->prescriptionRepo->findById($prescriptionId);
        if (!$prescription) {
            throw new ForbiddenException('Prescription not found.');
        }

        // 2. Ownership check
        if ($currentUser !== null) {
            $isOwner = (int)$currentUser['id'] === (int)$prescription['user_id'];
            $isAdminOrPharmacist = in_array($currentUser['role'] ?? '', ['admin', 'pharmacist'], true);

            if (!$isOwner && !$isAdminOrPharmacist) {
                throw new ForbiddenException('You do not have permission to access this prescription file.');
            }
        }

        $file = $this->prescriptionRepo->getFileById($fileId);
        if (!$file || (int)$file['prescription_id'] !== $prescriptionId || !file_exists($file['path'])) {
            throw new ForbiddenException('Prescription file not found on disk.');
        }

        return $file;
    }

    /**
     * Pharmacist reviews and approves/rejects a prescription.
     * Stamps pharmacist registration number and unblocks/cancels order.
     */
    public function review(int $prescriptionId, int $pharmacistId, array $data): array
    {
        $prescription = $this->prescriptionRepo->findById($prescriptionId);
        if (!$prescription) {
            throw new ValidationException(['prescription' => ['Prescription not found.']]);
        }

        $pharmacist = $this->userRepo->findById($pharmacistId);
        if (!$pharmacist || !in_array($pharmacist['role'], ['pharmacist', 'admin'], true)) {
            throw new ForbiddenException('Only a registered pharmacist or admin can review prescriptions.');
        }

        $status = strtolower(trim($data['status'] ?? ''));
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new ValidationException(['status' => ['Status must be either approved or rejected.']]);
        }

        $rejectReason = $status === 'rejected' ? ($data['reject_reason'] ?? 'Prescription does not meet medical standards') : null;
        $validUntil = $status === 'approved' ? ($data['valid_until'] ?? date('Y-m-d', strtotime('+90 days'))) : null;

        $regNo = $pharmacist['pharmacist_reg_no'] ?? 'REG-PHARM-DEFAULT';
        $pharmacistStamp = "{$pharmacist['name']} (Reg: {$regNo})";

        $this->db->transaction(function () use (
            $prescriptionId,
            $prescription,
            $status,
            $rejectReason,
            $pharmacistId,
            $validUntil,
            $pharmacistStamp,
            $pharmacist
        ) {
            // Update prescription record
            $this->prescriptionRepo->updateStatus(
                $prescriptionId,
                $status,
                $rejectReason,
                $pharmacistId,
                $validUntil
            );

            // If order linked, update order's rx_status and stamp pharmacist details
            $orderId = !empty($prescription['order_id']) ? (int)$prescription['order_id'] : null;
            if ($orderId !== null) {
                $orderRxStatus = $status === 'approved' ? 'approved' : 'rejected';
                $this->db->execute(
                    'UPDATE orders SET rx_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [$orderRxStatus, $orderId]
                );

                $note = $status === 'approved'
                    ? "Prescription approved by pharmacist {$pharmacistStamp}"
                    : "Prescription rejected: {$rejectReason} (reviewed by {$pharmacist['name']})";

                $this->db->execute(
                    'INSERT INTO order_status_history (order_id, from_status, to_status, note, changed_by, created_at)
                     VALUES (?, (SELECT status FROM orders WHERE id = ?), (SELECT status FROM orders WHERE id = ?), ?, ?, CURRENT_TIMESTAMP)',
                    [$orderId, $orderId, $orderId, $note, $pharmacistId]
                );
            }

            // Notify customer of review result
            $customerId = (int)$prescription['user_id'];
            if ($status === 'approved') {
                $this->notificationRepo->create(
                    $customerId,
                    'rx_review',
                    'Prescription Approved',
                    "Your prescription #{$prescriptionId} was approved by pharmacist {$pharmacist['name']} and is valid until {$validUntil}.",
                    ['prescription_id' => $prescriptionId, 'status' => 'approved', 'order_id' => $orderId]
                );
            } else {
                $this->notificationRepo->create(
                    $customerId,
                    'rx_review',
                    'Prescription Rejected',
                    "Your prescription #{$prescriptionId} was rejected. Reason: {$rejectReason}",
                    ['prescription_id' => $prescriptionId, 'status' => 'rejected', 'reject_reason' => $rejectReason, 'order_id' => $orderId]
                );
            }
        });

        return $this->getPrescriptionWithSignedUrls($prescriptionId);
    }

    /**
     * Check if user has an approved valid prescription that can be reused for an order.
     */
    public function getActiveReusablePrescription(int $userId): ?array
    {
        $reuseEnabled = (bool)($this->settingRepo->get('rx_reuse_enabled') ?? 1);
        if (!$reuseEnabled) {
            return null;
        }

        $activeRx = $this->prescriptionRepo->findActiveApprovedByUser($userId);
        if ($activeRx) {
            return $this->getPrescriptionWithSignedUrls((int)$activeRx['id']);
        }

        return null;
    }

    /**
     * Return prescription payload enriched with signed download URLs for its files.
     */
    public function getPrescriptionWithSignedUrls(int $prescriptionId): ?array
    {
        $prescription = $this->prescriptionRepo->findById($prescriptionId);
        if (!$prescription) {
            return null;
        }

        $files = $this->prescriptionRepo->getFiles($prescriptionId);
        foreach ($files as &$file) {
            $file['signed_url'] = $this->generateSignedUrl($prescriptionId, (int)$file['id']);
            $file['filename'] = basename($file['path']);
        }

        $prescription['files'] = $files;
        return $prescription;
    }
}

