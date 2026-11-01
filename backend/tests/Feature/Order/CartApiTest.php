<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Order;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Core\Cache;
use MediTrack\Repositories\CartRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Services\CartService;
use MediTrack\Core\Request;
use MediTrack\Controllers\Api\V1\CartController;

class CartApiTest extends TestCase
{
    private Database $db;
    private CartRepository $cartRepo;
    private MedicineRepository $medRepo;
    private SettingRepository $settingRepo;
    private CartService $cartService;
    private CartController $controller;

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

            INSERT INTO settings (`key`, value) VALUES ('delivery_fee', '50.00');

            -- Medicine 1: OTC, in stock
            INSERT INTO medicines (id, name, slug, schedule, requires_rx, gst_percent)
            VALUES (1, 'Paracetamol 500', 'paracetamol-500', 'OTC', 0, 12.00);

            -- Batch for Med 1: 50 units @ 20.00 (mrp 25.00), exp 2028-01-01
            INSERT INTO batches (id, medicine_id, batch_number, quantity, reserved_quantity, purchase_price, mrp, selling_price, mfg_date, exp_date)
            VALUES (1, 1, 'BATCH-001', 50, 0, 15.00, 25.00, 20.00, '2025-01-01', '2028-01-01');

            -- Medicine 2: Rx required, low stock (qty 2)
            INSERT INTO medicines (id, name, slug, schedule, requires_rx, gst_percent)
            VALUES (2, 'Amoxicillin 250', 'amoxicillin-250', 'H', 1, 12.00);

            INSERT INTO batches (id, medicine_id, batch_number, quantity, reserved_quantity, purchase_price, mrp, selling_price, mfg_date, exp_date)
            VALUES (2, 2, 'BATCH-002', 2, 0, 30.00, 45.00, 40.00, '2025-01-01', '2027-01-01');
        ");

        $this->cartRepo = new CartRepository($this->db);
        $this->medRepo = new MedicineRepository($this->db, new Cache(null));
        $this->settingRepo = new SettingRepository($this->db);
        $this->cartService = new CartService($this->cartRepo, $this->medRepo, $this->settingRepo);
        $this->controller = new CartController($this->cartService);
    }

    public function testEmptyCartReturnsZeroTotals(): void
    {
        $summary = $this->cartService->getSummary(101);

        $this->assertSame(0, $summary['item_count']);
        $this->assertSame(0.0, $summary['subtotal']);
        $this->assertSame(0.0, $summary['delivery_fee']);
        $this->assertSame(0.0, $summary['total']);
        $this->assertFalse($summary['has_rx_items']);
        $this->assertTrue($summary['all_in_stock']);
    }

    public function testAddItemCalculatesTotalsGstAndDeliveryFee(): void
    {
        // Add 2 units of Paracetamol (2 * 20.00 = 40.00)
        $summary = $this->cartService->addItem(101, 1, 2);

        $this->assertSame(2, $summary['item_count']);
        $this->assertSame(40.00, $summary['subtotal']);
        $this->assertSame(50.00, $summary['delivery_fee']); // subtotal < 500, delivery fee applied
        $this->assertSame(90.00, $summary['total']);
        $this->assertGreaterThan(0, $summary['gst_total']);
        $this->assertFalse($summary['has_rx_items']);
        $this->assertTrue($summary['all_in_stock']);

        // Adding same item increments quantity
        $summary = $this->cartService->addItem(101, 1, 1);
        $this->assertSame(3, $summary['item_count']);
        $this->assertSame(60.00, $summary['subtotal']);
    }

    public function testRxFlagDetectedWhenRxItemInCart(): void
    {
        $this->cartService->addItem(101, 2, 1);
        $summary = $this->cartService->getSummary(101);

        $this->assertTrue($summary['has_rx_items']);
    }

    public function testOutOfStockDetectionWhenRequestedExceedsAvailable(): void
    {
        // Med 2 only has 2 units in stock; request 3
        $this->cartService->addItem(101, 2, 3);
        $summary = $this->cartService->getSummary(101);

        $this->assertFalse($summary['all_in_stock']);
        $this->assertFalse($summary['items'][0]['in_stock']);
    }

    public function testUpdateItemAndRemoveItem(): void
    {
        $this->cartService->addItem(101, 1, 5);
        $summary = $this->cartService->updateItem(101, 1, 2);
        $this->assertSame(2, $summary['item_count']);

        // Updating to 0 removes item
        $summary = $this->cartService->updateItem(101, 1, 0);
        $this->assertSame(0, $summary['item_count']);
    }

    public function testClearCart(): void
    {
        $this->cartService->addItem(101, 1, 2);
        $this->cartService->addItem(101, 2, 1);
        $this->assertSame(3, $this->cartService->getSummary(101)['item_count']);

        $summary = $this->cartService->clear(101);
        $this->assertSame(0, $summary['item_count']);
        $this->assertEmpty($summary['items']);
    }
}
