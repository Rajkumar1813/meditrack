<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Prescription;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\PrescriptionRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\PrescriptionService;

class SignedUrlAndSecurityTest extends TestCase
{
    private Database $db;
    private PrescriptionRepository $rxRepo;
    private OrderRepository $orderRepo;
    private UserRepository $userRepo;
    private NotificationRepository $notificationRepo;
    private SettingRepository $settingRepo;
    private PrescriptionService $rxService;
    private string $tempDir;
    private int $prescriptionId;
    private int $fileId;

    protected function setUp(): void
    {
        $this->db = new Database([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $pdo = $this->db->pdo();
        $pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                phone TEXT NULL,
                email TEXT NULL,
                name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'customer',
                is_active INTEGER DEFAULT 1,
                is_blocked INTEGER DEFAULT 0,
                pharmacist_reg_no TEXT NULL,
                deleted_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL
            );

            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_no TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL,
                address_snapshot TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                rx_required INTEGER NOT NULL DEFAULT 0,
                rx_status TEXT NOT NULL DEFAULT 'not_required',
                payment_method TEXT NOT NULL DEFAULT 'cod',
                payment_status TEXT NOT NULL DEFAULT 'unpaid',
                subtotal NUMERIC NOT NULL DEFAULT 0,
                total NUMERIC NOT NULL DEFAULT 0,
                idempotency_key TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE prescriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                order_id INTEGER NULL,
                doctor_name TEXT NULL,
                patient_name TEXT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                reject_reason TEXT NULL,
                reviewed_by INTEGER NULL,
                reviewed_at TIMESTAMP NULL,
                valid_until TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE prescription_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                prescription_id INTEGER NOT NULL,
                path TEXT NOT NULL,
                mime TEXT NOT NULL,
                size_kb INTEGER NOT NULL
            );

            CREATE TABLE settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                `key` TEXT NOT NULL UNIQUE,
                value TEXT NOT NULL
            );

            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                title TEXT NOT NULL,
                body TEXT NOT NULL,
                data TEXT NULL,
                read_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL
            );

            INSERT INTO users (id, name, phone, role) VALUES
            (1, 'Customer One', '9876543210', 'customer'),
            (2, 'Customer Two', '9876543211', 'customer'),
            (3, 'Pharmacist Ramesh', '9876543212', 'pharmacist');
        ");

        $this->tempDir = sys_get_temp_dir() . '/meditrack_rx_sec_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);

        // Valid PNG
        $pngPath = $this->tempDir . '/test.png';
        file_put_contents($pngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $this->rxRepo = new PrescriptionRepository($this->db);
        $this->orderRepo = new OrderRepository($this->db);
        $this->userRepo = new UserRepository($this->db);
        $this->notificationRepo = new NotificationRepository($this->db);
        $this->settingRepo = new SettingRepository($this->db);

        $storagePath = $this->tempDir . '/storage';
        $this->rxService = new PrescriptionService(
            $this->rxRepo,
            $this->orderRepo,
            $this->userRepo,
            $this->notificationRepo,
            $this->settingRepo,
            $this->db,
            $storagePath,
            'secret-signing-key-xyz'
        );

        // Upload prescription for user 1
        $res = $this->rxService->upload(1, [
            [
                'name' => 'rx.png',
                'tmp_name' => $pngPath,
                'size' => filesize($pngPath),
                'error' => UPLOAD_ERR_OK,
            ],
        ]);

        $this->prescriptionId = (int)$res['id'];
        $this->fileId = (int)$res['files'][0]['id'];
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function testValidSignedUrlAllowsDownloadForOwner(): void
    {
        $signedUrl = $this->rxService->generateSignedUrl($this->prescriptionId, $this->fileId, 300);
        parse_str(parse_url($signedUrl, PHP_URL_QUERY), $query);

        $expires = (int)$query['expires'];
        $sig = (string)$query['signature'];

        $user1 = ['id' => 1, 'role' => 'customer'];
        $file = $this->rxService->getFileForDownload($this->prescriptionId, $this->fileId, $expires, $sig, $user1);

        $this->assertNotNull($file);
        $this->assertSame('image/png', $file['mime']);
        $this->assertFileExists($file['path']);
    }

    public function testExpiredSignedUrlThrowsForbidden(): void
    {
        // URL expired 10 seconds ago
        $expiredTime = time() - 10;
        $sig = hash_hmac('sha256', "rx:{$this->prescriptionId}:file:{$this->fileId}:{$expiredTime}", 'secret-signing-key-xyz');

        $user1 = ['id' => 1, 'role' => 'customer'];

        $this->expectException(ForbiddenException::class);
        $this->rxService->getFileForDownload($this->prescriptionId, $this->fileId, $expiredTime, $sig, $user1);
    }

    public function testTamperedSignatureThrowsForbidden(): void
    {
        $expires = time() + 300;
        $tamperedSig = 'bad-signature-1234567890';

        $user1 = ['id' => 1, 'role' => 'customer'];

        $this->expectException(ForbiddenException::class);
        $this->rxService->getFileForDownload($this->prescriptionId, $this->fileId, $expires, $tamperedSig, $user1);
    }

    public function testUserBCannotDownloadUserAFilesEvenWithValidSignature(): void
    {
        // User 2 tries to download User 1's file
        $signedUrl = $this->rxService->generateSignedUrl($this->prescriptionId, $this->fileId, 300);
        parse_str(parse_url($signedUrl, PHP_URL_QUERY), $query);

        $expires = (int)$query['expires'];
        $sig = (string)$query['signature'];

        $user2 = ['id' => 2, 'role' => 'customer']; // User B

        $this->expectException(ForbiddenException::class);
        $this->rxService->getFileForDownload($this->prescriptionId, $this->fileId, $expires, $sig, $user2);
    }

    public function testPharmacistCanDownloadCustomerFilesWithValidSignature(): void
    {
        $signedUrl = $this->rxService->generateSignedUrl($this->prescriptionId, $this->fileId, 300);
        parse_str(parse_url($signedUrl, PHP_URL_QUERY), $query);

        $expires = (int)$query['expires'];
        $sig = (string)$query['signature'];

        $pharmacist = ['id' => 3, 'role' => 'pharmacist']; // Pharmacist
        $file = $this->rxService->getFileForDownload($this->prescriptionId, $this->fileId, $expires, $sig, $pharmacist);

        $this->assertNotNull($file);
        $this->assertSame('image/png', $file['mime']);
    }
}
