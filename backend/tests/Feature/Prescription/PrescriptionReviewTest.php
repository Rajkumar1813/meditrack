<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Prescription;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Core\Cache;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Repositories\AddressRepository;
use MediTrack\Repositories\CartRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\PaymentRepository;
use MediTrack\Repositories\PrescriptionRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\StockMovementRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\CartService;
use MediTrack\Services\OrderService;
use MediTrack\Services\PrescriptionService;
use MediTrack\Services\StockService;

class PrescriptionReviewTest extends TestCase
{
    private Database $db;
    private PrescriptionRepository $rxRepo;
    private OrderRepository $orderRepo;
    private UserRepository $userRepo;
    private NotificationRepository $notificationRepo;
    private SettingRepository $settingRepo;
    private PrescriptionService $rxService;
    private OrderService $orderService;
    private string $tempDir;
    private int $prescriptionId;
    private int $orderId;

    protected function setUp(): void
    {
        $this->db = new Database([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $pdo = $this->db->pdo();
        $pdo->exec("
            CREATE TABLE settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                `key` TEXT NOT NULL UNIQUE,
                value TEXT NOT NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL
            );

            CREATE TABLE manufacturers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );

            INSERT INTO categories (id, name, slug) VALUES (1, 'Tablet', 'tablet');

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

            CREATE TABLE medicines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                generic_name TEXT NULL,
                manufacturer_id INTEGER NULL,
                category_id INTEGER NOT NULL DEFAULT 1,
                schedule TEXT NOT NULL DEFAULT 'OTC',
                requires_rx INTEGER NOT NULL DEFAULT 0,
                pack_size TEXT NULL,
                gst_percent NUMERIC DEFAULT 12.00,
                image TEXT NULL,
                is_active INTEGER DEFAULT 1,
                deleted_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE batches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                medicine_id INTEGER NOT NULL,
                batch_number TEXT NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 0,
                reserved_quantity INTEGER NOT NULL DEFAULT 0,
                purchase_price NUMERIC NOT NULL,
                mrp NUMERIC NOT NULL,
                selling_price NUMERIC NOT NULL,
                mfg_date TEXT NOT NULL,
                exp_date TEXT NOT NULL,
                disposal_status TEXT NOT NULL DEFAULT 'none',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE stock_movements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                batch_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                qty_change INTEGER NOT NULL,
                ref_type TEXT NULL,
                ref_id INTEGER NULL,
                user_id INTEGER NULL,
                created_at TIMESTAMP NULL
            );

            CREATE TABLE carts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                coupon_code TEXT NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE cart_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cart_id INTEGER NOT NULL,
                medicine_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                UNIQUE (cart_id, medicine_id)
            );

