<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Customer;

use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Jwt;
use MediTrack\Core\Request;
use MediTrack\Core\Router;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\CustomerService;
use PHPUnit\Framework\TestCase;

class CustomerManagementTest extends TestCase
{
    private Database $db;
    private Container $container;
    private Router $router;
    private Jwt $jwt;
    private UserRepository $userRepo;
    private AuditLogRepository $auditRepo;
    private CustomerService $customerService;

    private int $adminId;
    private int $customerId1;
    private int $customerId2;
    private string $adminToken;
    private string $customer1Token;

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

            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_no TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                payment_method TEXT NOT NULL DEFAULT 'cod',
                payment_status TEXT NOT NULL DEFAULT 'unpaid',
                subtotal NUMERIC NOT NULL,
                tax_total NUMERIC NOT NULL DEFAULT 0.00,
                gst_total NUMERIC NOT NULL DEFAULT 0.00,
                delivery_fee NUMERIC NOT NULL DEFAULT 0.00,
                discount NUMERIC DEFAULT 0.00,
                total NUMERIC NOT NULL,
                rx_required INTEGER NOT NULL DEFAULT 0,
                rx_status TEXT NOT NULL DEFAULT 'not_required',
                address_snapshot TEXT NULL,
                pharmacist_name TEXT NULL,
                pharmacist_reg_no TEXT NULL,
                courier TEXT NULL,
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

