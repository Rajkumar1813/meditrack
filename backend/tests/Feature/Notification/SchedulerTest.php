<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Notification;

use MediTrack\Commands\CommandInterface;
use MediTrack\Commands\DailyExpiryAlertCommand;
use MediTrack\Commands\ExpireUnpaidOrdersCommand;
use MediTrack\Commands\HourlyRefillReminderCommand;
use MediTrack\Commands\NightlyLowStockDigestCommand;
use MediTrack\Commands\Scheduler;
use MediTrack\Core\Container;
use MediTrack\Core\Database;
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
use MediTrack\Services\RefillService;
use MediTrack\Services\StockService;
use PHPUnit\Framework\TestCase;
use Predis\Client as RedisClient;

class SchedulerTest extends TestCase
{
    private Database $db;
    private Container $container;
    private BatchRepository $batchRepo;
    private MedicineRepository $medicineRepo;
    private OrderRepository $orderRepo;
    private UserRepository $userRepo;
    private SettingRepository $settingRepo;
    private NotificationRepository $notificationRepo;
    private NotificationPreferenceRepository $prefRepo;
    private DeviceTokenRepository $deviceTokenRepo;
    private FakePushService $fakePush;
    private NotificationService $notificationService;
    private OrderService $orderService;
    private RefillService $refillService;

    private int $adminId;
    private int $pharmacistId;

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

            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_no TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                payment_method TEXT NOT NULL DEFAULT 'online',
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
        ");

