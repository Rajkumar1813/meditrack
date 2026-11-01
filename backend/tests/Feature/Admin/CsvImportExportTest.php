<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Admin;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Cache;
use MediTrack\Core\Router;
use MediTrack\Core\Request;
use MediTrack\Core\Jwt;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\CategoryRepository;
use MediTrack\Repositories\StockMovementRepository;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Services\CsvImportExportService;

class CsvImportExportTest extends TestCase
{
    private Database $db;
    private Cache $cache;
    private Router $router;
    private string $adminToken;
    private CsvImportExportService $csvService;

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
                role TEXT NOT NULL DEFAULT 'admin',
                deleted_at TIMESTAMP NULL
            );

            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                is_active INTEGER DEFAULT 1,
                sort_order INTEGER DEFAULT 0
            );

            CREATE TABLE manufacturers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
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

            INSERT INTO users (id, name, role) VALUES (1, 'Admin', 'admin');
            INSERT INTO categories (id, name, slug) VALUES (1, 'Tablet', 'tablet');
            INSERT INTO medicines (id, name, slug, category_id) VALUES (1, 'Paracetamol 500', 'paracetamol-500', 1);
            INSERT INTO batches (id, medicine_id, batch_number, quantity, purchase_price, mrp, selling_price, mfg_date, exp_date) VALUES
            (1, 1, 'PARA-01', 50, 10.00, 15.00, 15.00, '2024-01-01', '2026-01-01');
        ");

        $this->cache = new Cache(null);
        $medRepo = new MedicineRepository($this->db, $this->cache);
        $batchRepo = new BatchRepository($this->db, $this->cache);
        $catRepo = new CategoryRepository($this->db);
        $movementRepo = new StockMovementRepository($this->db);
        $auditRepo = new AuditLogRepository($this->db);

        $this->csvService = new CsvImportExportService(
            $this->db,
            $this->cache,
            $medRepo,
            $batchRepo,
            $catRepo,
            $movementRepo,
            $auditRepo
        );

        $container = Container::getInstance();
        $container->instance(Database::class, $this->db);
        $container->instance(Cache::class, $this->cache);
        $container->instance(CsvImportExportService::class, $this->csvService);

        $userRepo = new \MediTrack\Repositories\UserRepository($this->db);
        $container->instance(\MediTrack\Repositories\UserRepository::class, $userRepo);

        $jwt = new Jwt('test-secret-key-must-be-at-least-32-chars-long!');
        $container->instance(Jwt::class, $jwt);

        $this->adminToken = $jwt->encode(['sub' => 1, 'role' => 'admin']);

        $this->router = require dirname(__DIR__, 3) . '/bootstrap/app.php';
    }

    private function dispatchAdmin(string $method, string $uri, array $body = []): \MediTrack\Core\Response
    {
        $headers = [
            'Authorization' => "Bearer {$this->adminToken}",
            'Content-Type' => 'application/json',
        ];
        $req = new Request($method, $uri, $headers, [], $body);
        return $this->router->dispatch($req);
    }

    public function testCsvExportStreamsValidCsv(): void
    {
        $res = $this->dispatchAdmin('GET', '/api/v1/admin/medicines/export');
        $this->assertSame(200, $res->getStatusCode());

        $this->assertSame('text/csv; charset=utf-8', $res->getHeaders()['Content-Type']);
        $csvOutput = $res->getContent();

        $lines = explode("\n", trim($csvOutput));
        $this->assertGreaterThanOrEqual(2, count($lines));

        // Verify header
        $this->assertStringContainsString('medicine_name', $lines[0]);
        $this->assertStringContainsString('batch_number', $lines[0]);
        $this->assertStringContainsString('purchase_price', $lines[0]);

        // Verify seeded data present
        $this->assertStringContainsString('Paracetamol 500', $lines[1]);
        $this->assertStringContainsString('PARA-01', $lines[1]);
    }

    public function testCsvImportPreviewIdentifiesErrors(): void
    {
        $csvData = "medicine_name,slug,category,manufacturer,schedule,requires_rx,pack_size,description,batch_number,quantity,purchase_price,mrp,selling_price,mfg_date,exp_date,rack,shelf,supplier\n"
                 . "Azithromycin 500,azithromycin-500,Tablet,Cipla,H,1,3 tablets,Antibiotic,AZI-01,30,45.00,60.00,55.00,2024-01-01,2026-06-01,Rack A,Shelf 1,MediDist\n"
                 . "Bad Medicine,bad-med,Tablet,GSK,OTC,0,10 tabs,Notes,BAD-01,-5,10.00,15.00,15.00,2025-05-01,2024-01-01,Rack A,Shelf 1,MediDist"; // Bad: negative qty & exp < mfg!

        $res = $this->dispatchAdmin('POST', '/api/v1/admin/medicines/import/preview', ['csv' => $csvData]);
        $this->assertSame(200, $res->getStatusCode());

        $payload = json_decode($res->getContent(), true);
        $this->assertTrue($payload['success']);
        $preview = $payload['data'];

        $this->assertSame(2, $preview['total_rows']);
        $this->assertSame(1, $preview['valid_rows']);
        $this->assertSame(1, $preview['invalid_rows']);
        $this->assertNotEmpty($preview['import_token']);

        // Check error details for row 2
        $row2 = $preview['preview'][1];
        $this->assertFalse($row2['valid']);
        $this->assertNotEmpty($row2['errors']);
    }

    public function testCsvImportCommitCreatesMedicinesAndBatches(): void
    {
        // 1. Preview a valid CSV
        $csvData = "medicine_name,slug,category,manufacturer,schedule,requires_rx,pack_size,description,batch_number,quantity,purchase_price,mrp,selling_price,mfg_date,exp_date,rack,shelf,supplier\n"
                 . "Cetirizine 10,cetirizine-10,Tablet,Sun Pharma,OTC,0,10 tablets,Allergy,CET-01,40,20.00,35.00,30.00,2024-01-01,2027-01-01,Rack B,Shelf 2,DistCo\n"
                 . "Paracetamol 500,paracetamol-500,Tablet,Cipla,OTC,0,10 tablets,Pain,PARA-01,25,10.00,15.00,15.00,2024-01-01,2026-01-01,Rack A,Shelf 1,DistCo"; // Existing batch PARA-01

        $prevRes = $this->dispatchAdmin('POST', '/api/v1/admin/medicines/import/preview', ['csv' => $csvData]);
        $token = json_decode($prevRes->getContent(), true)['data']['import_token'];

        // 2. Commit with on_duplicate = 'update'
        $commitRes = $this->dispatchAdmin('POST', '/api/v1/admin/medicines/import/commit', [
            'import_token' => $token,
            'on_duplicate' => 'update',
        ]);
        $this->assertSame(200, $commitRes->getStatusCode());
        $commitPayload = json_decode($commitRes->getContent(), true);
        $this->assertTrue($commitPayload['success']);

        $resData = $commitPayload['data'];
        $this->assertSame(1, $resData['created']); // Cetirizine created
        $this->assertSame(1, $resData['updated']); // PARA-01 updated (50 + 25 = 75)

        // Verify DB state
        $paraBatch = $this->db->fetchOne('SELECT quantity FROM batches WHERE batch_number = "PARA-01"');
        $this->assertSame(75, (int)$paraBatch['quantity']);

        $cetirizine = $this->db->fetchOne('SELECT id, name FROM medicines WHERE slug = "cetirizine-10"');
        $this->assertNotNull($cetirizine);

        // Verify stock movements logged with type 'import'
        $movements = $this->db->fetchAll('SELECT * FROM stock_movements WHERE type = "import"');
        $this->assertCount(2, $movements);
    }
}
