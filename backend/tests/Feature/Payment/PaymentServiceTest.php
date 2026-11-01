<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Payment;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Core\Cache;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Core\Request;
use MediTrack\Controllers\Api\V1\WebhookController;
use MediTrack\Repositories\AddressRepository;
use MediTrack\Repositories\CartRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\PaymentRepository;
use MediTrack\Repositories\RefundRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\StockMovementRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Repositories\WebhookEventRepository;
use MediTrack\Services\CartService;
use MediTrack\Services\OrderService;
use MediTrack\Services\PaymentService;
use MediTrack\Services\StockService;
use MediTrack\Services\Payment\RazorpayGateway;
use MediTrack\Services\Payment\CodGateway;

class PaymentServiceTest extends TestCase
{
    private Database $db;
    private PaymentRepository $paymentRepo;
    private RefundRepository $refundRepo;
    private WebhookEventRepository $webhookRepo;
    private OrderRepository $orderRepo;
    private OrderService $orderService;
    private PaymentService $paymentService;
    private RazorpayGateway $razorpayGateway;
    private CodGateway $codGateway;

    private string $keyId = 'rzp_test_12345';
    private string $keySecret = 'test_secret_987654321';
    private string $webhookSecret = 'test_webhook_sec_abc';

    private int $onlineOrderId;
    private int $codOrderId;

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

            CREATE TABLE webhook_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                provider TEXT NOT NULL,
                event_id TEXT NOT NULL UNIQUE,
                payload TEXT NOT NULL,
                processed_at TIMESTAMP NULL,
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

            INSERT INTO users (id, name, phone, role) VALUES
            (1, 'Customer Rajesh', '9876543210', 'customer'),
            (99, 'Admin Suresh', '9876543299', 'admin');

            -- Seed 1 online order (total: ₹250.00)
            INSERT INTO orders (id, order_no, user_id, address_snapshot, status, rx_required, rx_status, payment_method, payment_status, subtotal, delivery_fee, total)
            VALUES (1, 'MT-20261101-ONLINE', 1, '{\"city\":\"Jaipur\"}', 'pending', 0, 'not_required', 'upi', 'unpaid', 200.00, 50.00, 250.00);

            -- Seed 1 COD order (total: ₹150.00)
            INSERT INTO orders (id, order_no, user_id, address_snapshot, status, rx_required, rx_status, payment_method, payment_status, subtotal, delivery_fee, total)
            VALUES (2, 'MT-20261101-COD', 1, '{\"city\":\"Jaipur\"}', 'pending', 0, 'not_required', 'cod', 'unpaid', 100.00, 50.00, 150.00);
        ");

        $this->onlineOrderId = 1;
        $this->codOrderId = 2;

        $cache = new Cache(null);
        $this->paymentRepo = new PaymentRepository($this->db);
        $this->refundRepo = new RefundRepository($this->db);
        $this->webhookRepo = new WebhookEventRepository($this->db);
        $this->orderRepo = new OrderRepository($this->db);
        $movementRepo = new StockMovementRepository($this->db);
        $stockService = new StockService($this->db, $cache, $movementRepo);
        $cartRepo = new CartRepository($this->db);
        $cartService = new CartService($cartRepo, new MedicineRepository($this->db, $cache), new SettingRepository($this->db));

        $this->orderService = new OrderService(
            $this->db,
            $this->orderRepo,
            $cartService,
            new AddressRepository($this->db),
            $stockService,
            $this->paymentRepo
        );

        $this->razorpayGateway = new RazorpayGateway($this->keyId, $this->keySecret, $this->webhookSecret);
        $this->codGateway = new CodGateway();