        $this->userRepo = new UserRepository($this->db);
        $this->adminId = $this->userRepo->create([
            'name' => 'Owner Admin',
            'phone' => '+919876543201',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
        $this->pharmacistId = $this->userRepo->create([
            'name' => 'Staff Pharmacist',
            'phone' => '+919876543202',
            'email' => 'pharm@example.com',
            'role' => 'pharmacist',
        ]);

        $cache = new \MediTrack\Core\Cache(null);
        $this->batchRepo = new BatchRepository($this->db, $cache);
        $this->medicineRepo = new MedicineRepository($this->db, $cache);
        $this->orderRepo = new OrderRepository($this->db);
        $this->settingRepo = new SettingRepository($this->db);
        $this->settingRepo->set('low_stock_threshold', '10');

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

        $cartRepo = new CartRepository($this->db);
        $cartService = new CartService($cartRepo, $this->medicineRepo, $this->settingRepo);
        $addrRepo = new AddressRepository($this->db);
        $movementRepo = new StockMovementRepository($this->db);
        $stockService = new StockService($this->db, $cache, $movementRepo);
        $payRepo = new PaymentRepository($this->db);

        $this->orderService = new OrderService(
            $this->db,
            $this->orderRepo,
            $cartService,
            $addrRepo,
            $stockService,
            $payRepo
        );

        $refillRepo = new RefillReminderRepository($this->db);
        $this->refillService = new RefillService(
            $refillRepo,
            $this->orderRepo,
            $this->medicineRepo,
            $cartService,
            $this->notificationService
        );

        $this->container = Container::getInstance();
    }

    public function testSchedulerRegistersAllCommands(): void
    {
        $scheduler = new Scheduler($this->container);
        $scheduler->register(new ExpireUnpaidOrdersCommand($this->orderService));
        $scheduler->register(new HourlyRefillReminderCommand($this->refillService));
        $scheduler->register(new DailyExpiryAlertCommand($this->batchRepo, $this->notificationService));
        $scheduler->register(new NightlyLowStockDigestCommand($this->medicineRepo, $this->settingRepo, $this->notificationService));

        $registered = $scheduler->getCommands();
        $this->assertCount(4, $registered);
        $this->assertArrayHasKey('expire_unpaid_orders', $registered);
        $this->assertArrayHasKey('hourly_refill_reminder', $registered);
        $this->assertArrayHasKey('daily_expiry_alert', $registered);
        $this->assertArrayHasKey('nightly_low_stock_digest', $registered);
    }

    public function testCommandTimingLogic(): void
    {
        $cmd5min = new ExpireUnpaidOrdersCommand($this->orderService);
        $this->assertTrue($cmd5min->shouldRun(new \DateTimeImmutable('2026-11-01 10:05:00')));
        $this->assertTrue($cmd5min->shouldRun(new \DateTimeImmutable('2026-11-01 10:10:00')));
        $this->assertFalse($cmd5min->shouldRun(new \DateTimeImmutable('2026-11-01 10:03:00')));

        $cmdHourly = new HourlyRefillReminderCommand($this->refillService);
        $this->assertTrue($cmdHourly->shouldRun(new \DateTimeImmutable('2026-11-01 14:00:00')));
        $this->assertFalse($cmdHourly->shouldRun(new \DateTimeImmutable('2026-11-01 14:15:00')));

        $cmdDaily = new DailyExpiryAlertCommand($this->batchRepo, $this->notificationService);
        $this->assertTrue($cmdDaily->shouldRun(new \DateTimeImmutable('2026-11-01 08:00:00')));
        $this->assertFalse($cmdDaily->shouldRun(new \DateTimeImmutable('2026-11-01 08:05:00')));
        $this->assertFalse($cmdDaily->shouldRun(new \DateTimeImmutable('2026-11-01 09:00:00')));

        $cmdNightly = new NightlyLowStockDigestCommand($this->medicineRepo, $this->settingRepo, $this->notificationService);
        $this->assertTrue($cmdNightly->shouldRun(new \DateTimeImmutable('2026-11-01 22:00:00')));
        $this->assertFalse($cmdNightly->shouldRun(new \DateTimeImmutable('2026-11-01 22:30:00')));
    }

    public function testRedisSetNxLockSkipsDuplicateExecution(): void
    {
        // Mock Redis client returning false on set (lock already held)
        $redisMock = $this->createMock(RedisClient::class);
        $redisMock->expects($this->once())
            ->method('__call')
            ->with('set', $this->callback(function ($args) {
                return str_contains($args[0], 'schedule:lock:expire_unpaid_orders');
            }))
            ->willReturn(false);

        $scheduler = new Scheduler($this->container, $redisMock);
        $scheduler->register(new ExpireUnpaidOrdersCommand($this->orderService));

        $results = $scheduler->run(new \DateTimeImmutable('2026-11-01 10:05:00'));

        $this->assertArrayHasKey('expire_unpaid_orders', $results);
        $this->assertSame('skipped: locked by another process', $results['expire_unpaid_orders']);
    }

    public function testDailyExpiryAlertExecution(): void
    {
        $pdo = $this->db->pdo();
        // Seed 1 medicine with 1 batch expiring in 10 days, 1 expired batch, and 1 fresh batch
        $today = date('Y-m-d');
        $expiringSoon = date('Y-m-d', strtotime('+10 days'));
        $expiredDate = date('Y-m-d', strtotime('-5 days'));
        $futureDate = date('Y-m-d', strtotime('+2 years'));

        $pdo->exec("
            INSERT INTO medicines (id, name, slug) VALUES (1, 'Paracetamol 650', 'paracetamol-650');

            INSERT INTO batches (id, medicine_id, batch_number, quantity, purchase_price, mrp, selling_price, mfg_date, exp_date)
            VALUES 
                (1, 1, 'BATCH-EXP-SOON', 20, 10.00, 20.00, 18.00, '2024-01-01', '{$expiringSoon}'),
                (2, 1, 'BATCH-EXPIRED', 10, 10.00, 20.00, 18.00, '2023-01-01', '{$expiredDate}'),
                (3, 1, 'BATCH-SAFE', 100, 10.00, 20.00, 18.00, '2025-01-01', '{$futureDate}');
        ");

        $command = new DailyExpiryAlertCommand($this->batchRepo, $this->notificationService);
        $output = $command->execute();

        $this->assertStringContainsString('Sent expiry digest', $output);
        $this->assertStringContainsString('1 expiring in 30d', $output);
        $this->assertStringContainsString('1 expired', $output);

        // Verify notifications were created for both Admin and Pharmacist
        $adminNotifs = $this->notificationRepo->paginateForUser($this->adminId);
        $this->assertCount(1, $adminNotifs);
        $this->assertSame('Daily Expiry Digest', $adminNotifs[0]['title']);

        $pharmNotifs = $this->notificationRepo->paginateForUser($this->pharmacistId);
        $this->assertCount(1, $pharmNotifs);
        $this->assertSame('Daily Expiry Digest', $pharmNotifs[0]['title']);
    }

    public function testNightlyLowStockDigestExecution(): void
    {
        $pdo = $this->db->pdo();
        // Seed 1 medicine with stock 4 (threshold is 10)
        $futureDate = date('Y-m-d', strtotime('+1 year'));
        $pdo->exec("
            INSERT INTO medicines (id, name, slug) VALUES (2, 'Azithromycin 500', 'azithromycin-500');

            INSERT INTO batches (id, medicine_id, batch_number, quantity, purchase_price, mrp, selling_price, mfg_date, exp_date)
            VALUES (4, 2, 'BATCH-LOW', 4, 30.00, 60.00, 50.00, '2025-01-01', '{$futureDate}');
        ");

        $command = new NightlyLowStockDigestCommand($this->medicineRepo, $this->settingRepo, $this->notificationService);
        $output = $command->execute();

        $this->assertStringContainsString('Sent low stock digest for 1 medicine(s)', $output);

        // Admin received alert
        $adminNotifs = $this->notificationRepo->paginateForUser($this->adminId);
        $this->assertNotEmpty($adminNotifs);
        $latest = $adminNotifs[0];
        $this->assertSame('Nightly Low Stock Digest', $latest['title']);
    }
}

