<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Order;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Core\Cache;
use MediTrack\Core\Container;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Core\Request;
use MediTrack\Core\Jwt;
use MediTrack\Enums\Role;
use MediTrack\Middleware\Idempotency;
use MediTrack\Policies\OwnershipPolicy;
use MediTrack\Repositories\AddressRepository;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\CartRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\PaymentRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\StockMovementRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\CartService;
use MediTrack\Services\OrderService;
use MediTrack\Services\StockService;

class OrderServiceTest extends TestCase
{
    private Database $db;
    private OrderRepository $orderRepo;
    private CartRepository $cartRepo;
    private MedicineRepository $medRepo;
    private SettingRepository $settingRepo;
    private AddressRepository $addressRepo;
    private StockMovementRepository $movementRepo;
    private StockService $stockService;
    private CartService $cartService;
    private PaymentRepository $paymentRepo;
    private OrderService $orderService;
    private int $addressId;
    private int $otcMedId;
    private int $rxMedId;

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
                value TEXT NOT NULL
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
                payment_method TEXT NOT NULL,
                payment_status TEXT NOT NULL DEFAULT 'unpaid',
                subtotal NUMERIC NOT NULL,
                discount NUMERIC NOT NULL DEFAULT 0.00,
                gst_total NUMERIC NOT NULL DEFAULT 0.00,
                delivery_fee NUMERIC NOT NULL DEFAULT 0.00,
                total NUMERIC NOT NULL,
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
                updated_at TIMESTAMP NULL,
                UNIQUE (user_id, idempotency_key)
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

            INSERT INTO settings (`key`, value) VALUES ('delivery_fee', '50.00');

            -- User 1 (customer) and User 2 (another customer)
            INSERT INTO users (id, phone, email, name, role) VALUES
            (1, '9876543210', 'customer1@example.com', 'Customer One', 'customer'),
            (2, '9876543211', 'customer2@example.com', 'Customer Two', 'customer');

            -- OTC Medicine with two unexpired batches in FEFO order:
            -- Batch 1: exp 2027-01-01, qty 10, price 18.00
            -- Batch 2: exp 2028-01-01, qty 20, price 20.00
            INSERT INTO medicines (id, name, slug, schedule, requires_rx, gst_percent)
            VALUES (1, 'Paracetamol 500', 'paracetamol-500', 'OTC', 0, 12.00);

            INSERT INTO batches (id, medicine_id, batch_number, quantity, reserved_quantity, purchase_price, mrp, selling_price, mfg_date, exp_date) VALUES
            (1, 1, 'BATCH-FEFO-1', 10, 0, 12.00, 25.00, 18.00, '2024-01-01', '2027-01-01'),
            (2, 1, 'BATCH-FEFO-2', 20, 0, 14.00, 25.00, 20.00, '2025-01-01', '2028-01-01');

            -- Rx Medicine
            INSERT INTO medicines (id, name, slug, schedule, requires_rx, gst_percent)
            VALUES (2, 'Azithromycin 500', 'azithromycin-500', 'H', 1, 12.00);

            INSERT INTO batches (id, medicine_id, batch_number, quantity, reserved_quantity, purchase_price, mrp, selling_price, mfg_date, exp_date) VALUES
            (3, 2, 'BATCH-AZI-1', 15, 0, 50.00, 80.00, 70.00, '2024-01-01', '2027-06-01');

            -- Address for user 1
            INSERT INTO addresses (id, user_id, recipient_name, phone, line1, city, state, pincode, is_default)
            VALUES (1, 1, 'Customer One', '9876543210', '123 Main Street', 'Jaipur', 'Rajasthan', '302001', 1);
        ");

        $this->addressId = 1;
        $this->otcMedId = 1;
        $this->rxMedId = 2;

        $cache = new Cache(null);
        $this->orderRepo = new OrderRepository($this->db);
        $this->cartRepo = new CartRepository($this->db);
        $this->medRepo = new MedicineRepository($this->db, $cache);
        $this->settingRepo = new SettingRepository($this->db);
        $this->addressRepo = new AddressRepository($this->db);
        $this->movementRepo = new StockMovementRepository($this->db);
        $this->paymentRepo = new PaymentRepository($this->db);
        $this->stockService = new StockService($this->db, $cache, $this->movementRepo);
        $this->cartService = new CartService($this->cartRepo, $this->medRepo, $this->settingRepo);

        $this->orderService = new OrderService(
            $this->db,
            $this->orderRepo,
            $this->cartService,
            $this->addressRepo,
            $this->stockService,
            $this->paymentRepo
        );

