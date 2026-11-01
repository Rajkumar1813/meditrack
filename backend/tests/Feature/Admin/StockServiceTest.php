<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Admin;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Core\Cache;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Repositories\StockMovementRepository;
use MediTrack\Services\StockService;

class StockServiceTest extends TestCase
{
    private Database $db;
    private Cache $cache;
    private StockMovementRepository $movementRepo;
    private StockService $stockService;

    protected function setUp(): void
    {
        $this->db = new Database([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $pdo = $this->db->pdo();
        $pdo->exec("
            CREATE TABLE medicines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                category_id INTEGER NOT NULL,
                schedule TEXT NOT NULL DEFAULT 'OTC',
                requires_rx INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER DEFAULT 1,
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
                rack_id INTEGER NULL,
                shelf_id INTEGER NULL,
                supplier_id INTEGER NULL,
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

            INSERT INTO medicines (id, name, slug, category_id) VALUES (1, 'Paracetamol 500', 'paracetamol-500', 1);

            -- Batch 1: EXPIRED (exp 2020-01-01, qty 50) -> MUST NEVER BE ALLOCATED
            -- Batch 2: Earlier unexpired batch (exp 2027-01-01, qty 30, selling_price 20.00)
            -- Batch 3: Later unexpired batch (exp 2028-01-01, qty 40, selling_price 25.00)
            INSERT INTO batches (id, medicine_id, batch_number, quantity, reserved_quantity, purchase_price, mrp, selling_price, mfg_date, exp_date) VALUES
            (1, 1, 'B-EXPIRED', 50, 0, 10.00, 15.00, 15.00, '2019-01-01', '2020-01-01'),
            (2, 1, 'B-FEFO-EARLY', 30, 0, 15.00, 22.00, 20.00, '2024-01-01', '2027-01-01'),
            (3, 1, 'B-FEFO-LATE', 40, 0, 18.00, 28.00, 25.00, '2025-01-01', '2028-01-01');
        ");

        $this->cache = new Cache(null);
        $this->movementRepo = new StockMovementRepository($this->db);
        $this->stockService = new StockService($this->db, $this->cache, $this->movementRepo);
    }

    public function testStockServiceAllocatesEarliestExpiryFirst(): void
    {
        // Total unexpired available: 30 + 40 = 70. Request 40.
        // It must take ALL 30 from B-FEFO-EARLY (batch 2), and remaining 10 from B-FEFO-LATE (batch 3).
        $allocations = $this->stockService->allocate(1, 40, 99, 'order', 101);

        $this->assertCount(2, $allocations);

        // First allocation from Batch 2
        $this->assertSame(2, $allocations[0]['batch_id']);
        $this->assertSame('B-FEFO-EARLY', $allocations[0]['batch_number']);
        $this->assertSame(30, $allocations[0]['quantity']);

        // Second allocation from Batch 3
        $this->assertSame(3, $allocations[1]['batch_id']);
        $this->assertSame('B-FEFO-LATE', $allocations[1]['batch_number']);
        $this->assertSame(10, $allocations[1]['quantity']);

        // Verify DB updates
        $b2 = $this->db->fetchOne('SELECT reserved_quantity FROM batches WHERE id = 2');
        $this->assertSame(30, (int)$b2['reserved_quantity']);

        $b3 = $this->db->fetchOne('SELECT reserved_quantity FROM batches WHERE id = 3');
        $this->assertSame(10, (int)$b3['reserved_quantity']);

        // Verify stock movement logs
        $movements = $this->db->fetchAll('SELECT * FROM stock_movements WHERE ref_id = 101 ORDER BY id ASC');
        $this->assertCount(2, $movements);
        $this->assertSame('sale', $movements[0]['type']);
        $this->assertSame(-30, (int)$movements[0]['qty_change']);
        $this->assertSame(-10, (int)$movements[1]['qty_change']);
    }

    public function testStockServiceNeverAllocatesExpiredBatches(): void
    {
        // Batch 1 has 50 units but is expired.
        // Unexpired total is 70.
        // Request 60.
        $allocations = $this->stockService->allocate(1, 60);

        $allocatedBatchIds = array_column($allocations, 'batch_id');
        $this->assertNotContains(1, $allocatedBatchIds); // Expired batch ID 1 must NOT be allocated

        $b1 = $this->db->fetchOne('SELECT reserved_quantity FROM batches WHERE id = 1');
        $this->assertSame(0, (int)$b1['reserved_quantity']);
    }

    public function testStockServiceRejectsInsufficientStock(): void
    {
        // Total unexpired available is 70 (expired 50 is ignored).
        // Requesting 75 must throw ValidationException.
        try {
            $this->stockService->allocate(1, 75);
            $this->fail('Expected ValidationException for insufficient stock not thrown');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertArrayHasKey('stock', $e->getErrors());
            $this->assertStringContainsString('Insufficient stock', $e->getErrors()['stock'][0]);
        }
    }

    public function testStockServiceReleaseRestoresStock(): void
    {
        // First allocate 20 from Batch 2
        $this->stockService->allocate(1, 20);

        $b2Before = $this->db->fetchOne('SELECT reserved_quantity FROM batches WHERE id = 2');
        $this->assertSame(20, (int)$b2Before['reserved_quantity']);

        // Release 15 back
        $released = $this->stockService->release(2, 15, 99, 'order_cancel', 101);
        $this->assertTrue($released);

        $b2After = $this->db->fetchOne('SELECT reserved_quantity FROM batches WHERE id = 2');
        $this->assertSame(5, (int)$b2After['reserved_quantity']);

        // Verify stock movement log
        $log = $this->db->fetchOne("SELECT * FROM stock_movements WHERE type = 'cancel_restock' ORDER BY id DESC LIMIT 1");
        $this->assertNotNull($log);
        $this->assertSame(15, (int)$log['qty_change']);
    }

    public function testStockServiceRestockIncreasesQuantity(): void
    {
        $b2Before = $this->db->fetchOne('SELECT quantity FROM batches WHERE id = 2');
        $this->assertSame(30, (int)$b2Before['quantity']);

        $restocked = $this->stockService->restock(2, 50, 'Supplier shipment received', 99);
        $this->assertTrue($restocked);

        $b2After = $this->db->fetchOne('SELECT quantity FROM batches WHERE id = 2');
        $this->assertSame(80, (int)$b2After['quantity']);

        $log = $this->db->fetchOne("SELECT * FROM stock_movements WHERE type = 'purchase' AND batch_id = 2 ORDER BY id DESC LIMIT 1");
        $this->assertSame(50, (int)$log['qty_change']);
    }

    public function testStockServiceDisposeMarksBatchAndZeroesQuantity(): void
    {
        $disposed = $this->stockService->dispose(1, 'disposed', 'Expired on shelf', 99);
        $this->assertTrue($disposed);

        $b1 = $this->db->fetchOne('SELECT quantity, disposal_status, notes FROM batches WHERE id = 1');
        $this->assertSame(0, (int)$b1['quantity']);
        $this->assertSame('disposed', $b1['disposal_status']);
        $this->assertSame('Expired on shelf', $b1['notes']);

        $log = $this->db->fetchOne("SELECT * FROM stock_movements WHERE type = 'dispose' AND batch_id = 1");
        $this->assertNotNull($log);
        $this->assertSame(-50, (int)$log['qty_change']);
    }

    public function testParallelAllocationDoesNotOversell(): void
    {
        // Batch 2 has 30 units, Batch 3 has 40 units (total unexpired 70).
        // First allocation requests 50 units.
        $alloc1 = $this->stockService->allocate(1, 50);
        $this->assertNotEmpty($alloc1);

        // Remaining unexpired units is now 20.
        // A second parallel allocation requests 25 units.
        // It must be rejected because only 20 units remain!
        try {
            $this->stockService->allocate(1, 25);
            $this->fail('Expected ValidationException for oversell attempt not thrown');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertArrayHasKey('stock', $e->getErrors());
        }

        // A third allocation requests exactly 20 units: it must succeed!
        $alloc3 = $this->stockService->allocate(1, 20);
        $this->assertNotEmpty($alloc3);

        // Now total remaining unexpired is 0. Requesting even 1 unit fails!
        try {
            $this->stockService->allocate(1, 1);
            $this->fail('Expected ValidationException when stock completely depleted');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
