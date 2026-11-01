<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Prescription;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Core\Cache;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\PrescriptionRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\PrescriptionService;

class PrescriptionUploadTest extends TestCase
{
    private Database $db;
    private PrescriptionRepository $rxRepo;
    private OrderRepository $orderRepo;
    private UserRepository $userRepo;
    private NotificationRepository $notificationRepo;
    private SettingRepository $settingRepo;
    private PrescriptionService $rxService;
    private string $tempDir;

    private string $validPngPath;
    private string $validPdfPath;
    private string $fakePhpPath;

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
            (1, 'Rahul Customer', '9876543210', 'customer'),
            (2, 'Dr. Sharma Pharmacist', '9876543211', 'pharmacist');

            INSERT INTO orders (id, order_no, user_id, address_snapshot, rx_required, rx_status)
            VALUES (101, 'MT-20261101-0001', 1, '{}', 1, 'pending');
        ");

        $this->tempDir = sys_get_temp_dir() . '/meditrack_rx_test_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);

        // 1. Create a 1x1 valid PNG file
        $this->validPngPath = $this->tempDir . '/valid.png';
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($this->validPngPath, $pngBytes);

        // 2. Create a minimal valid PDF file
        $this->validPdfPath = $this->tempDir . '/valid.pdf';
        $pdfBytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nxref\n0 2\n0000000000 65535 f\n0000000009 00000 n\ntrailer\n<< /Size 2 /Root 1 0 R >>\nstartxref\n52\n%%EOF\n";
        file_put_contents($this->validPdfPath, $pdfBytes);

        // 3. Create a malicious PHP script pretending to be an image
        $this->fakePhpPath = $this->tempDir . '/malicious.php';
        file_put_contents($this->fakePhpPath, "<?php echo 'hack'; ?>");

        $storagePath = $this->tempDir . '/storage_rx';
        $this->rxRepo = new PrescriptionRepository($this->db);
        $this->orderRepo = new OrderRepository($this->db);
        $this->userRepo = new UserRepository($this->db);
        $this->notificationRepo = new NotificationRepository($this->db);
        $this->settingRepo = new SettingRepository($this->db);

        $this->rxService = new PrescriptionService(
            $this->rxRepo,
            $this->orderRepo,
            $this->userRepo,
            $this->notificationRepo,
            $this->settingRepo,
            $this->db,
            $storagePath,
            'test-secret-key-123'
        );
    }

    protected function tearDown(): void
    {
        // Recursively remove test temp files
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

    public function testValidPrescriptionUploadStoresFileAndReturnsSignedUrl(): void
    {
        $files = [
            [
                'name' => 'my_prescription.png',
                'tmp_name' => $this->validPngPath,
                'size' => filesize($this->validPngPath),
                'error' => UPLOAD_ERR_OK,
            ],
            [
                'name' => 'report.pdf',
                'tmp_name' => $this->validPdfPath,
                'size' => filesize($this->validPdfPath),
                'error' => UPLOAD_ERR_OK,
            ],
        ];

        $res = $this->rxService->upload(1, $files, [
            'order_id' => 101,
            'doctor_name' => 'Dr. A. Verma',
            'patient_name' => 'Rahul Sharma',
        ]);

        $this->assertNotNull($res['id']);
        $this->assertSame('pending', $res['status']);
        $this->assertSame(101, (int)$res['order_id']);
        $this->assertCount(2, $res['files']);

        // Check signed URL present for each file
        foreach ($res['files'] as $f) {
            $this->assertArrayHasKey('signed_url', $f);
            $this->assertStringContainsString('signature=', $f['signed_url']);
            $this->assertStringContainsString('expires=', $f['signed_url']);
            $this->assertFileExists($f['path']);
        }

        // Pharmacist should have received notification
        $notifications = $this->notificationRepo->paginateForUser(2);
        $this->assertNotEmpty($notifications);
        $this->assertSame('rx_review', $notifications[0]['type']);
    }

    public function testRejectUploadWithNoFiles(): void
    {
        $this->expectException(ValidationException::class);
        $this->rxService->upload(1, []);
    }

    public function testRejectUploadExceedingFiveFiles(): void
    {
        $files = [];
        for ($i = 0; $i < 6; $i++) {
            $files[] = [
                'name' => "file_{$i}.png",
                'tmp_name' => $this->validPngPath,
                'size' => filesize($this->validPngPath),
                'error' => UPLOAD_ERR_OK,
            ];
        }

        $this->expectException(ValidationException::class);
        $this->rxService->upload(1, $files);
    }

    public function testRejectOversizedFileAboveFiveMegabytes(): void
    {
        $oversizedPath = $this->tempDir . '/big.png';
        // Mock a 5.2 MB file
        $fp = fopen($oversizedPath, 'wb');
        fseek($fp, (int)(5.2 * 1024 * 1024));
        fwrite($fp, "\0");
        fclose($fp);

        $files = [
            [
                'name' => 'big.png',
                'tmp_name' => $oversizedPath,
                'size' => filesize($oversizedPath),
                'error' => UPLOAD_ERR_OK,
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->rxService->upload(1, $files);
    }

    public function testRejectFakeMimeTypeWherePhpScriptDisguisedAsJpg(): void
    {
        // Name is .jpg but contents are PHP script
        $fakeJpg = $this->tempDir . '/fake.jpg';
        file_put_contents($fakeJpg, "<?php system(\$_GET['cmd']); ?>");

        $files = [
            [
                'name' => 'fake.jpg',
                'tmp_name' => $fakeJpg,
                'size' => filesize($fakeJpg),
                'error' => UPLOAD_ERR_OK,
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->rxService->upload(1, $files);
    }

    public function testRejectDirectPhpExtensionUpload(): void
    {
        $files = [
            [
                'name' => 'malicious.php',
                'tmp_name' => $this->fakePhpPath,
                'size' => filesize($this->fakePhpPath),
                'error' => UPLOAD_ERR_OK,
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->rxService->upload(1, $files);
    }

    public function testRejectDoubleExtensionPhpRename(): void
    {
        $fakeDouble = $this->tempDir . '/shell.php.png';
        file_put_contents($fakeDouble, "<?php echo 'shell'; ?>");

        $files = [
            [
                'name' => 'shell.php.png',
                'tmp_name' => $fakeDouble,
                'size' => filesize($fakeDouble),
                'error' => UPLOAD_ERR_OK,
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->rxService->upload(1, $files);
    }
}
