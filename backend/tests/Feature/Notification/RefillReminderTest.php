<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Notification;

use MediTrack\Controllers\Api\V1\RefillController;
use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Jwt;
use MediTrack\Core\Request;
use MediTrack\Core\Router;
use MediTrack\Jobs\SendOrderStatusNotificationJob;
use MediTrack\Repositories\AddressRepository;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\CartRepository;
use MediTrack\Repositories\DeviceTokenRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\NotificationPreferenceRepository;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\PaymentRepository;
use MediTrack\Repositories\RefillReminderRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\StockMovementRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\CartService;
use MediTrack\Services\NotificationService;
use MediTrack\Services\OrderService;
use MediTrack\Services\Push\FakePushService;
use MediTrack\Services\Push\PushServiceInterface;
use MediTrack\Services\RefillService;
use MediTrack\Services\StockService;
use PHPUnit\Framework\TestCase;

class RefillReminderTest extends TestCase
{
    private Database $db;
    private Container $container;
    private Router $router;
    private Jwt $jwt;
    private UserRepository $userRepo;
    private MedicineRepository $medicineRepo;
    private BatchRepository $batchRepo;
    private OrderRepository $orderRepo;
    private CartRepository $cartRepo;
    private SettingRepository $settingRepo;
    private CartService $cartService;
    private OrderService $orderService;
    private RefillReminderRepository $refillRepo;
    private NotificationRepository $notificationRepo;
    private NotificationPreferenceRepository $prefRepo;
    private DeviceTokenRepository $deviceTokenRepo;
    private FakePushService $fakePush;
    private NotificationService $notificationService;
    private RefillService $refillService;

    private int $userId;
    private string $userToken;
    private int $medicineId;

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

            CREATE TABLE device_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                fcm_token TEXT NOT NULL UNIQUE,
                platform TEXT NOT NULL DEFAULT 'android',
                last_seen_at TIMESTAMP NULL,
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
                rack_id INTEGER NULL,
                shelf_id INTEGER NULL,
                supplier_id INTEGER NULL,
                disposal_status TEXT NOT NULL DEFAULT 'none',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE carts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
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

            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_no TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                payment_method TEXT NOT NULL DEFAULT 'cod',
                payment_status TEXT NOT NULL DEFAULT 'unpaid',
                subtotal NUMERIC NOT NULL,
                discount NUMERIC DEFAULT 0.00,
                gst_total NUMERIC DEFAULT 0.00,
                delivery_fee NUMERIC NOT NULL DEFAULT 0.00,
                total NUMERIC NOT NULL,
                rx_required INTEGER NOT NULL DEFAULT 0,
                rx_status TEXT NOT NULL DEFAULT 'not_required',
                address_snapshot TEXT NULL,
                pharmacist_name TEXT NULL,
                pharmacist_reg_no TEXT NULL,
                courier TEXT NULL,
                tracking_number TEXT NULL,
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

            CREATE TABLE user_notification_preferences (
                user_id INTEGER PRIMARY KEY,
                order_updates INTEGER NOT NULL DEFAULT 1,
                refill_reminders INTEGER NOT NULL DEFAULT 1,
                promotional_push INTEGER NOT NULL DEFAULT 1,
                stock_alerts INTEGER NOT NULL DEFAULT 1,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE refill_reminders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                medicine_id INTEGER NOT NULL,
                source_order_id INTEGER NULL,
                course_days INTEGER NOT NULL DEFAULT 30,
                remind_at TEXT NOT NULL,
                snoozed_until TEXT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                last_notified_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL
            );
        ");

        $this->userRepo = new UserRepository($this->db);
        $this->userId = $this->userRepo->create([
            'name' => 'Vikas Gupta',
            'phone' => '+919876543210',
            'email' => 'vikas@example.com',
            'role' => 'customer',
        ]);

        $this->jwt = new Jwt('test-secret-key-32-characters-min!!');
        $this->userToken = $this->jwt->encode([
            'sub' => $this->userId,
            'role' => 'customer',
            'phone' => '+919876543210',
        ]);

