<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\SaltRepository;

class MedicineRepositoryTest extends TestCase
{
    private Database $db;
    private MedicineRepository $medRepo;
    private BatchRepository $batchRepo;
    private SaltRepository $saltRepo;

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

            INSERT INTO categories (id, name, slug) VALUES (1, 'Tablet', 'tablet');
        ");

        $this->medRepo = new MedicineRepository($this->db);
        $this->batchRepo = new BatchRepository($this->db);
        $this->saltRepo = new SaltRepository($this->db);
    }

    public function testAutoSettingRequiresRxBasedOnSchedule(): void
    {
        // 1. OTC medicine -> requires_rx must be 0
        $otcId = $this->medRepo->create([
            'name' => 'Paracetamol 500',
            'slug' => 'paracetamol-500',
            'category_id' => 1,
            'schedule' => 'OTC',
        ]);
        $otcMed = $this->medRepo->findById($otcId);
        $this->assertEquals(0, $otcMed['requires_rx']);

        // 2. Schedule H medicine -> requires_rx must automatically be 1
        $hId = $this->medRepo->create([
            'name' => 'Azithromycin 500',
            'slug' => 'azithromycin-500',
            'category_id' => 1,
            'schedule' => 'H',
        ]);
        $hMed = $this->medRepo->findById($hId);
        $this->assertEquals(1, $hMed['requires_rx']);

        // 3. Schedule H1 medicine -> requires_rx must automatically be 1
        $h1Id = $this->medRepo->create([
            'name' => 'Amoxyclav 625',
            'slug' => 'amoxyclav-625',
            'category_id' => 1,
            'schedule' => 'H1',
        ]);
        $h1Med = $this->medRepo->findById($h1Id);
        $this->assertEquals(1, $h1Med['requires_rx']);

        // 4. Update schedule from OTC to H should update requires_rx
        $this->medRepo->update($otcId, ['schedule' => 'H']);
        $updatedOtc = $this->medRepo->findById($otcId);
        $this->assertEquals(1, $updatedOtc['requires_rx']);
    }

    public function testSearchByMedicineNameGenericNameAndSalt(): void
    {
        $medId = $this->medRepo->create([
            'name' => 'Amoxyclav 625',
            'slug' => 'amoxyclav-625',
            'generic_name' => 'Amoxicillin with Clavulanate',
            'category_id' => 1,
            'schedule' => 'H1',
        ]);

        $saltId = $this->saltRepo->findOrCreate('Clavulanic Acid');
        $this->saltRepo->attachToMedicine($medId, $saltId, '125 mg');

        // Search by name
        $results1 = $this->medRepo->search('Amoxyclav');
        $this->assertCount(1, $results1);
        $this->assertSame('Amoxyclav 625', $results1[0]['name']);

        // Search by generic name
        $results2 = $this->medRepo->search('Amoxicillin');
        $this->assertCount(1, $results2);
        $this->assertSame('Amoxyclav 625', $results2[0]['name']);

        // Search by salt
        $results3 = $this->medRepo->search('Clavulanic');
        $this->assertCount(1, $results3);
        $this->assertSame('Amoxyclav 625', $results3[0]['name']);
    }

    public function testInStockAndLowStockQueryHelpers(): void
    {
        $med1 = $this->medRepo->create([
            'name' => 'Medicine Ample Stock',
            'slug' => 'medicine-ample',
            'category_id' => 1,
            'schedule' => 'OTC',
        ]);

        $med2 = $this->medRepo->create([
            'name' => 'Medicine Low Stock',
            'slug' => 'medicine-low',
            'category_id' => 1,
            'schedule' => 'OTC',
        ]);

        // Med 1: 50 in stock (unexpired)
        $this->batchRepo->create([
            'medicine_id' => $med1,
            'batch_number' => 'B-AMP-01',
            'quantity' => 50,
            'purchase_price' => 10,
            'mrp' => 20,
            'mfg_date' => date('Y-m-d', strtotime('-30 days')),
            'exp_date' => date('Y-m-d', strtotime('+100 days')),
        ]);

        // Med 2: 5 in stock (unexpired) + 100 expired
        $this->batchRepo->create([
            'medicine_id' => $med2,
            'batch_number' => 'B-LOW-ACTIVE',
            'quantity' => 5,
            'purchase_price' => 10,
            'mrp' => 20,
            'mfg_date' => date('Y-m-d', strtotime('-30 days')),
            'exp_date' => date('Y-m-d', strtotime('+100 days')),
        ]);
        $this->batchRepo->create([
            'medicine_id' => $med2,
            'batch_number' => 'B-LOW-EXPIRED',
            'quantity' => 100,
            'purchase_price' => 10,
            'mrp' => 20,
            'mfg_date' => date('Y-m-d', strtotime('-400 days')),
            'exp_date' => date('Y-m-d', strtotime('-10 days')),
        ]);

        // In-stock: both have active stock > 0
        $inStock = $this->medRepo->inStock();
        $this->assertCount(2, $inStock);

        // Low stock with threshold 10: only Med 2 has available active stock <= 10
        $lowStock = $this->medRepo->lowStock(10);
        $this->assertCount(1, $lowStock);
        $this->assertSame('Medicine Low Stock', $lowStock[0]['name']);
        $this->assertEquals(5, $lowStock[0]['available_stock']);
    }

    public function testExpiringWithinAndExpiredQueryHelpers(): void
    {
        $medId = $this->medRepo->create([
            'name' => 'Test Expiry Medicine',
            'slug' => 'test-expiry',
            'category_id' => 1,
            'schedule' => 'OTC',
        ]);

        // 1. Expired batch (-10 days)
        $this->batchRepo->create([
            'medicine_id' => $medId,
            'batch_number' => 'B-EXPIRED',
            'quantity' => 20,
            'purchase_price' => 10,
            'mrp' => 20,
            'mfg_date' => date('Y-m-d', strtotime('-100 days')),
            'exp_date' => date('Y-m-d', strtotime('-10 days')),
        ]);

        // 2. Expiring in 7 days
        $this->batchRepo->create([
            'medicine_id' => $medId,
            'batch_number' => 'B-EXP-7D',
            'quantity' => 15,
            'purchase_price' => 10,
            'mrp' => 20,
            'mfg_date' => date('Y-m-d', strtotime('-50 days')),
            'exp_date' => date('Y-m-d', strtotime('+7 days')),
        ]);

        // 3. Expiring in 45 days
        $this->batchRepo->create([
            'medicine_id' => $medId,
            'batch_number' => 'B-EXP-45D',
            'quantity' => 30,
            'purchase_price' => 10,
            'mrp' => 20,
            'mfg_date' => date('Y-m-d', strtotime('-20 days')),
            'exp_date' => date('Y-m-d', strtotime('+45 days')),
        ]);

        // Test expired()
        $expired = $this->medRepo->expired();
        $this->assertCount(1, $expired);
        $this->assertSame('B-EXPIRED', $expired[0]['batch_number']);

        // Test expiringWithin(10) -> should match B-EXP-7D only
        $expiring10 = $this->medRepo->expiringWithin(10);
        $this->assertCount(1, $expiring10);
        $this->assertSame('B-EXP-7D', $expiring10[0]['batch_number']);

        // Test expiringWithin(50) -> should match both B-EXP-7D and B-EXP-45D
        $expiring50 = $this->medRepo->expiringWithin(50);
        $this->assertCount(2, $expiring50);
    }
}