        $this->paymentService = new PaymentService(
            $this->paymentRepo,
            $this->refundRepo,
            $this->webhookRepo,
            $this->orderRepo,
            $this->orderService,
            $this->db,
            $this->razorpayGateway,
            $this->codGateway
        );
    }

    public function testCreatePaymentAmountAlwaysFromServerOrderTotal(): void
    {
        // Online order has total ₹250.00 in database
        $payload = $this->paymentService->createPayment($this->onlineOrderId, 1, 'razorpay');

        $this->assertSame('razorpay', $payload['gateway']);
        $this->assertSame(250.00, $payload['amount']);
        $this->assertSame(25000, $payload['amount_paise']); // In paise
        $this->assertSame('INR', $payload['currency']);
        $this->assertNotEmpty($payload['gateway_order_id']);

        // Check payment record saved in DB
        $payment = $this->paymentRepo->findByOrderId($this->onlineOrderId);
        $this->assertNotNull($payment);
        $this->assertSame(250.00, (float)$payment['amount']);
        $this->assertSame('created', $payment['status']);
    }

    public function testVerifyPaymentSuccessUnblocksConfirmation(): void
    {
        // 1. Create gateway payment
        $created = $this->paymentService->createPayment($this->onlineOrderId, 1, 'razorpay');
        $gwOrderId = $created['gateway_order_id'];
        $gwPaymentId = 'pay_test_succ_12345';

        // 2. Generate valid HMAC-SHA256 signature
        $validSignature = hash_hmac('sha256', "{$gwOrderId}|{$gwPaymentId}", $this->keySecret);

        // 3. Verify
        $res = $this->paymentService->verifyPayment($this->onlineOrderId, 1, [
            'razorpay_order_id' => $gwOrderId,
            'razorpay_payment_id' => $gwPaymentId,
            'razorpay_signature' => $validSignature,
        ]);

        $this->assertTrue($res['verified']);

        // Order payment status must now be paid
        $orderAfter = $this->orderRepo->findById($this->onlineOrderId);
        $this->assertSame('paid', $orderAfter['payment_status']);

        // Since rx_required == 0, order must have automatically transitioned to confirmed!
        $this->assertSame('confirmed', $orderAfter['status']);

        // Payment status in DB must be captured
        $paymentAfter = $this->paymentRepo->findByOrderId($this->onlineOrderId);
        $this->assertSame('captured', $paymentAfter['status']);
        $this->assertSame($gwPaymentId, $paymentAfter['gateway_payment_id']);
    }

    public function testVerifyPaymentTamperedSignatureRejected(): void
    {
        $created = $this->paymentService->createPayment($this->onlineOrderId, 1, 'razorpay');
        $gwOrderId = $created['gateway_order_id'];
        $gwPaymentId = 'pay_tamper_123';

        $fakeSignature = 'fake_signature_hex_1234567890';

        $this->expectException(ValidationException::class);
        $this->paymentService->verifyPayment($this->onlineOrderId, 1, [
            'razorpay_order_id' => $gwOrderId,
            'razorpay_payment_id' => $gwPaymentId,
            'razorpay_signature' => $fakeSignature,
        ]);
    }

    public function testVerifyPaymentIdempotent(): void
    {
        $created = $this->paymentService->createPayment($this->onlineOrderId, 1, 'razorpay');
        $gwOrderId = $created['gateway_order_id'];
        $gwPaymentId = 'pay_idem_123';

        $validSignature = hash_hmac('sha256', "{$gwOrderId}|{$gwPaymentId}", $this->keySecret);

        // First verification
        $res1 = $this->paymentService->verifyPayment($this->onlineOrderId, 1, [
            'razorpay_order_id' => $gwOrderId,
            'razorpay_payment_id' => $gwPaymentId,
            'razorpay_signature' => $validSignature,
        ]);
        $this->assertTrue($res1['verified']);

        // Second verification with same details -> idempotent return
        $res2 = $this->paymentService->verifyPayment($this->onlineOrderId, 1, [
            'razorpay_order_id' => $gwOrderId,
            'razorpay_payment_id' => $gwPaymentId,
            'razorpay_signature' => $validSignature,
        ]);
        $this->assertTrue($res2['verified']);
        $this->assertTrue($res2['already_paid']);
    }

    public function testRazorpayWebhookPaymentCapturedEvent(): void
    {
        $created = $this->paymentService->createPayment($this->onlineOrderId, 1, 'razorpay');
        $gwOrderId = $created['gateway_order_id'];
        $gwPaymentId = 'pay_webhook_capt_123';

        $rawPayload = json_encode([
            'id' => 'evt_captured_001',
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => $gwPaymentId,
                        'order_id' => $gwOrderId,
                        'amount' => 25000,
                        'method' => 'upi',
                        'notes' => [
                            'order_id' => $this->onlineOrderId,
                        ],
                    ],
                ],
            ],
        ]);

        $signature = hash_hmac('sha256', $rawPayload, $this->webhookSecret);

        $result = $this->paymentService->handleRazorpayWebhook($rawPayload, $signature);
        $this->assertSame('processed', $result['status']);

        // Order marked paid
        $order = $this->orderRepo->findById($this->onlineOrderId);
        $this->assertSame('paid', $order['payment_status']);
    }

    public function testRazorpayWebhookDuplicateEventSkipped(): void
    {
        $rawPayload = json_encode([
            'id' => 'evt_duplicate_test',
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_dup_123',
                        'order_id' => 'order_fake_dup',
                        'amount' => 10000,
                        'notes' => ['order_id' => $this->onlineOrderId],
                    ],
                ],
            ],
        ]);

        $signature = hash_hmac('sha256', $rawPayload, $this->webhookSecret);

        // First call -> processed
        $res1 = $this->paymentService->handleRazorpayWebhook($rawPayload, $signature);
        $this->assertSame('processed', $res1['status']);

        // Second call with same event id -> already_processed
        $res2 = $this->paymentService->handleRazorpayWebhook($rawPayload, $signature);
        $this->assertSame('already_processed', $res2['status']);
    }

    public function testRazorpayWebhookTamperedSignatureRejected(): void
    {
        $controller = new WebhookController($this->paymentService);
        $rawBody = '{"id":"evt_test","event":"payment.captured"}';

        // Bad signature
        $req = (new Request('POST', '/api/v1/webhooks/razorpay', [
            'x-razorpay-signature' => 'invalid_tampered_sig',
            'content-type' => 'application/json',
        ]))->withRawBody($rawBody);

        $response = $controller->razorpay($req);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCancelAutoRefundFlow(): void
    {
        // 1. Mark order as paid
        $this->paymentRepo->create([
            'order_id' => $this->onlineOrderId,
            'gateway' => 'razorpay',
            'gateway_payment_id' => 'pay_to_refund_123',
            'amount' => 250.00,
            'status' => 'captured',
        ]);
        $this->orderRepo->updatePaymentStatus($this->onlineOrderId, 'paid');

        // 2. Customer cancels order
        $cancelled = $this->orderService->cancelByCustomer($this->onlineOrderId, 1, 'Need refund');
        $this->assertSame('cancelled', $cancelled['status']);

        // 3. Payment status automatically updated to refunded
        $orderAfter = $this->orderRepo->findById($this->onlineOrderId);
        $this->assertSame('refunded', $orderAfter['payment_status']);

        // 4. Refund record created in database
        $refunds = $this->db->fetchAll('SELECT * FROM refunds WHERE payment_id = (SELECT id FROM payments WHERE order_id = ?)', [$this->onlineOrderId]);
        $this->assertCount(1, $refunds);
        $this->assertSame(250.00, (float)$refunds[0]['amount']);
        $this->assertSame('initiated', $refunds[0]['status']);
    }

    public function testAdminPartialRefund(): void
    {
        $this->paymentRepo->create([
            'order_id' => $this->onlineOrderId,
            'gateway' => 'razorpay',
            'gateway_payment_id' => 'pay_partial_123',
            'amount' => 250.00,
            'status' => 'captured',
        ]);
        $this->orderRepo->updatePaymentStatus($this->onlineOrderId, 'paid');

        // 1. Partial refund of ₹100 out of ₹250
        $res1 = $this->paymentService->processRefund($this->onlineOrderId, 100.00, 'Partial return of medicine', 99);
        $this->assertSame('partially_refunded', $res1['new_payment_status']);

        $order1 = $this->orderRepo->findById($this->onlineOrderId);
        $this->assertSame('partially_refunded', $order1['payment_status']);

        // 2. Attempting to refund more than remaining ₹150 fails
        try {
            $this->paymentService->processRefund($this->onlineOrderId, 151.00, 'Too much', 99);
            $this->fail('Expected ValidationException when refund exceeds remaining balance.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->getErrors());
        }

        // 3. Remaining ₹150 refund completes full refund
        $res2 = $this->paymentService->processRefund($this->onlineOrderId, 150.00, 'Remaining refund', 99);
        $this->assertSame('refunded', $res2['new_payment_status']);

        $order2 = $this->orderRepo->findById($this->onlineOrderId);
        $this->assertSame('refunded', $order2['payment_status']);
    }

    public function testCodMarkedPaidOnDelivered(): void
    {
        // COD order is pending and unpaid
        $order = $this->orderRepo->findById($this->codOrderId);
        $this->assertSame('cod', $order['payment_method']);
        $this->assertSame('unpaid', $order['payment_status']);

        // Confirm -> Pack -> Ship -> Deliver
        $this->orderService->transition($this->codOrderId, 'confirmed', 99);
        $this->orderService->transition($this->codOrderId, 'packed', 99);
        $this->orderService->transition($this->codOrderId, 'shipped', 99, null, ['courier' => 'Local Agent', 'tracking_no' => 'COD-999']);
        $delivered = $this->orderService->transition($this->codOrderId, 'delivered', 99);

        // COD marked as paid upon delivery
        $this->assertSame('delivered', $delivered['status']);
        $this->assertSame('paid', $delivered['payment_status']);
    }
}