        $cache = new \MediTrack\Core\Cache(null);
        $this->medicineRepo = new MedicineRepository($this->db, $cache);
        $this->batchRepo = new BatchRepository($this->db, $cache);
        $this->cartRepo = new CartRepository($this->db);
        $this->settingRepo = new SettingRepository($this->db);
        $this->settingRepo->set('delivery_fee', '40.00');
        $this->cartService = new CartService($this->cartRepo, $this->medicineRepo, $this->settingRepo);

        $this->orderRepo = new OrderRepository($this->db);
        $this->refillRepo = new RefillReminderRepository($this->db);
        $this->notificationRepo = new NotificationRepository($this->db);
        $this->prefRepo = new NotificationPreferenceRepository($this->db);
        $this->deviceTokenRepo = new DeviceTokenRepository($this->db);
        $this->fakePush = new FakePushService($this->deviceTokenRepo);

        $this->notificationService = new NotificationService(
            $this->notificationRepo,
            $this->prefRepo,
            $this->userRepo,
            $this->fakePush
        );

        $this->refillService = new RefillService(
            $this->refillRepo,
            $this->orderRepo,
            $this->medicineRepo,
            $this->cartService,
            $this->notificationService
        );

        $this->container = Container::getInstance();
        $this->container->instance(Database::class, $this->db);
        $this->container->instance(UserRepository::class, $this->userRepo);
        $this->container->instance(MedicineRepository::class, $this->medicineRepo);
        $this->container->instance(BatchRepository::class, $this->batchRepo);
        $this->container->instance(OrderRepository::class, $this->orderRepo);
        $this->container->instance(CartRepository::class, $this->cartRepo);
        $this->container->instance(SettingRepository::class, $this->settingRepo);
        $this->container->instance(CartService::class, $this->cartService);
        $this->container->instance(NotificationRepository::class, $this->notificationRepo);
        $this->container->instance(NotificationPreferenceRepository::class, $this->prefRepo);
        $this->container->instance(DeviceTokenRepository::class, $this->deviceTokenRepo);
        $this->container->instance(PushServiceInterface::class, $this->fakePush);
        $this->container->instance(NotificationService::class, $this->notificationService);
        $this->container->instance(RefillReminderRepository::class, $this->refillRepo);
        $this->container->instance(RefillService::class, $this->refillService);
        $this->container->instance(Jwt::class, $this->jwt);

        $refillController = new RefillController($this->refillService);
        $this->container->instance(RefillController::class, $refillController);

        $this->router = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        // Seed a medicine and batch
        $pdo->exec("
            INSERT INTO medicines (id, name, slug, pack_size, requires_rx, gst_percent)
            VALUES (1, 'Cetirizine 10mg', 'cetirizine-10mg', '10 tablets', 0, 12.00);

            INSERT INTO batches (id, medicine_id, batch_number, quantity, purchase_price, mrp, selling_price, mfg_date, exp_date)
            VALUES (1, 1, 'BATCH-CET-01', 50, 10.00, 25.00, 20.00, '2025-01-01', '2027-01-01');
        ");
        $this->medicineId = 1;
    }

