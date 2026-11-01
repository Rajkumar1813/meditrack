<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Validators;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Validators\BatchValidator;

class BatchValidatorTest extends TestCase
{
    private Database $db;
    private BatchRepository $batchRepo;
    private BatchValidator $validator;
    private int $medicineId;

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

        $medRepo = new MedicineRepository($this->db);
        $this->medicineId = $medRepo->create([
            'name' => 'Paracetamol 500',
            'slug' => 'paracetamol-500',
            'category_id' => 1,
            'schedule' => 'OTC',
        ]);

        $this->batchRepo = new BatchRepository($this->db);
        $this->validator = new BatchValidator($this->batchRepo);
    }

    public function testValidatorRejectsDuplicateBatch(): void
    {
        // Insert first batch in repo
        $this->batchRepo->create([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'PAR-101',
            'quantity' => 100,
            'purchase_price' => 10.00,
            'mrp' => 20.00,
            'selling_price' => 20.00,
            'mfg_date' => '2026-01-01',
            'exp_date' => '2026-12-31',
        ]);

        // Attempt validating duplicate batch
        $res = $this->validator->validate([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'PAR-101',
            'quantity' => 50,
            'purchase_price' => 12.00,
            'mrp' => 22.00,
            'mfg_date' => '2026-02-01',
            'exp_date' => '2027-01-01',
        ]);

        $this->assertTrue($res->fails());
        $this->assertArrayHasKey('batch_number', $res->errors());
        $this->assertStringContainsString('already exists', $res->errors()['batch_number'][0]);
    }

    public function testValidatorRejectsExpiryDateLessOrEqualToMfgDate(): void
    {
        $res = $this->validator->validate([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'PAR-INVALID-DATE',
            'quantity' => 50,
            'purchase_price' => 10.00,
            'mrp' => 20.00,
            'mfg_date' => '2026-12-31',
            'exp_date' => '2026-01-01',
        ]);

        $this->assertTrue($res->fails());
        $this->assertArrayHasKey('exp_date', $res->errors());
        $this->assertStringContainsString('must be after', $res->errors()['exp_date'][0]);
    }

    public function testValidatorRejectsNegativeQuantity(): void
    {
        $res = $this->validator->validate([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'PAR-NEG-QTY',
            'quantity' => -10,
            'purchase_price' => 10.00,
            'mrp' => 20.00,
            'mfg_date' => '2026-01-01',
            'exp_date' => '2026-12-31',
        ]);

        $this->assertTrue($res->fails());
        $this->assertArrayHasKey('quantity', $res->errors());
        $this->assertStringContainsString('cannot be negative', $res->errors()['quantity'][0]);
    }

    public function testValidatorRejectsMrpLessThanPurchasePrice(): void
    {
        $res = $this->validator->validate([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'PAR-LOW-MRP',
            'quantity' => 50,
            'purchase_price' => 30.00,
            'mrp' => 20.00, // MRP < purchase price
            'mfg_date' => '2026-01-01',
            'exp_date' => '2026-12-31',
        ]);

        $this->assertTrue($res->fails());
        $this->assertArrayHasKey('mrp', $res->errors());
        $this->assertStringContainsString('greater than or equal to purchase price', $res->errors()['mrp'][0]);
    }

    public function testValidatorAcceptsValidBatch(): void
    {
        $res = $this->validator->validate([
            'medicine_id' => $this->medicineId,
            'batch_number' => 'PAR-VALID',
            'quantity' => 100,
            'purchase_price' => 15.00,
            'mrp' => 25.00,
            'mfg_date' => '2026-01-01',
            'exp_date' => '2026-12-31',
        ]);

        $this->assertTrue($res->passes());
        $this->assertEmpty($res->errors());
    }
}
