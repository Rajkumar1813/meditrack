<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Admin;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Router;
use MediTrack\Core\Request;
use MediTrack\Core\Jwt;
use MediTrack\Core\Lang;
use MediTrack\Repositories\UserRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Repositories\StockMovementRepository;
use MediTrack\Repositories\CategoryRepository;
use MediTrack\Repositories\SaltRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Validators\BatchValidator;
use MediTrack\Validators\MedicineValidator;

class ValidationRulesTest extends TestCase
{
    private Database $db;
    private Router $router;
    private Jwt $jwt;
    private string $adminToken;

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
                name TEXT NOT NULL,
                email TEXT NULL UNIQUE,
                phone TEXT NULL UNIQUE,
                role TEXT NOT NULL DEFAULT 'admin',
                locale TEXT NOT NULL DEFAULT 'en',
                is_blocked INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            );

            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                icon TEXT NULL,
                sort_order INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1
            );

            CREATE TABLE manufacturers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
            );

            CREATE TABLE salts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
            );

            CREATE TABLE medicine_salt (
                medicine_id INTEGER NOT NULL,
                salt_id INTEGER NOT NULL,
                strength TEXT NULL,
                PRIMARY KEY (medicine_id, salt_id)
            );

            CREATE TABLE racks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
            );

            CREATE TABLE shelves (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rack_id INTEGER NOT NULL,
                name TEXT NOT NULL
            );

            CREATE TABLE suppliers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
            );

            CREATE TABLE medicines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                generic_name TEXT NULL,
                manufacturer_id INTEGER NULL,
                category_id INTEGER NOT NULL,
                schedule TEXT NOT NULL DEFAULT 'OTC',
                requires_rx INTEGER NOT NULL DEFAULT 0,
                pack_size TEXT NULL,
                gst_percent NUMERIC DEFAULT 12.00,
                description TEXT NULL,
                usage_info TEXT NULL,
                side_effects TEXT NULL,
                storage_info TEXT NULL,
                image TEXT NULL,
                is_active INTEGER DEFAULT 1,
                is_featured INTEGER DEFAULT 0,
                notes TEXT NULL,
                created_by INTEGER NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
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
                supplier_id INTEGER NULL,
                rack_id INTEGER NULL,
                shelf_id INTEGER NULL,
                disposal_status TEXT NOT NULL DEFAULT 'none',
                notes TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE (medicine_id, batch_number)
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

            CREATE TABLE settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT NOT NULL UNIQUE,
                value TEXT NULL,
                updated_at TIMESTAMP NULL
            );

            INSERT INTO users (id, name, email, role) VALUES (1, 'Admin', 'admin@example.com', 'admin');
            INSERT INTO categories (id, name, slug) VALUES (1, 'Tablets', 'tablets');
            INSERT INTO medicines (id, name, slug, category_id, schedule) VALUES (1, 'Paracetamol 500', 'paracetamol-500', 1, 'OTC');
        ");

        $container = Container::getInstance();
        $container->instance(Database::class, $this->db);

        $userRepo = new UserRepository($this->db);
        $container->instance(UserRepository::class, $userRepo);

        $this->jwt = new Jwt('test-secret-key-must-be-at-least-32-chars-long!');
        $container->instance(Jwt::class, $this->jwt);

        $this->adminToken = $this->jwt->encode(['sub' => 1, 'role' => 'admin']);

        $this->router = require dirname(__DIR__, 3) . '/bootstrap/app.php';
    }

    private function dispatchAdmin(string $method, string $uri, array $body = [], array $headers = []): \MediTrack\Core\Response
    {
        $headers = array_merge([
            'Authorization' => "Bearer {$this->adminToken}",
            'Content-Type' => 'application/json',
        ], $headers);

        $req = new Request($method, $uri, $headers, [], $body);
        return $this->router->dispatch($req);
    }

    public function testExpiryBeforeMfgIsRejected(): void
    {
        $payload = [
            'batch_number' => 'B-ERR-01',
            'quantity' => 10,
            'purchase_price' => 10.00,
            'mrp' => 15.00,
            'selling_price' => 14.00,
            'mfg_date' => '2025-05-01',
            'exp_date' => '2025-04-01', // Before Mfg!
        ];

        try {
            $this->dispatchAdmin('POST', '/api/v1/admin/medicines/1/batches', $payload);
            $this->fail('Expected ValidationException not thrown');
        } catch (\MediTrack\Core\Exceptions\ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertArrayHasKey('exp_date', $e->getErrors());
        }
    }

    public function testDuplicateBatchNumberForSameMedicineIsRejected(): void
    {
        $payload = [
            'batch_number' => 'B-DUP-01',
            'quantity' => 20,
            'purchase_price' => 10.00,
            'mrp' => 15.00,
            'selling_price' => 14.00,
            'mfg_date' => '2024-01-01',
            'exp_date' => '2026-01-01',
        ];

        $res1 = $this->dispatchAdmin('POST', '/api/v1/admin/medicines/1/batches', $payload);
        $this->assertSame(201, $res1->getStatusCode());

        // Second attempt with same batch number
        try {
            $this->dispatchAdmin('POST', '/api/v1/admin/medicines/1/batches', $payload);
            $this->fail('Expected ValidationException for duplicate batch not thrown');
        } catch (\MediTrack\Core\Exceptions\ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertArrayHasKey('batch_number', $e->getErrors());
        }
    }

    public function testNegativeQuantityIsRejected(): void
    {
        $payload = [
            'batch_number' => 'B-NEG-01',
            'quantity' => -5, // Negative quantity!
            'purchase_price' => 10.00,
            'mrp' => 15.00,
            'selling_price' => 14.00,
            'mfg_date' => '2024-01-01',
            'exp_date' => '2026-01-01',
        ];

        try {
            $this->dispatchAdmin('POST', '/api/v1/admin/medicines/1/batches', $payload);
            $this->fail('Expected ValidationException for negative quantity not thrown');
        } catch (\MediTrack\Core\Exceptions\ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertArrayHasKey('quantity', $e->getErrors());
        }
    }

    public function testMrpLessThanPurchasePriceIsRejected(): void
    {
        $payload = [
            'batch_number' => 'B-MRP-01',
            'quantity' => 10,
            'purchase_price' => 50.00,
            'mrp' => 40.00, // MRP < purchase price!
            'selling_price' => 40.00,
            'mfg_date' => '2024-01-01',
            'exp_date' => '2026-01-01',
        ];

        try {
            $this->dispatchAdmin('POST', '/api/v1/admin/medicines/1/batches', $payload);
            $this->fail('Expected ValidationException for mrp < purchase not thrown');
        } catch (\MediTrack\Core\Exceptions\ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertArrayHasKey('mrp', $e->getErrors());
        }
    }

    public function testSellingPriceGreaterThanMrpIsRejected(): void
    {
        $payload = [
            'batch_number' => 'B-SELL-01',
            'quantity' => 10,
            'purchase_price' => 20.00,
            'mrp' => 30.00,
            'selling_price' => 35.00, // Selling price > MRP!
            'mfg_date' => '2024-01-01',
            'exp_date' => '2026-01-01',
        ];

        try {
            $this->dispatchAdmin('POST', '/api/v1/admin/medicines/1/batches', $payload);
            $this->fail('Expected ValidationException for selling > mrp not thrown');
        } catch (\MediTrack\Core\Exceptions\ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertArrayHasKey('selling_price', $e->getErrors());
        }
    }

    public function testHindiValidationMessages(): void
    {
        $lang = Container::getInstance()->get(Lang::class);
        $lang->setLocale('hi');

        $payload = [
            'batch_number' => 'B-HI-01',
            'quantity' => 10,
            'purchase_price' => 50.00,
            'mrp' => 30.00, // MRP < purchase
            'mfg_date' => '2024-01-01',
            'exp_date' => '2026-01-01',
        ];

        try {
            $this->dispatchAdmin('POST', '/api/v1/admin/medicines/1/batches', $payload, ['Accept-Language' => 'hi']);
            $this->fail('Expected ValidationException');
        } catch (\MediTrack\Core\Exceptions\ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('mrp', $errors);
            // Verify Hindi message presence
            $this->assertStringContainsString('एमआरपी', $errors['mrp'][0]);
        } finally {
            $lang->setLocale('en');
        }
    }
}
