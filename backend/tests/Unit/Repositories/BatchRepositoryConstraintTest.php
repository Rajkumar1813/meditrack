<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Repositories;

use PDOException;
use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\MedicineRepository;

class BatchRepositoryConstraintTest extends TestCase
{
    private Database $db;
    private BatchRepository $batchRepo;
    private MedicineRepository $medRepo;
    private int $medicineId;

    protected function setUp(): void
    {
        $this->db = new Database([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $pdo = $this->db->pdo();
        $pdo->exec('PRAGMA foreign_keys = ON;');

        $pdo->exec("
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                icon TEXT NULL,
                sort_order INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1
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
                name TEXT NOT NULL UNIQUE,
                phone TEXT NULL,
                email TEXT NULL,
                gstin TEXT NULL
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
                UNIQUE (medicine_id, batch_number),
                CONSTRAINT chk_qty CHECK (quantity >= 0),
                CONSTRAINT chk_dates CHECK (exp_date > mfg_date),
                CONSTRAINT chk_price CHECK (mrp >= purchase_price AND selling_price <= mrp)
            );
        ");

        $this->batchRepo = new BatchRepository($this->db);
        $this->medRepo = new MedicineRepository($this->db);

        $this->medicineId = $this->medRepo->create([
            'name' => 'Paracetamol 500',
            'slug' => 'paracetamol-500',
            'category_id' => 1,
            'schedule' => 'OTC',
        ]);
    }

    public function testDatabaseRejectsDuplicateBatchForSameMedicine(): void
    {
        $this->batchRepo->create([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'BATCH-001',
            'quantity' => 50,
            'purchase_price' => 10.00,
            'mrp' => 20.00,
            'selling_price' => 20.00,
            'mfg_date' => '2026-01-01',
            'exp_date' => '2026-12-31',
        ]);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/UNIQUE constraint failed|Duplicate entry/i');

        $this->batchRepo->create([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'BATCH-001',
            'quantity' => 10,
            'purchase_price' => 12.00,
            'mrp' => 22.00,
            'selling_price' => 22.00,
            'mfg_date' => '2026-02-01',
            'exp_date' => '2027-01-01',
        ]);
    }

    public function testDatabaseRejectsExpiryDateLessOrEqualToMfgDate(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/CHECK constraint failed.*chk_dates/i');

        $this->batchRepo->create([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'BATCH-INVALID-DATE',
            'quantity' => 50,
            'purchase_price' => 10.00,
            'mrp' => 20.00,
            'selling_price' => 20.00,
            'mfg_date' => '2026-12-31',
            'exp_date' => '2026-01-01', // Before mfg_date!
        ]);
    }

    public function testDatabaseRejectsNegativeQuantity(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/CHECK constraint failed.*chk_qty/i');

        $this->batchRepo->create([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'BATCH-NEG-QTY',
            'quantity' => -5, // Negative quantity!
            'purchase_price' => 10.00,
            'mrp' => 20.00,
            'selling_price' => 20.00,
            'mfg_date' => '2026-01-01',
            'exp_date' => '2026-12-31',
        ]);
    }

    public function testDatabaseRejectsMrpLessThanPurchasePrice(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/CHECK constraint failed.*chk_price/i');

        $this->batchRepo->create([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'BATCH-INVALID-PRICE',
            'quantity' => 50,
            'purchase_price' => 30.00,
            'mrp' => 20.00, // MRP < purchase price!
            'selling_price' => 20.00,
            'mfg_date' => '2026-01-01',
            'exp_date' => '2026-12-31',
        ]);
    }

    public function testDatabaseRejectsSellingPriceGreaterThanMrp(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/CHECK constraint failed.*chk_price/i');

        $this->batchRepo->create([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'BATCH-EXPENSIVE',
            'quantity' => 50,
            'purchase_price' => 15.00,
            'mrp' => 25.00,
            'selling_price' => 30.00, // Selling price > MRP!
            'mfg_date' => '2026-01-01',
            'exp_date' => '2026-12-31',
        ]);
    }

    public function testFefoBatchOrdering(): void
    {
        // Batch 1: expires in 60 days
        $this->batchRepo->create([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'BATCH-60D',
            'quantity' => 20,
            'purchase_price' => 10.00,
            'mrp' => 20.00,
            'selling_price' => 20.00,
            'mfg_date' => date('Y-m-d', strtotime('-30 days')),
            'exp_date' => date('Y-m-d', strtotime('+60 days')),
        ]);

        // Batch 2: expires in 10 days (earlier expiry!)
        $this->batchRepo->create([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'BATCH-10D',
            'quantity' => 15,
            'purchase_price' => 10.00,
            'mrp' => 20.00,
            'selling_price' => 20.00,
            'mfg_date' => date('Y-m-d', strtotime('-30 days')),
            'exp_date' => date('Y-m-d', strtotime('+10 days')),
        ]);

        // Batch 3: expires in 120 days
        $this->batchRepo->create([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'BATCH-120D',
            'quantity' => 30,
            'purchase_price' => 10.00,
            'mrp' => 20.00,
            'selling_price' => 20.00,
            'mfg_date' => date('Y-m-d', strtotime('-30 days')),
            'exp_date' => date('Y-m-d', strtotime('+120 days')),
        ]);

        $available = $this->batchRepo->findAvailableByMedicine($this->medicineId);

        $this->assertCount(3, $available);
        // FEFO: earliest expiry first
        $this->assertSame('BATCH-10D', $available[0]['batch_number']);
        $this->assertSame('BATCH-60D', $available[1]['batch_number']);
        $this->assertSame('BATCH-120D', $available[2]['batch_number']);
    }

    public function testReserveAndDeductQuantity(): void
    {
        $batchId = $this->batchRepo->create([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'BATCH-RESERVE',
            'quantity' => 50,
            'purchase_price' => 10.00,
            'mrp' => 20.00,
            'selling_price' => 20.00,
            'mfg_date' => '2026-01-01',
            'exp_date' => '2026-12-31',
        ]);

        // Reserve 10
        $reserved = $this->batchRepo->reserveQuantity($batchId, 10);
        $this->assertTrue($reserved);

        $batch = $this->batchRepo->findById($batchId);
        $this->assertEquals(10, $batch['reserved_quantity']);
        $this->assertEquals(50, $batch['quantity']);

        // Over-reserve beyond available stock (50 - 10 = 40 available, trying 45) should fail
        $overReserve = $this->batchRepo->reserveQuantity($batchId, 45);
        $this->assertFalse($overReserve);

        // Deduct 10 on dispatch
        $deducted = $this->batchRepo->deductQuantity($batchId, 10);
        $this->assertTrue($deducted);

        $batch = $this->batchRepo->findById($batchId);
        $this->assertEquals(40, $batch['quantity']);
        $this->assertEquals(0, $batch['reserved_quantity']);
    }
}