            CREATE TABLE medicines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL,
                deleted_at TIMESTAMP NULL
            );

            CREATE TABLE prescriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP NULL
            );

            CREATE TABLE prescription_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                prescription_id INTEGER NOT NULL,
                original_name TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                file_size INTEGER NOT NULL,
                created_at TIMESTAMP NULL
            );

            CREATE TABLE refill_reminders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                medicine_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'active',
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
        ");

        $this->userRepo = new UserRepository($this->db);
        $this->auditRepo = new AuditLogRepository($this->db);
        $this->customerService = new CustomerService($this->userRepo, $this->auditRepo);

        // Seed users
        $this->adminId = $this->userRepo->create([
            'name' => 'Admin Ramesh',
            'phone' => '+919811111111',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $this->customerId1 = $this->userRepo->create([
            'name' => 'Aarav Sharma',
            'phone' => '+919822222222',
            'email' => 'aarav@example.com',
            'role' => 'customer',
        ]);

        $this->customerId2 = $this->userRepo->create([
            'name' => 'Priya Patel',
            'phone' => '+919833333333',
            'email' => 'priya@example.com',
            'role' => 'customer',
        ]);

        $this->jwt = new Jwt('test-secret-key-32-bytes-minimum!!', 'HS256', 900);
        $this->adminToken = $this->jwt->encode(['sub' => $this->adminId, 'role' => 'admin']);
        $this->customer1Token = $this->jwt->encode(['sub' => $this->customerId1, 'role' => 'customer']);

        // Seed orders for Aarav: 2 confirmed (200.00, 300.00) and 1 cancelled (150.00) -> LTV = 500.00
        $pdo->exec("
            INSERT INTO orders (id, order_no, user_id, status, subtotal, tax_total, delivery_fee, total, created_at)
            VALUES
                (1, 'ORD-2026-001', {$this->customerId1}, 'confirmed', 180.00, 20.00, 0.00, 200.00, datetime('now')),
                (2, 'ORD-2026-002', {$this->customerId1}, 'delivered', 270.00, 30.00, 0.00, 300.00, datetime('now')),
                (3, 'ORD-2026-003', {$this->customerId1}, 'cancelled', 130.00, 20.00, 0.00, 150.00, datetime('now'));

            INSERT INTO addresses (id, user_id, full_name, phone, address_line1, city, state, pincode, is_default)
            VALUES (1, {$this->customerId1}, 'Aarav Sharma', '+919822222222', '45 Green Park', 'New Delhi', 'Delhi', '110016', 1);

            INSERT INTO refresh_tokens (user_id, token_hash, device_name, expires_at, created_at)
            VALUES ({$this->customerId1}, 'mock_hash_token_aarav_session', 'Chrome Windows', datetime('now', '+30 days'), datetime('now'));
        ");

        $this->container = Container::getInstance();
        $this->container->instance(Database::class, $this->db);
        $this->container->instance(UserRepository::class, $this->userRepo);
        $this->container->instance(AuditLogRepository::class, $this->auditRepo);
        $this->container->instance(CustomerService::class, $this->customerService);
        $this->container->instance(Jwt::class, $this->jwt);

        $this->router = require dirname(__DIR__, 3) . '/bootstrap/app.php';
    }

    public function testAdminCanListCustomersWithOrderCountAndLtv(): void
    {
        $request = new Request(
            'GET',
            '/api/v1/admin/customers?page=1&per_page=10',
            ['Authorization' => "Bearer {$this->adminToken}"]
        );

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertCount(2, $body['data']);
        $this->assertSame(2, $body['meta']['total']);

        // Find Aarav's entry
        $aarav = null;
        foreach ($body['data'] as $item) {
            if ($item['id'] === $this->customerId1) {
                $aarav = $item;
                break;
            }
        }

        $this->assertNotNull($aarav);
        $this->assertSame('Aarav Sharma', $aarav['name']);
        $this->assertSame(3, $aarav['orders_count']);
        // Non-cancelled total: 200 + 300 = 500
        $this->assertEquals(500.0, $aarav['lifetime_value']);
    }

    public function testAdminCanSearchCustomersByNamePhoneEmail(): void
    {
        // Search by name
        $reqName = new Request(
            'GET',
            '/api/v1/admin/customers?search=Priya',
            ['Authorization' => "Bearer {$this->adminToken}"]
        );
        $resName = $this->router->dispatch($reqName);
        $bodyName = json_decode($resName->getBody(), true);
        $this->assertSame(1, $bodyName['meta']['total']);
        $this->assertSame('Priya Patel', $bodyName['data'][0]['name']);

        // Search by phone
        $reqPhone = new Request(
            'GET',
            '/api/v1/admin/customers?search=9822222222',
            ['Authorization' => "Bearer {$this->adminToken}"]
        );
        $resPhone = $this->router->dispatch($reqPhone);
        $bodyPhone = json_decode($resPhone->getBody(), true);
        $this->assertSame(1, $bodyPhone['meta']['total']);
        $this->assertSame('Aarav Sharma', $bodyPhone['data'][0]['name']);

        // Search by email
        $reqEmail = new Request(
            'GET',
            '/api/v1/admin/customers?search=priya@example.com',
            ['Authorization' => "Bearer {$this->adminToken}"]
        );
        $resEmail = $this->router->dispatch($reqEmail);
        $bodyEmail = json_decode($resEmail->getBody(), true);
        $this->assertSame(1, $bodyEmail['meta']['total']);
    }

    public function testAdminCanViewCustomerDetailsWithAddressesAndOrders(): void
    {
        $request = new Request(
            'GET',
            "/api/v1/admin/customers/{$this->customerId1}",
            ['Authorization' => "Bearer {$this->adminToken}"]
        );

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('Aarav Sharma', $body['data']['user']['name']);
        $this->assertSame(500.0, (float)$body['data']['statistics']['lifetime_value']);
        $this->assertNotEmpty($body['data']['addresses']);
        $this->assertSame('45 Green Park', $body['data']['addresses'][0]['address_line1']);
        $this->assertCount(3, $body['data']['recent_orders']);
    }

    public function testAdminCanBlockCustomerAndRevokeAllRefreshTokens(): void
    {
        $request = new Request(
            'POST',
            "/api/v1/admin/customers/{$this->customerId1}/block",
            [
                'Authorization' => "Bearer {$this->adminToken}",
                'Content-Type' => 'application/json',
            ],
            [],
            ['reason' => 'Violation of terms']
        );

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame(1, $body['data']['user']['is_blocked']);

        // Verify in database: user is blocked
        $user = $this->userRepo->findById($this->customerId1);
        $this->assertSame(1, (int)$user['is_blocked']);

        // Verify all refresh tokens for Aarav are revoked
        $tokens = $this->db->fetchAll('SELECT * FROM refresh_tokens WHERE user_id = ?', [$this->customerId1]);
        $this->assertNotEmpty($tokens);
        foreach ($tokens as $token) {
            $this->assertNotNull($token['revoked_at']);
        }

        // Verify audit log
        $logs = $this->auditRepo->getByEntity('user', $this->customerId1);
        $this->assertNotEmpty($logs);
        $this->assertSame('customer_blocked', $logs[0]['action']);
    }

    public function testBlockedCustomerReceivesForbidden403OnAuthenticatedEndpoints(): void
    {
        // Block the customer
        $this->customerService->blockCustomer($this->customerId1, $this->adminId, 'Account locked');

        // Blocked customer attempts to access authenticated endpoint
        $request = new Request(
            'GET',
            '/api/v1/orders',
            ['Authorization' => "Bearer {$this->customer1Token}"]
        );

        try {
            $response = $this->router->dispatch($request);
            $this->assertSame(403, $response->getStatusCode());
            $body = json_decode($response->getBody(), true);
            $this->assertFalse($body['success']);
            $this->assertSame('ACCOUNT_BLOCKED', $body['error']['code']);
        } catch (\MediTrack\Core\Exceptions\ForbiddenException $e) {
            $this->assertSame('ACCOUNT_BLOCKED', $e->getErrorCode());
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function testAdminCanUnblockCustomer(): void
    {
        // Block first
        $this->customerService->blockCustomer($this->customerId1, $this->adminId);

        $request = new Request(
            'POST',
            "/api/v1/admin/customers/{$this->customerId1}/unblock",
            ['Authorization' => "Bearer {$this->adminToken}"]
        );

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame(0, $body['data']['user']['is_blocked']);

        // Verify user can now authenticate and access orders
        $user = $this->userRepo->findById($this->customerId1);
        $this->assertSame(0, (int)$user['is_blocked']);

        $reqOrders = new Request(
            'GET',
            '/api/v1/orders',
            ['Authorization' => "Bearer {$this->customer1Token}"]
        );
        $resOrders = $this->router->dispatch($reqOrders);
        $this->assertSame(200, $resOrders->getStatusCode());
    }

    public function testAdminCanExportCustomerDataUnderDPDP(): void
    {
        $request = new Request(
            'GET',
            "/api/v1/admin/customers/{$this->customerId1}/export",
            ['Authorization' => "Bearer {$this->adminToken}"]
        );

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertStringContainsString('DPDP', $body['data']['dpdp_compliance']);
        $this->assertSame('Aarav Sharma', $body['data']['user']['name']);
        $this->assertArrayHasKey('addresses', $body['data']);
        $this->assertArrayHasKey('orders', $body['data']);
        $this->assertArrayHasKey('prescriptions', $body['data']);
        $this->assertArrayHasKey('refill_reminders', $body['data']);
        $this->assertArrayHasKey('account_deletion_requests', $body['data']);
    }

    public function testAdminCanRecordAccountDeletionRequestUnderDPDP(): void
    {
        $request = new Request(
            'POST',
            "/api/v1/admin/customers/{$this->customerId1}/delete-request",
            [
                'Authorization' => "Bearer {$this->adminToken}",
                'Content-Type' => 'application/json',
            ],
            [],
            ['reason' => 'User requested right to be forgotten']
        );

        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('pending', $body['data']['status']);

        // List deletion requests as admin
        $reqList = new Request(
            'GET',
            '/api/v1/admin/customers/delete-requests',
            ['Authorization' => "Bearer {$this->adminToken}"]
        );
        $resList = $this->router->dispatch($reqList);
        $bodyList = json_decode($resList->getBody(), true);
        $this->assertSame(200, $resList->getStatusCode());
        $this->assertSame(1, $bodyList['meta']['total']);
        $this->assertSame('User requested right to be forgotten', $bodyList['data'][0]['reason']);
    }
}
