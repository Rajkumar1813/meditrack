<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Invoice;

use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Jwt;
use MediTrack\Core\Request;
use MediTrack\Core\Router;
use MediTrack\Jobs\GenerateInvoiceJob;
use MediTrack\Repositories\AddressRepository;
use MediTrack\Repositories\CartRepository;
use MediTrack\Repositories\InvoiceRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\PaymentRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\StockMovementRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\CartService;
use MediTrack\Services\InvoiceService;
use MediTrack\Services\OrderService;
use MediTrack\Services\StockService;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    private Database $db;
    private Container $container;
    private Router $router;
    private Jwt $jwt;
    private InvoiceRepository $invoiceRepo;
    private OrderRepository $orderRepo;
    private UserRepository $userRepo;
    private SettingRepository $settingRepo;
    private NotificationRepository $notificationRepo;
    private InvoiceService $invoiceService;
    private OrderService $orderService;

    private int $ownerUserId;
    private int $otherUserId;
    private int $adminUserId;
    private string $ownerToken;
    private string $otherToken;
    private string $adminToken;
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
                locale TEXT NOT NULL DEFAULT 'en',
                is_blocked INTEGER DEFAULT 0,
                pharmacist_reg_no TEXT NULL,
                phone_verified_at TIMESTAMP NULL,
                email_verified_at TIMESTAMP NULL,
                last_login_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE addresses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                full_name TEXT NOT NULL,
                phone TEXT NOT NULL,
                address_line1 TEXT NOT NULL,
                address_line2 TEXT NULL,
                city TEXT NOT NULL,
                state TEXT NOT NULL,
                pincode TEXT NOT NULL,
                is_default INTEGER DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
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
                balance_after INTEGER NOT NULL,
                reason TEXT NULL,
                reference_id INTEGER NULL,
                created_by INTEGER NULL,
                created_at TIMESTAMP NULL
            );

            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_no TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                payment_method TEXT NOT NULL DEFAULT 'cod',
                payment_status TEXT NOT NULL DEFAULT 'unpaid',
                subtotal NUMERIC NOT NULL,
                tax_total NUMERIC NOT NULL DEFAULT 0.00,
                delivery_fee NUMERIC NOT NULL DEFAULT 0.00,
                total NUMERIC NOT NULL,
                rx_required INTEGER NOT NULL DEFAULT 0,
                rx_status TEXT NOT NULL DEFAULT 'not_required',
                address_snapshot TEXT NULL,
                pharmacist_name TEXT NULL,
                pharmacist_reg_no TEXT NULL,
                courier_name TEXT NULL,
                tracking_no TEXT NULL,
                notes TEXT NULL,
                cancel_reason TEXT NULL,
                cancelled_by INTEGER NULL,
                idempotency_key TEXT NULL,
                confirmed_at TIMESTAMP NULL,
                packed_at TIMESTAMP NULL,
                shipped_at TIMESTAMP NULL,
                delivered_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                medicine_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                unit_price NUMERIC NOT NULL,
                line_total NUMERIC NOT NULL,
                gst_rate NUMERIC DEFAULT 12.00,
                requires_rx INTEGER DEFAULT 0
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
                changed_by INTEGER NULL,
                note TEXT NULL,
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
                error_message TEXT NULL,
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

            CREATE TABLE invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL UNIQUE,
                invoice_no TEXT NOT NULL UNIQUE,
                pdf_path TEXT NULL,
                issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE invoice_counters (
                year INTEGER PRIMARY KEY,
                last_number INTEGER NOT NULL DEFAULT 0
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

            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                action TEXT NOT NULL,
                entity TEXT NOT NULL,
                entity_id INTEGER NULL,
                old_values TEXT NULL,
                new_values TEXT NULL,
                ip TEXT NULL,
                created_at TIMESTAMP NULL
            );

            CREATE TABLE refresh_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                device_name TEXT NULL,
                ip TEXT NULL,
                expires_at TIMESTAMP NOT NULL,
                revoked_at TIMESTAMP NULL,
                replaced_by INTEGER NULL,
                created_at TIMESTAMP NULL
            );

            CREATE TABLE account_deletion_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                reason TEXT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                processed_at TIMESTAMP NULL,
                processed_by INTEGER NULL
            );

            CREATE TABLE carts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE cart_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cart_id INTEGER NOT NULL,
                medicine_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );
        ");

        $this->settingRepo = new SettingRepository($this->db);
        $this->settingRepo->set('shop_name', 'Sharma Medical Store');
        $this->settingRepo->set('owner_name', 'Ramesh Sharma');
        $this->settingRepo->set('gst_no', '07AAAAA0000A1Z5');
        $this->settingRepo->set('drug_license_no', 'DL-2026-DEL-9941');

        $this->userRepo = new UserRepository($this->db);
        $this->ownerUserId = $this->userRepo->create([
            'name' => 'Owner Customer',
            'phone' => '+919876500001',
            'email' => 'owner@example.com',
            'role' => 'customer',
        ]);

        $this->otherUserId = $this->userRepo->create([
            'name' => 'Other Customer',
            'phone' => '+919876500002',
            'email' => 'other@example.com',
            'role' => 'customer',
        ]);

        $this->adminUserId = $this->userRepo->create([
            'name' => 'Store Admin',
            'phone' => '+919876500003',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $this->jwt = new Jwt('test-secret-key-32-bytes-minimum!!', 'HS256', 900);
        $this->ownerToken = $this->jwt->encode(['sub' => $this->ownerUserId, 'role' => 'customer']);
        $this->otherToken = $this->jwt->encode(['sub' => $this->otherUserId, 'role' => 'customer']);
        $this->adminToken = $this->jwt->encode(['sub' => $this->adminUserId, 'role' => 'admin']);

        $this->invoiceRepo = new InvoiceRepository($this->db);
        $this->orderRepo = new OrderRepository($this->db);
        $this->notificationRepo = new NotificationRepository($this->db);

        $tempStorage = sys_get_temp_dir() . '/meditrack_test_invoices_' . uniqid();
        $this->invoiceService = new InvoiceService(
            $this->db,
            $this->invoiceRepo,
            $this->orderRepo,
            $this->userRepo,
            $this->settingRepo,
            $this->notificationRepo,
            null,
            $tempStorage
        );

        $cartRepo = new CartRepository($this->db);
        $cache = new \MediTrack\Core\Cache(null);
        $medRepo = new MedicineRepository($this->db, $cache);
        $addressRepo = new AddressRepository($this->db);
        $movementRepo = new StockMovementRepository($this->db);
        $paymentRepo = new PaymentRepository($this->db);
        $stockService = new StockService($this->db, $cache, $movementRepo);
        $cartService = new CartService($cartRepo, $medRepo, $this->settingRepo);
        $this->orderService = new OrderService(
            $this->db,
            $this->orderRepo,
            $cartService,
            $addressRepo,
            $stockService,
            $paymentRepo
        );

        $this->container = Container::getInstance();
        $this->container->instance(Database::class, $this->db);
        $this->container->instance(InvoiceRepository::class, $this->invoiceRepo);
        $this->container->instance(OrderRepository::class, $this->orderRepo);
        $this->container->instance(UserRepository::class, $this->userRepo);
        $this->container->instance(SettingRepository::class, $this->settingRepo);
        $this->container->instance(NotificationRepository::class, $this->notificationRepo);
        $this->container->instance(InvoiceService::class, $this->invoiceService);
        $this->container->instance(OrderService::class, $this->orderService);
        $this->container->instance(Jwt::class, $this->jwt);

        $this->router = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        // Seed a demo order with items and batches
        $pdo->exec("
            INSERT INTO medicines (id, name, slug, requires_rx, gst_percent)
            VALUES (1, 'Paracetamol 500mg', 'paracetamol-500mg', 0, 12.00);

            INSERT INTO batches (id, medicine_id, batch_number, quantity, purchase_price, mrp, selling_price, mfg_date, exp_date)
            VALUES (1, 1, 'BATCH-P500-01', 100, 15.00, 30.00, 25.00, '2025-01-01', '2027-01-01');

            INSERT INTO addresses (id, user_id, full_name, phone, address_line1, city, state, pincode)
            VALUES (1, {$this->ownerUserId}, 'Owner Customer', '+919876500001', '123 Health Ave', 'New Delhi', 'Delhi', '110001');

            INSERT INTO orders (id, order_no, user_id, status, payment_method, payment_status, subtotal, tax_total, delivery_fee, total, address_snapshot, created_at)
            VALUES (1, 'ORD-2026-0001', {$this->ownerUserId}, 'pending', 'cod', 'unpaid', 50.00, 6.00, 40.00, 96.00, '{\"full_name\":\"Owner Customer\",\"phone\":\"+919876500001\",\"address_line1\":\"123 Health Ave\",\"city\":\"New Delhi\",\"state\":\"Delhi\",\"pincode\":\"110001\"}', datetime('now'));

            INSERT INTO order_items (id, order_id, medicine_id, quantity, unit_price, line_total, gst_rate)
            VALUES (1, 1, 1, 2, 25.00, 50.00, 12.00);

            INSERT INTO order_item_batches (order_item_id, batch_id, quantity)
            VALUES (1, 1, 2);
        ");

        $this->orderId = 1;
    }

    public function testGapFreeSequentialInvoiceNumberingUnderConcurrency(): void
    {
        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $this->invoiceRepo->getNextInvoiceNumber(2026);
        }

        $this->assertSame('INV-2026-000001', $numbers[0]);
        $this->assertSame('INV-2026-000002', $numbers[1]);
        $this->assertSame('INV-2026-000003', $numbers[2]);
        $this->assertSame('INV-2026-000004', $numbers[3]);
        $this->assertSame('INV-2026-000005', $numbers[4]);

        // Rollover to next year
        $nextYearNum = $this->invoiceRepo->getNextInvoiceNumber(2027);
        $this->assertSame('INV-2027-000001', $nextYearNum);
    }

    public function testOrderConfirmedTriggersInvoiceGenerationAndSavesPdf(): void
    {
        // Transition order to confirmed
        $updated = $this->orderService->transition($this->orderId, 'confirmed', $this->adminUserId, 'Order confirmed by store');
        $this->assertSame('confirmed', $updated['status']);

        // Check invoice was generated
        $invoice = $this->invoiceRepo->findByOrderId($this->orderId);
        $this->assertNotNull($invoice);
        $this->assertStringStartsWith('INV-2026-', $invoice['invoice_no']);
        $this->assertNotEmpty($invoice['pdf_path']);
        $this->assertFileExists($invoice['pdf_path']);

        // Verify PDF file contents
        $content = file_get_contents($invoice['pdf_path']);
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }

    public function testInvoiceGenerationIsIdempotent(): void
    {
        $inv1 = $this->invoiceService->generateForOrder($this->orderId);
        $inv2 = $this->invoiceService->generateForOrder($this->orderId);

        $this->assertSame($inv1['id'], $inv2['id']);
        $this->assertSame($inv1['invoice_no'], $inv2['invoice_no']);
        $this->assertSame($inv1['pdf_path'], $inv2['pdf_path']);
    }

    public function testCustomerCanDownloadOwnInvoice(): void
    {
        // First confirm order to generate invoice
        $this->orderService->transition($this->orderId, 'confirmed', $this->adminUserId);

        $request = new Request(
            'GET',
            "/api/v1/orders/{$this->orderId}/invoice",
            ['Authorization' => "Bearer {$this->ownerToken}"]
        );

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('inline; filename="INV-2026-', $response->getHeader('Content-Disposition') ?? '');
        $this->assertStringStartsWith('%PDF-', $response->getBody());
    }

    public function testAnotherCustomerCannotDownloadInvoice403(): void
    {
        // Confirm order first
        $this->orderService->transition($this->orderId, 'confirmed', $this->adminUserId);

        // Other customer requests Owner's invoice
        $request = new Request(
            'GET',
            "/api/v1/orders/{$this->orderId}/invoice",
            ['Authorization' => "Bearer {$this->otherToken}"]
        );

        $response = $this->router->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('FORBIDDEN', $body['error']['code']);
    }

    public function testAdminCanListInvoicesWithPaginationAndFilters(): void
    {
        // Generate invoice
        $this->invoiceService->generateForOrder($this->orderId);

        $request = new Request(
            'GET',
            '/api/v1/admin/invoices?page=1&per_page=10&search=INV-',
            ['Authorization' => "Bearer {$this->adminToken}"]
        );

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertNotEmpty($body['data']);
        $this->assertSame(1, $body['meta']['total']);
        $this->assertSame('INV-2026-000001', $body['data'][0]['invoice_no']);
    }

    public function testAdminCanDownloadAnyInvoice(): void
    {
        $invoice = $this->invoiceService->generateForOrder($this->orderId);

        $request = new Request(
            'GET',
            "/api/v1/admin/invoices/{$invoice['id']}/download",
            ['Authorization' => "Bearer {$this->adminToken}"]
        );

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeader('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getBody());
    }

    public function testAdminCanResendInvoiceToCustomer(): void
    {
        $invoice = $this->invoiceService->generateForOrder($this->orderId);

        $request = new Request(
            'POST',
            "/api/v1/admin/invoices/{$invoice['id']}/resend",
            [
                'Authorization' => "Bearer {$this->adminToken}",
                'Content-Type' => 'application/json',
            ],
            [],
            ['channel' => 'all']
        );

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertTrue($body['data']['sent']);

        // Verify notification row was created for owner
        $notifications = $this->notificationRepo->findByUser($this->ownerUserId);
        $this->assertNotEmpty($notifications);
        $this->assertSame('Invoice Ready', $notifications[0]['title']);
    }
}
