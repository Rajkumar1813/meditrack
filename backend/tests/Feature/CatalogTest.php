<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Router;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Cache;
use MediTrack\Repositories\CategoryRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\SaltRepository;
use MediTrack\Services\CatalogService;

class CatalogTest extends TestCase
{
    private Database $db;
    private Cache $cache;
    private Router $router;
    private CategoryRepository $categoryRepo;
    private MedicineRepository $medicineRepo;
    private BatchRepository $batchRepo;
    private SaltRepository $saltRepo;
    private CatalogService $catalogService;

    protected function setUp(): void
    {
        $this->db = new Database([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $pdo = $this->db->pdo();
        $pdo->exec("
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
        ");

        $this->seedTestData();

        $this->cache = new Cache(null); // In-memory fallback
        $this->categoryRepo = new CategoryRepository($this->db);
        $this->medicineRepo = new MedicineRepository($this->db, $this->cache);
        $this->batchRepo = new BatchRepository($this->db, $this->cache);
        $this->saltRepo = new SaltRepository($this->db);

        $this->catalogService = new CatalogService(
            $this->db,
            $this->cache,
            $this->categoryRepo,
            $this->medicineRepo,
            $this->saltRepo
        );

        $container = Container::getInstance();
        $container->instance(Database::class, $this->db);
        $container->instance(Cache::class, $this->cache);
        $container->instance(CategoryRepository::class, $this->categoryRepo);
        $container->instance(MedicineRepository::class, $this->medicineRepo);
        $container->instance(BatchRepository::class, $this->batchRepo);
        $container->instance(SaltRepository::class, $this->saltRepo);
        $container->instance(CatalogService::class, $this->catalogService);

        // Load api routes into router
        $this->router = require dirname(__DIR__, 2) . '/bootstrap/app.php';
    }

    private function seedTestData(): void
    {
        $pdo = $this->db->pdo();

        // 1. Categories
        $pdo->exec("
            INSERT INTO categories (id, name, slug, is_active) VALUES
            (1, 'Tablets', 'tablets', 1),
            (2, 'Syrups', 'syrups', 1),
            (3, 'Ointments', 'ointments', 1),
            (4, 'Archived Category', 'archived', 0);
        ");

        // 2. Manufacturers
        $pdo->exec("
            INSERT INTO manufacturers (id, name) VALUES
            (1, 'Cipla Ltd'),
            (2, 'Sun Pharma'),
            (3, 'GSK Pharmaceuticals');
        ");

        // 3. Salts
        $pdo->exec("
            INSERT INTO salts (id, name) VALUES
            (1, 'Paracetamol'),
            (2, 'Amoxicillin'),
            (3, 'Clavulanic Acid'),
            (4, 'Cetirizine');
        ");

        // 4. Medicines
        // Med 1: Paracetamol 500 (OTC, Tablets, Cipla)
        // Med 2: Amoxyclav 625 (Schedule H, Tablets, Sun Pharma)
        // Med 3: Augmentin 625 (Schedule H, Tablets, GSK)
        // Med 4: Cetirizine Syrup (Schedule H, Syrups, Cipla) - Out of stock
        // Med 5: Crocin 500 (OTC, Tablets, GSK)
        $pdo->exec("
            INSERT INTO medicines (id, name, slug, generic_name, manufacturer_id, category_id, schedule, requires_rx, pack_size, is_active) VALUES
            (1, 'Paracetamol 500', 'paracetamol-500', 'Paracetamol 500mg', 1, 1, 'OTC', 0, '10 tablets', 1),
            (2, 'Amoxyclav 625', 'amoxyclav-625', 'Amoxicillin + Clavulanic Acid', 2, 1, 'H', 1, '10 tablets', 1),
            (3, 'Augmentin 625', 'augmentin-625', 'Amoxicillin and Potassium Clavulanate', 3, 1, 'H', 1, '10 tablets', 1),
            (4, 'Cetirizine Syrup', 'cetirizine-syrup', 'Cetirizine 5mg/5ml', 1, 2, 'H', 1, '60ml bottle', 1),
            (5, 'Crocin 500', 'crocin-500', 'Paracetamol 500mg', 3, 1, 'OTC', 0, '15 tablets', 1);
        ");

        // 5. Medicine Salts
        $pdo->exec("
            INSERT INTO medicine_salt (medicine_id, salt_id, strength) VALUES
            (1, 1, '500mg'),
            (2, 2, '500mg'),
            (2, 3, '125mg'),
            (3, 2, '500mg'),
            (3, 3, '125mg'),
            (4, 4, '5mg/5ml'),
            (5, 1, '500mg');
        ");

        // 6. Batches
        // For Med 1 (Paracetamol 500):
        //   - Expired batch: exp 2020-01-01, qty 100, price 10.00 (MUST BE EXCLUDED)
        //   - FEFO batch: exp 2027-01-01, qty 50, reserved 10, price 20.00 (selling_price = 20.00, avail = 40)
        //   - Later batch: exp 2028-01-01, qty 60, reserved 0, price 25.00 (avail = 60)
        //   => Expected selling_price = 20.00, total stock = 100
        //
        // For Med 2 (Amoxyclav 625):
        //   - FEFO batch: exp 2027-06-01, qty 30, price 150.00 => stock = 30, price = 150.00
        //
        // For Med 3 (Augmentin 625):
        //   - FEFO batch: exp 2027-09-01, qty 20, price 180.00 => stock = 20, price = 180.00
        //
        // For Med 4 (Cetirizine Syrup):
        //   - Exp 2027-05-01, qty 10, reserved 10, price 45.00 => available = 0 (OUT OF STOCK)
        //
        // For Med 5 (Crocin 500):
        //   - FEFO batch: exp 2027-04-01, qty 80, price 18.00 => stock = 80, price = 18.00
        $pdo->exec("
            INSERT INTO batches (medicine_id, batch_number, quantity, reserved_quantity, purchase_price, mrp, selling_price, mfg_date, exp_date) VALUES
            (1, 'PARA-OLD', 100, 0, 8.00, 12.00, 10.00, '2019-01-01', '2020-01-01'),
            (1, 'PARA-FEFO', 50, 10, 15.00, 22.00, 20.00, '2024-01-01', '2027-01-01'),
            (1, 'PARA-LATE', 60, 0, 18.00, 28.00, 25.00, '2025-01-01', '2028-01-01'),
            (2, 'AMX-001', 30, 0, 110.00, 160.00, 150.00, '2024-06-01', '2027-06-01'),
            (3, 'AUG-001', 20, 0, 130.00, 195.00, 180.00, '2024-09-01', '2027-09-01'),
            (4, 'CET-001', 10, 10, 30.00, 50.00, 45.00, '2024-05-01', '2027-05-01'),
            (5, 'CRO-001', 80, 0, 12.00, 20.00, 18.00, '2024-04-01', '2027-04-01');
        ");
    }

    public function testGetCategoriesReturnsActiveCategoriesOnly(): void
    {
        $request = new Request('GET', '/api/v1/categories');
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $categories = $payload['data'];

        $this->assertCount(3, $categories);
        $slugs = array_column($categories, 'slug');
        $this->assertContains('tablets', $slugs);
        $this->assertContains('syrups', $slugs);
        $this->assertContains('ointments', $slugs);
        $this->assertNotContains('archived', $slugs);
    }

    public function testMedicinesSingleQueryExcludesExpiredBatchesAndCalculatesFEFOPrice(): void
    {
        $request = new Request('GET', '/api/v1/medicines?q=Paracetamol+500');
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $items = $payload['data'];
        $this->assertNotEmpty($items);

        $para = null;
        foreach ($items as $item) {
            if ($item['slug'] === 'paracetamol-500') {
                $para = $item;
                break;
            }
        }

        $this->assertNotNull($para);
        // Expired batch selling price was 10.00; later batch was 25.00.
        // FEFO selling price MUST be 20.00!
        $this->assertEquals(20.00, $para['selling_price']);

        // Stock MUST exclude expired (100) and subtract reserved (10):
        // Valid batches: (50 - 10) + (60 - 0) = 100
        $this->assertSame(100, $para['stock']);
        $this->assertTrue($para['in_stock']);
    }

    public function testMedicinesFilterBySaltReturnsMatchingMedicine(): void
    {
        // "Clavulanic" is in the salts table, not the medicine name!
        $request = new Request('GET', '/api/v1/medicines?q=Clavulanic');
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $items = $payload['data'];

        // Should return both Amoxyclav 625 and Augmentin 625
        $slugs = array_column($items, 'slug');
        $this->assertContains('amoxyclav-625', $slugs);
        $this->assertContains('augmentin-625', $slugs);
        $this->assertNotContains('paracetamol-500', $slugs);
    }

    public function testMedicinesFilterByCategoryAndRequiresRx(): void
    {
        // 1. By category slug
        $request = new Request('GET', '/api/v1/medicines?category=syrups');
        $response = $this->router->dispatch($request);
        $payload = json_decode($response->getContent(), true);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('cetirizine-syrup', $payload['data'][0]['slug']);

        // 2. By requires_rx = 1
        $requestRx = new Request('GET', '/api/v1/medicines?requires_rx=1');
        $responseRx = $this->router->dispatch($requestRx);
        $payloadRx = json_decode($responseRx->getContent(), true);
        $slugsRx = array_column($payloadRx['data'], 'slug');
        $this->assertContains('amoxyclav-625', $slugsRx);
        $this->assertContains('augmentin-625', $slugsRx);
        $this->assertContains('cetirizine-syrup', $slugsRx);
        $this->assertNotContains('paracetamol-500', $slugsRx);

        // 3. By requires_rx = 0
        $requestOtc = new Request('GET', '/api/v1/medicines?requires_rx=0');
        $responseOtc = $this->router->dispatch($requestOtc);
        $payloadOtc = json_decode($responseOtc->getContent(), true);
        $slugsOtc = array_column($payloadOtc['data'], 'slug');
        $this->assertContains('paracetamol-500', $slugsOtc);
        $this->assertContains('crocin-500', $slugsOtc);
        $this->assertNotContains('amoxyclav-625', $slugsOtc);
    }

    public function testMedicinesFilterInStock(): void
    {
        // Cetirizine has available stock = 0. It should be excluded when in_stock=1.
        $request = new Request('GET', '/api/v1/medicines?in_stock=1');
        $response = $this->router->dispatch($request);
        $payload = json_decode($response->getContent(), true);

        $slugs = array_column($payload['data'], 'slug');
        $this->assertNotContains('cetirizine-syrup', $slugs);
        $this->assertContains('paracetamol-500', $slugs);
        $this->assertContains('amoxyclav-625', $slugs);
    }

    public function testMedicinesPriceRangeFilter(): void
    {
        // Min price = 100. Should return Amoxyclav (150) and Augmentin (180).
        $request = new Request('GET', '/api/v1/medicines?min_price=100');
        $response = $this->router->dispatch($request);
        $payload = json_decode($response->getContent(), true);
        $slugs = array_column($payload['data'], 'slug');
        $this->assertContains('amoxyclav-625', $slugs);
        $this->assertContains('augmentin-625', $slugs);
        $this->assertNotContains('paracetamol-500', $slugs);

        // Max price = 22. Should return Paracetamol (20) and Crocin (18).
        $requestMax = new Request('GET', '/api/v1/medicines?max_price=22');
        $responseMax = $this->router->dispatch($requestMax);
        $payloadMax = json_decode($responseMax->getContent(), true);
        $slugsMax = array_column($payloadMax['data'], 'slug');
        $this->assertContains('paracetamol-500', $slugsMax);
        $this->assertContains('crocin-500', $slugsMax);
        $this->assertNotContains('amoxyclav-625', $slugsMax);
    }

    public function testMedicinesSortingWithIdTieBreaker(): void
    {
        // Sort price_asc: Crocin (18.00), Paracetamol (20.00), Amoxyclav (150.00), Augmentin (180.00)
        $request = new Request('GET', '/api/v1/medicines?in_stock=1&sort=price_asc');
        $response = $this->router->dispatch($request);
        $payload = json_decode($response->getContent(), true);
        $items = $payload['data'];

        $this->assertSame('crocin-500', $items[0]['slug']);
        $this->assertSame('paracetamol-500', $items[1]['slug']);
        $this->assertSame('amoxyclav-625', $items[2]['slug']);
        $this->assertSame('augmentin-625', $items[3]['slug']);

        // Sort price_desc
        $requestDesc = new Request('GET', '/api/v1/medicines?in_stock=1&sort=price_desc');
        $responseDesc = $this->router->dispatch($requestDesc);
        $payloadDesc = json_decode($responseDesc->getContent(), true);
        $itemsDesc = $payloadDesc['data'];

        $this->assertSame('augmentin-625', $itemsDesc[0]['slug']);
        $this->assertSame('amoxyclav-625', $itemsDesc[1]['slug']);
        $this->assertSame('paracetamol-500', $itemsDesc[2]['slug']);
        $this->assertSame('crocin-500', $itemsDesc[3]['slug']);
    }

    public function testMedicinesPaginationOffsetMetaAndClamp(): void
    {
        // Request page 1 with per_page 2
        $request = new Request('GET', '/api/v1/medicines?page=1&per_page=2');
        $response = $this->router->dispatch($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertCount(2, $payload['data']);
        $meta = $payload['meta']['pagination'];
        $this->assertSame('offset', $meta['type']);
        $this->assertSame(1, $meta['page']);
        $this->assertSame(2, $meta['per_page']);
        $this->assertSame(5, $meta['total']);
        $this->assertSame(3, $meta['last_page']);
        $this->assertSame(1, $meta['from']);
        $this->assertSame(2, $meta['to']);

        // Test clamping: per_page 999 must be clamped to MAX_OFFSET_PER_PAGE (100)
        $requestClamped = new Request('GET', '/api/v1/medicines?per_page=999');
        $responseClamped = $this->router->dispatch($requestClamped);
        $payloadClamped = json_decode($responseClamped->getContent(), true);
        $this->assertSame(100, $payloadClamped['meta']['pagination']['per_page']);
    }

    public function testMedicinesPaginationCursorMetaAndClamp(): void
    {
        // First cursor request with limit 2
        $request = new Request('GET', '/api/v1/medicines?type=cursor&limit=2');
        $response = $this->router->dispatch($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertCount(2, $payload['data']);
        $meta = $payload['meta']['pagination'];
        $this->assertSame('cursor', $meta['type']);
        $this->assertSame(2, $meta['limit']);
        $this->assertTrue($meta['has_more']);
        $this->assertNotEmpty($meta['next_cursor']);

        // Second request following the cursor
        $nextCursor = $meta['next_cursor'];
        $request2 = new Request('GET', "/api/v1/medicines?cursor={$nextCursor}&limit=2");
        $response2 = $this->router->dispatch($request2);
        $payload2 = json_decode($response2->getContent(), true);

        $this->assertCount(2, $payload2['data']);
        $this->assertNotSame($payload['data'][0]['id'], $payload2['data'][0]['id']);

        // Test limit clamp: limit 999 must be clamped to MAX_CURSOR_LIMIT (50)
        $requestClamp = new Request('GET', '/api/v1/medicines?type=cursor&limit=999');
        $responseClamp = $this->router->dispatch($requestClamp);
        $payloadClamp = json_decode($responseClamp->getContent(), true);
        $this->assertSame(50, $payloadClamp['meta']['pagination']['limit']);
    }

    public function testMedicinesSqlInjectionAttemptIsSafe(): void
    {
        $payloadTerm = "' OR 1=1--";
        $request = new Request('GET', '/api/v1/medicines?q=' . urlencode($payloadTerm));
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        // Query should be safely parameterized and match zero rows
        $this->assertSame(0, $payload['meta']['pagination']['total']);
        $this->assertEmpty($payload['data']);
    }

    public function testMedicineShowReturnsSlugDetailsAndAlternatives(): void
    {
        $request = new Request('GET', '/api/v1/medicines/amoxyclav-625');
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $med = $payload['data'];

        $this->assertSame('amoxyclav-625', $med['slug']);
        $this->assertTrue($med['requires_rx']);
        $this->assertEquals(150.00, $med['selling_price']);
        $this->assertSame(30, $med['stock']);

        // Check composition salts
        $this->assertCount(2, $med['salts']);
        $saltNames = array_column($med['salts'], 'name');
        $this->assertContains('Amoxicillin', $saltNames);
        $this->assertContains('Clavulanic Acid', $saltNames);

        // Check same-salt alternatives
        // Augmentin 625 shares the exact same salts, and Amoxyclav itself must NOT be in alternatives!
        $this->assertNotEmpty($med['same_salt_alternatives']);
        $altSlugs = array_column($med['same_salt_alternatives'], 'slug');
        $this->assertContains('augmentin-625', $altSlugs);
        $this->assertNotContains('amoxyclav-625', $altSlugs);
    }

    public function testMedicineShowNonExistentReturns404(): void
    {
        $this->expectException(\MediTrack\Core\Exceptions\NotFoundException::class);
        $this->expectExceptionMessage("Medicine with slug 'does-not-exist' not found.");

        $request = new Request('GET', '/api/v1/medicines/does-not-exist');
        $this->router->dispatch($request);
    }

    public function testSearchSuggestReturnsMedicinesAndSaltsWithHighlights(): void
    {
        // 1. Min 2 chars validation
        $req1 = new Request('GET', '/api/v1/search/suggest?q=p');
        $res1 = $this->router->dispatch($req1);
        $payload1 = json_decode($res1->getContent(), true);
        $this->assertEmpty($payload1['data']);

        // 2. Search "para"
        $req2 = new Request('GET', '/api/v1/search/suggest?q=para');
        $res2 = $this->router->dispatch($req2);
        $payload2 = json_decode($res2->getContent(), true);

        $this->assertTrue($payload2['success']);
        $items = $payload2['data'];
        $this->assertNotEmpty($items);
        $this->assertLessThanOrEqual(8, count($items));

        $types = array_column($items, 'type');
        $this->assertContains('medicine', $types);
        $this->assertContains('salt', $types);

        // Verify highlight structure
        $first = $items[0];
        $this->assertArrayHasKey('type', $first);
        $this->assertArrayHasKey('title', $first);
        $this->assertArrayHasKey('subtitle', $first);
        $this->assertArrayHasKey('highlight', $first);
        $this->assertSame(0, $first['highlight']['start']);
        $this->assertSame(4, $first['highlight']['length']);
    }

    public function testCatalogCacheVersioningInvalidatesOnBatchMutation(): void
    {
        // 1. Prime the catalog cache
        $req1 = new Request('GET', '/api/v1/medicines?q=Crocin+500');
        $res1 = $this->router->dispatch($req1);
        $payload1 = json_decode($res1->getContent(), true);
        $this->assertSame(80, $payload1['data'][0]['stock']);

        // 2. Add a new batch for Crocin 500 via BatchRepository
        $this->batchRepo->create([
            'medicine_id' => 5,
            'batch_number' => 'CRO-NEW-02',
            'quantity' => 40,
            'reserved_quantity' => 0,
            'purchase_price' => 12.00,
            'mrp' => 20.00,
            'selling_price' => 18.00,
            'mfg_date' => '2025-01-01',
            'exp_date' => '2028-01-01',
        ]);

        // 3. Request again: the cache was invalidated by BatchRepository bumping the catalog version!
        $req2 = new Request('GET', '/api/v1/medicines?q=Crocin+500');
        $res2 = $this->router->dispatch($req2);
        $payload2 = json_decode($res2->getContent(), true);

        // Stock should now be 80 + 40 = 120
        $this->assertSame(120, $payload2['data'][0]['stock']);
    }
}