            CREATE TABLE addresses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                label TEXT NULL,
                recipient_name TEXT NOT NULL,
                phone TEXT NOT NULL,
                line1 TEXT NOT NULL,
                line2 TEXT NULL,
                landmark TEXT NULL,
                city TEXT NOT NULL,
                state TEXT NOT NULL,
                pincode TEXT NOT NULL,
                lat NUMERIC NULL,
                lng NUMERIC NULL,
                is_default INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
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
                discount NUMERIC NOT NULL DEFAULT 0,
                gst_total NUMERIC NOT NULL DEFAULT 0,
                delivery_fee NUMERIC NOT NULL DEFAULT 0,
                total NUMERIC NOT NULL DEFAULT 0,
                courier TEXT NULL,
                tracking_no TEXT NULL,
                cancel_reason TEXT NULL,
                cancelled_by INTEGER NULL,
                idempotency_key TEXT NULL,
                confirmed_at TIMESTAMP NULL,
                packed_at TIMESTAMP NULL,
                shipped_at TIMESTAMP NULL,
                delivered_at TIMESTAMP NULL,
                cancelled_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                medicine_id INTEGER NOT NULL,
                name_snapshot TEXT NOT NULL,
                quantity INTEGER NOT NULL,
                unit_price NUMERIC NOT NULL,
                mrp NUMERIC NOT NULL,
                gst_percent NUMERIC NOT NULL DEFAULT 12.00,
                line_total NUMERIC NOT NULL,
                requires_rx INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE order_item_batches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_item_id INTEGER NOT NULL,
                batch_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL
            );

            CREATE TABLE order_status_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                from_status TEXT NULL,
                to_status TEXT NOT NULL,
                note TEXT NULL,
                changed_by INTEGER NULL,
                created_at TIMESTAMP NULL
            );

            CREATE TABLE payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                gateway TEXT NOT NULL,
                gateway_order_id TEXT NULL,
                gateway_payment_id TEXT NULL,
                method TEXT NULL,
                amount NUMERIC NOT NULL,
                status TEXT NOT NULL DEFAULT 'created',
                raw_response TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE refunds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payment_id INTEGER NOT NULL,
                amount NUMERIC NOT NULL,
                gateway_refund_id TEXT NULL,
                reason TEXT NULL,
                status TEXT NOT NULL DEFAULT 'initiated',
                created_at TIMESTAMP NULL
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

            INSERT INTO settings (`key`, value) VALUES ('delivery_fee', '50.00'), ('rx_reuse_enabled', '1');

            INSERT INTO users (id, name, phone, role, pharmacist_reg_no) VALUES
            (1, 'Customer Rajesh', '9876543210', 'customer', NULL),
            (2, 'Pharmacist Ramesh', '9876543211', 'pharmacist', 'PHARM-DELHI-98421');

            -- Rx Medicine
            INSERT INTO medicines (id, name, slug, schedule, requires_rx, gst_percent)
            VALUES (1, 'Amoxyclav 625', 'amoxyclav-625', 'H', 1, 12.00);

            INSERT INTO batches (id, medicine_id, batch_number, quantity, reserved_quantity, purchase_price, mrp, selling_price, mfg_date, exp_date)
            VALUES (1, 1, 'AMX-001', 20, 0, 100.00, 150.00, 130.00, '2025-01-01', '2027-12-31');

            INSERT INTO addresses (id, user_id, recipient_name, phone, line1, city, state, pincode, is_default)
            VALUES (1, 1, 'Customer Rajesh', '9876543210', 'Line 1', 'Delhi', 'Delhi', '110001', 1);
        ");

        $this->tempDir = sys_get_temp_dir() . '/meditrack_rx_rev_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
        $pngPath = $this->tempDir . '/rx.png';
        file_put_contents($pngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $cache = new Cache(null);
        $this->rxRepo = new PrescriptionRepository($this->db);
        $this->orderRepo = new OrderRepository($this->db);
        $this->userRepo = new UserRepository($this->db);
        $this->notificationRepo = new NotificationRepository($this->db);
        $this->settingRepo = new SettingRepository($this->db);
        $this->movementRepo = new StockMovementRepository($this->db);
        $this->stockService = new StockService($this->db, $cache, $this->movementRepo);
        $this->cartRepo = new CartRepository($this->db);
        $this->cartService = new CartService($this->cartRepo, new MedicineRepository($this->db, $cache), $this->settingRepo);
        $this->orderService = new OrderService(
            $this->db,
            $this->orderRepo,
            $this->cartService,
            new AddressRepository($this->db),
            $this->stockService,
            new PaymentRepository($this->db)
        );

        $storagePath = $this->tempDir . '/storage';
        $this->rxService = new PrescriptionService(
            $this->rxRepo,
            $this->orderRepo,
            $this->userRepo,
            $this->notificationRepo,
            $this->settingRepo,
            $this->db,
            $storagePath,
            'secret-key-review'
        );

        // 1. Customer creates an order with Rx item
        $this->cartService->addItem(1, 1, 1);
        $order = $this->orderService->createOrder(1, ['address_id' => 1, 'payment_method' => 'cod']);
        $this->orderId = (int)$order['id'];

        // 2. Customer uploads prescription linked to order
        $rx = $this->rxService->upload(1, [
            [
                'name' => 'amoxyclav_rx.png',
                'tmp_name' => $pngPath,
                'size' => filesize($pngPath),
                'error' => UPLOAD_ERR_OK,
            ],
        ], ['order_id' => $this->orderId]);
        $this->prescriptionId = (int)$rx['id'];
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

    public function testPharmacistApprovalStampsRegNoAndUnblocksOrderConfirmation(): void
    {
        // Before approval, order confirmation MUST fail because rx_status is pending
        try {
            $this->orderService->transition($this->orderId, 'confirmed', 1);
            $this->fail('Expected confirmation to fail before prescription approval.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('prescription', $e->getErrors());
        }

        // Pharmacist reviews and approves
        $reviewed = $this->rxService->review($this->prescriptionId, 2, [
            'status' => 'approved',
            'valid_until' => date('Y-m-d', strtotime('+60 days')),
        ]);

        $this->assertSame('approved', $reviewed['status']);
        $this->assertSame(2, (int)$reviewed['reviewed_by']);
        $this->assertNotNull($reviewed['reviewed_at']);

        // Check order rx_status is updated to 'approved'
        $orderAfter = $this->orderRepo->findById($this->orderId);
        $this->assertSame('approved', $orderAfter['rx_status']);

        // Check pharmacist stamp in order history
        $history = $this->orderRepo->getStatusHistory($this->orderId);
        $lastHistory = end($history);
        $this->assertStringContainsString('PHARM-DELHI-98421', $lastHistory['note']);
        $this->assertStringContainsString('Pharmacist Ramesh', $lastHistory['note']);

        // Order confirmation is now UNBLOCKED!
        $confirmedOrder = $this->orderService->transition($this->orderId, 'confirmed', 2, 'Confirmed post-Rx verification');
        $this->assertSame('confirmed', $confirmedOrder['status']);

        // Customer was notified
        $custNotifs = $this->notificationRepo->paginateForUser(1);
        $this->assertSame('Prescription Approved', $custNotifs[0]['title']);
    }

    public function testPharmacistRejectionNotifiesCustomerAndUpdatesOrder(): void
    {
        $reviewed = $this->rxService->review($this->prescriptionId, 2, [
            'status' => 'rejected',
            'reject_reason' => 'Doctor signature missing on the prescription.',
        ]);

        $this->assertSame('rejected', $reviewed['status']);
        $this->assertSame('Doctor signature missing on the prescription.', $reviewed['reject_reason']);

        // Order rx_status updated to rejected
        $order = $this->orderRepo->findById($this->orderId);
        $this->assertSame('rejected', $order['rx_status']);

        // Customer notified with rejection reason
        $custNotifs = $this->notificationRepo->paginateForUser(1);
        $this->assertSame('Prescription Rejected', $custNotifs[0]['title']);
        $this->assertStringContainsString('Doctor signature missing', $custNotifs[0]['body']);
    }

    public function testNonPharmacistCannotReviewPrescription(): void
    {
        $this->expectException(ForbiddenException::class);
        // User 1 (customer) tries to review prescription
        $this->rxService->review($this->prescriptionId, 1, ['status' => 'approved']);
    }

    public function testApprovedPrescriptionReuseSetting(): void
    {
        // 1. Approve prescription with valid_until in future
        $futureDate = date('Y-m-d', strtotime('+30 days'));
        $this->rxService->review($this->prescriptionId, 2, [
            'status' => 'approved',
            'valid_until' => $futureDate,
        ]);

        // When reuse setting is ON (default 1)
        $reusable = $this->rxService->getActiveReusablePrescription(1);
        $this->assertNotNull($reusable);
        $this->assertSame($this->prescriptionId, (int)$reusable['id']);
        $this->assertSame('approved', $reusable['status']);

        // Turn OFF reuse setting
        $this->settingRepo->set('rx_reuse_enabled', '0');
        $reusableAfterOff = $this->rxService->getActiveReusablePrescription(1);
        $this->assertNull($reusableAfterOff);
    }
}