        // Bind in container for notification job execution
        $container = Container::getInstance();
        $container->instance(OrderRepository::class, $this->orderRepo);
        $container->instance(NotificationRepository::class, new NotificationRepository($this->db));
    }

    public function testOrderCreationHappyPathWithFefoBatchAllocation(): void
    {
        // 1. Add 15 units of Paracetamol to cart
        // (Batch 1 has 10 units, Batch 2 has 20 units -> FEFO should allocate 10 from Batch 1, and 5 from Batch 2)
        $this->cartService->addItem(1, $this->otcMedId, 15);

        // 2. Create order
        $order = $this->orderService->createOrder(1, [
            'address_id' => $this->addressId,
            'payment_method' => 'cod',
        ], 'test-idem-key-1');

        $this->assertNotNull($order['id']);
        $this->assertSame('pending', $order['status']);
        $this->assertSame(0, (int)$order['rx_required']);
        $this->assertSame('not_required', $order['rx_status']);
        $this->assertSame('cod', $order['payment_method']);

        // Check FEFO batch allocations
        $items = $this->orderRepo->getItemsWithBatches((int)$order['id']);
        $this->assertCount(1, $items);
        $batches = $items[0]['allocated_batches'];
        $this->assertCount(2, $batches);

        // First allocation from Batch 1 (10 units)
        $this->assertSame(1, (int)$batches[0]['batch_id']);
        $this->assertSame(10, (int)$batches[0]['quantity']);

        // Second allocation from Batch 2 (5 units)
        $this->assertSame(2, (int)$batches[1]['batch_id']);
        $this->assertSame(5, (int)$batches[1]['quantity']);

        // Check batches table: reserved_quantity must be incremented
        $b1 = $this->db->fetchOne('SELECT * FROM batches WHERE id = 1');
        $this->assertSame(10, (int)$b1['reserved_quantity']);

        $b2 = $this->db->fetchOne('SELECT * FROM batches WHERE id = 2');
        $this->assertSame(5, (int)$b2['reserved_quantity']);

        // Cart should now be empty
        $cart = $this->cartService->getSummary(1);
        $this->assertSame(0, $cart['item_count']);
    }

    public function testDoublePostSameIdempotencyKeyReturnsSameOrder(): void
    {
        $this->cartService->addItem(1, $this->otcMedId, 2);

        $idemKey = 'unique-key-abc-123';
        $middleware = new Idempotency($this->orderRepo);

        // First call through middleware
        $req1 = (new Request('POST', '/api/v1/orders', ['idempotency-key' => $idemKey]))
            ->withAttribute('user_id', 1);

        $res1 = $middleware->process($req1, function (Request $r) use ($idemKey) {
            $created = $this->orderService->createOrder(1, [
                'address_id' => $this->addressId,
                'payment_method' => 'cod',
            ], $r->attribute('idempotency_key'));
            return \MediTrack\Core\ApiResponse::success($created, null, 201);
        });

        $this->assertSame(201, $res1->getStatusCode());
        $body1 = json_decode($res1->getBody(), true);
        $orderId1 = $body1['data']['id'];

        // Second call with same key (simulating network retry)
        $req2 = (new Request('POST', '/api/v1/orders', ['idempotency-key' => $idemKey]))
            ->withAttribute('user_id', 1);

        $res2 = $middleware->process($req2, function (Request $r) {
            // Should NOT be called
            $this->fail('Handler should not be invoked on duplicate idempotent request.');
        });

        $this->assertSame(200, $res2->getStatusCode());
        $body2 = json_decode($res2->getBody(), true);
        $this->assertSame($orderId1, $body2['data']['id']);
        $this->assertTrue($body2['meta']['idempotent_replay']);

        // Stock was allocated only once (2 units reserved)
        $b1 = $this->db->fetchOne('SELECT * FROM batches WHERE id = 1');
        $this->assertSame(2, (int)$b1['reserved_quantity']);
    }

    public function testMissingIdempotencyKeyHeaderThrowsValidationException(): void
    {
        $middleware = new Idempotency($this->orderRepo);
        $req = (new Request('POST', '/api/v1/orders'))->withAttribute('user_id', 1);

        $this->expectException(ValidationException::class);
        $middleware->process($req, fn() => \MediTrack\Core\ApiResponse::success([]));
    }

    public function testStateMachineStandardTransitions(): void
    {
        $this->cartService->addItem(1, $this->otcMedId, 2);
        $order = $this->orderService->createOrder(1, [
            'address_id' => $this->addressId,
            'payment_method' => 'cod',
        ]);
        $orderId = (int)$order['id'];

        // 1. PENDING -> CONFIRMED (COD can confirm without payment)
        $confirmed = $this->orderService->transition($orderId, 'confirmed', 1, 'Auto confirmed');
        $this->assertSame('confirmed', $confirmed['status']);
        $this->assertNotNull($confirmed['confirmed_at']);

        // 2. CONFIRMED -> PACKED
        $packed = $this->orderService->transition($orderId, 'packed', 10, 'Packed by pharmacist');
        $this->assertSame('packed', $packed['status']);
        $this->assertNotNull($packed['packed_at']);

        // 3. PACKED -> SHIPPED (with courier & tracking)
        $shipped = $this->orderService->transition($orderId, 'shipped', 10, 'Handed to courier', [
            'courier' => 'BlueDart',
            'tracking_no' => 'BD-123456789',
        ]);
        $this->assertSame('shipped', $shipped['status']);
        $this->assertSame('BlueDart', $shipped['courier']);
        $this->assertSame('BD-123456789', $shipped['tracking_no']);

        // 4. SHIPPED -> DELIVERED (marks COD payment as paid)
        $delivered = $this->orderService->transition($orderId, 'delivered', 10, 'Delivered to customer');
        $this->assertSame('delivered', $delivered['status']);
        $this->assertSame('paid', $delivered['payment_status']);

        // Verify status history
        $history = $this->orderRepo->getStatusHistory($orderId);
        $this->assertCount(5, $history); // initial placed + 4 transitions
    }

    public function testInvalidStateTransitionThrowsValidationException(): void
    {
        $this->cartService->addItem(1, $this->otcMedId, 2);
        $order = $this->orderService->createOrder(1, [
            'address_id' => $this->addressId,
            'payment_method' => 'cod',
        ]);
        $orderId = (int)$order['id'];

        // Cannot jump directly from PENDING to SHIPPED
        $this->expectException(ValidationException::class);
        $this->orderService->transition($orderId, 'shipped', 1, 'Illegal jump');
    }

    public function testRxGatePreventsOrderConfirmationUntilApproved(): void
    {
        // Add Rx medicine to cart
        $this->cartService->addItem(1, $this->rxMedId, 1);
        $order = $this->orderService->createOrder(1, [
            'address_id' => $this->addressId,
            'payment_method' => 'cod',
        ]);
        $orderId = (int)$order['id'];

        $this->assertSame(1, (int)$order['rx_required']);
        $this->assertSame('pending', $order['rx_status']);

        // Attempting to confirm before Rx approved MUST throw 422
        try {
            $this->orderService->transition($orderId, 'confirmed', 1);
            $this->fail('Expected ValidationException when confirming order with pending Rx.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('prescription', $e->getErrors());
        }

        // Pharmacist approves prescription
        $this->db->execute("UPDATE orders SET rx_status = 'approved' WHERE id = ?", [$orderId]);

        // Confirmation should now succeed
        $confirmed = $this->orderService->transition($orderId, 'confirmed', 1);
        $this->assertSame('confirmed', $confirmed['status']);
    }

    public function testPaymentGatePreventsOnlineOrderConfirmationUntilPaid(): void
    {
        $this->cartService->addItem(1, $this->otcMedId, 1);
        $order = $this->orderService->createOrder(1, [
            'address_id' => $this->addressId,
            'payment_method' => 'upi', // Online payment method
        ]);
        $orderId = (int)$order['id'];

        $this->assertSame('unpaid', $order['payment_status']);

        // Attempting to confirm while unpaid MUST throw 422
        try {
            $this->orderService->transition($orderId, 'confirmed', 1);
            $this->fail('Expected ValidationException when confirming unpaid online order.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment', $e->getErrors());
        }

        // Payment captured
        $this->orderRepo->updatePaymentStatus($orderId, 'paid');

        // Confirmation succeeds
        $confirmed = $this->orderService->transition($orderId, 'confirmed', 1);
        $this->assertSame('confirmed', $confirmed['status']);
    }

    public function testCancellationReleasesReservedStockAndRestoresBatches(): void
    {
        $this->cartService->addItem(1, $this->otcMedId, 6);
        $order = $this->orderService->createOrder(1, [
            'address_id' => $this->addressId,
            'payment_method' => 'cod',
        ]);
        $orderId = (int)$order['id'];

        // Batch 1 had 6 reserved
        $b1 = $this->db->fetchOne('SELECT * FROM batches WHERE id = 1');
        $this->assertSame(6, (int)$b1['reserved_quantity']);

        // Customer cancels order
        $cancelled = $this->orderService->cancelByCustomer($orderId, 1, 'Changed mind');
        $this->assertSame('cancelled', $cancelled['status']);

        // Batch 1 reserved_quantity restored back to 0
        $b1After = $this->db->fetchOne('SELECT * FROM batches WHERE id = 1');
        $this->assertSame(0, (int)$b1After['reserved_quantity']);

        // Movement logged as cancel_restock
        $movement = $this->db->fetchOne("SELECT * FROM stock_movements WHERE type = 'cancel_restock' AND ref_id = ?", [$orderId]);
        $this->assertNotNull($movement);
        $this->assertSame(6, (int)$movement['qty_change']);
    }

    public function testCustomerCannotCancelOncePacked(): void
    {
        $this->cartService->addItem(1, $this->otcMedId, 1);
        $order = $this->orderService->createOrder(1, [
            'address_id' => $this->addressId,
            'payment_method' => 'cod',
        ]);
        $orderId = (int)$order['id'];

        $this->orderService->transition($orderId, 'confirmed', 1);
        $this->orderService->transition($orderId, 'packed', 1);

        // Customer attempts cancel on packed order -> rejected
        $this->expectException(ValidationException::class);
        $this->orderService->cancelByCustomer($orderId, 1, 'Too late');
    }

    public function testAdminCanCancelPackedOrder(): void
    {
        $this->cartService->addItem(1, $this->otcMedId, 1);
        $order = $this->orderService->createOrder(1, [
            'address_id' => $this->addressId,
            'payment_method' => 'cod',
        ]);
        $orderId = (int)$order['id'];

        $this->orderService->transition($orderId, 'confirmed', 1);
        $this->orderService->transition($orderId, 'packed', 1);

        // Admin can cancel packed order
        $cancelled = $this->orderService->cancelByAdmin($orderId, 99, 'Admin manual cancellation');
        $this->assertSame('cancelled', $cancelled['status']);
    }

    public function testExpireUnpaidOrdersCommand(): void
    {
        $this->cartService->addItem(1, $this->otcMedId, 4);
        $order = $this->orderService->createOrder(1, [
            'address_id' => $this->addressId,
            'payment_method' => 'upi', // Unpaid online order
        ]);
        $orderId = (int)$order['id'];

        // Simulate created 20 minutes ago
        $twentyMinutesAgo = date('Y-m-d H:i:s', time() - 1200);
        $this->db->execute('UPDATE orders SET created_at = ? WHERE id = ?', [$twentyMinutesAgo, $orderId]);

        // Run auto-expire
        $expiredCount = $this->orderService->expireUnpaidOrders(15);
        $this->assertSame(1, $expiredCount);

        // Order status is now cancelled
        $updated = $this->orderRepo->findById($orderId);
        $this->assertSame('cancelled', $updated['status']);

        // Reserved stock released
        $b1 = $this->db->fetchOne('SELECT * FROM batches WHERE id = 1');
        $this->assertSame(0, (int)$b1['reserved_quantity']);
    }

    public function testOwnershipPolicyIdorProtection(): void
    {
        $this->cartService->addItem(1, $this->otcMedId, 1);
        $order = $this->orderService->createOrder(1, [
            'address_id' => $this->addressId,
            'payment_method' => 'cod',
        ]);

        $user1 = ['id' => 1, 'role' => 'customer'];
        $user2 = ['id' => 2, 'role' => 'customer'];
        $admin = ['id' => 99, 'role' => Role::ADMIN->value];

        // User 1 can view own order
        $this->assertTrue(OwnershipPolicy::canAccessOrder($user1, $order));

        // User 2 cannot view User 1's order (IDOR protected)
        $this->assertFalse(OwnershipPolicy::canAccessOrder($user2, $order));

        // Admin can view any order
        $this->assertTrue(OwnershipPolicy::canAccessOrder($admin, $order));
    }

    public function testReorderAddsItemsToCart(): void
    {
        $this->cartService->addItem(1, $this->otcMedId, 3);
        $order = $this->orderService->createOrder(1, [
            'address_id' => $this->addressId,
            'payment_method' => 'cod',
        ]);
        $orderId = (int)$order['id'];

        // Cart is empty after order
        $this->assertSame(0, $this->cartService->getSummary(1)['item_count']);

        // Reorder adds items back
        $cartSummary = $this->orderService->reorder($orderId, 1);
        $this->assertSame(3, $cartSummary['item_count']);
        $this->assertSame($this->otcMedId, $cartSummary['items'][0]['medicine_id']);
    }
}