    public function testRefillReminderCreatedOnDelivered(): void
    {
        $pdo = $this->db->pdo();
        $pdo->exec("
            INSERT INTO orders (id, order_no, user_id, status, total, subtotal, payment_method, payment_status, created_at)
            VALUES (10, 'ORD-TEST-10', {$this->userId}, 'shipped', 60.00, 20.00, 'cod', 'unpaid', datetime('now'));

            INSERT INTO order_items (id, order_id, medicine_id, quantity, unit_price, line_total)
            VALUES (10, 10, {$this->medicineId}, 1, 20.00, 20.00);
        ");

        // Dispatch status transition to 'delivered'
        $job = new SendOrderStatusNotificationJob(10, 'shipped', 'delivered');
        $job->handle($this->container);

        // Verify refill reminder was created for Cetirizine
        $reminders = $this->refillRepo->listForUser($this->userId);
        $this->assertCount(1, $reminders);

        $reminder = $reminders[0];
        $this->assertSame($this->medicineId, (int)$reminder['medicine_id']);
        $this->assertSame(30, (int)$reminder['course_days']);
        $this->assertSame('active', $reminder['status']);

        // Check remind_at date calculation: today + (30 - 3) = today + 27 days
        $expectedRemindDate = date('Y-m-d', strtotime('+27 days'));
        $this->assertSame($expectedRemindDate, $reminder['remind_at']);
    }

    public function testListCustomerRefillsEndpoint(): void
    {
        $this->refillRepo->create($this->userId, $this->medicineId, null, 30, date('Y-m-d', strtotime('+10 days')));

        $request = Request::create(
            'GET',
            '/api/v1/refills',
            ['authorization' => "Bearer {$this->userToken}"]
        );

        $res = $this->router->dispatch($request);
        $this->assertSame(200, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['success']);
        $this->assertCount(1, $body['data']);
        $this->assertSame('Cetirizine 10mg', $body['data'][0]['medicine_name']);
        $this->assertSame(20.0, (float)$body['data'][0]['estimated_price']);
    }

    public function testSnoozeRefillReminderEndpoint(): void
    {
        $id = $this->refillRepo->create($this->userId, $this->medicineId, null, 30, date('Y-m-d'));

        $request = Request::create(
            'POST',
            "/api/v1/refills/{$id}/snooze",
            [
                'authorization' => "Bearer {$this->userToken}",
                'content-type' => 'application/json',
            ],
            [],
            ['days' => 7]
        );

        $res = $this->router->dispatch($request);
        $this->assertSame(200, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['success']);
        $expectedDate = date('Y-m-d', strtotime('+7 days'));
        $this->assertSame($expectedDate, $body['data']['remind_at']);
    }

    public function testDismissRefillReminderEndpoint(): void
    {
        $id = $this->refillRepo->create($this->userId, $this->medicineId, null, 30, date('Y-m-d'));

        $request = Request::create(
            'POST',
            "/api/v1/refills/{$id}/dismiss",
            ['authorization' => "Bearer {$this->userToken}"]
        );

        $res = $this->router->dispatch($request);
        $this->assertSame(200, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertSame('dismissed', $body['data']['status']);
    }

    public function test1TapReorderAddsToCart(): void
    {
        $id = $this->refillRepo->create($this->userId, $this->medicineId, null, 30, date('Y-m-d'));

        $request = Request::create(
            'POST',
            "/api/v1/refills/{$id}/reorder",
            ['authorization' => "Bearer {$this->userToken}"]
        );

        $res = $this->router->dispatch($request);
        $this->assertSame(200, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['data']['reordered']);
        $this->assertSame('Cetirizine 10mg', $body['data']['medicine_name']);

        // Check reminder status is now 'ordered'
        $reminder = $this->refillRepo->findById($id);
        $this->assertSame('ordered', $reminder['status']);

        // Check user's cart now has the item
        $cart = $this->cartService->getSummary($this->userId);
        $this->assertCount(1, $cart['items']);
        $this->assertSame($this->medicineId, (int)$cart['items'][0]['medicine_id']);
    }

    public function testProcessDueRemindersSendsNotification(): void
    {
        $today = date('Y-m-d');
        $id = $this->refillRepo->create($this->userId, $this->medicineId, null, 30, $today);

        $this->deviceTokenRepo->save($this->userId, 'fcm-vikas-token-123', 'android');

        $processed = $this->refillService->processDueReminders($today);
        $this->assertSame(1, $processed);

        // In-app notification created
        $this->assertSame(1, $this->notificationRepo->getUnreadCount($this->userId));

        // Push sent via FakePushService
        $this->assertSame(1, $this->fakePush->count());

        // Status updated to 'sent'
        $rem = $this->refillRepo->findById($id);
        $this->assertSame('sent', $rem['status']);
        $this->assertNotNull($rem['last_notified_at']);
    }
}
