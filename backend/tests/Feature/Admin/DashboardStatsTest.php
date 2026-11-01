<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Admin;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Router;
use MediTrack\Core\Request;
use MediTrack\Core\Jwt;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Services\AdminDashboardService;

class DashboardStatsTest extends TestCase
{
    private Database $db;
    private Router $router;
    private string $adminToken;
    private SettingRepository $settingRepo;
    private AdminDashboardService $dashboardService;

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
                key TEXT NOT NULL UNIQUE,
                value TEXT NULL,
                updated_at TIMESTAMP NULL
            );

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
                category_id INTEGER NOT NULL,
                schedule TEXT NOT NULL DEFAULT 'OTC',
                requires_rx INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                deleted_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL
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

            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NULL UNIQUE,
                role TEXT NOT NULL DEFAULT 'admin',
                created_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            );
        ");

        $today = date('Y-m-d');
        $expMinus10 = date('Y-m-d', strtotime('-10 days')); // EXPIRED
        $expPlus15 = date('Y-m-d', strtotime('+15 days'));   // WITHIN 30d
        $expPlus60 = date('Y-m-d', strtotime('+60 days'));   // WITHIN 90d
        $expPlus180 = date('Y-m-d', strtotime('+180 days')); // SAFE (>90d)

        // Seed Categories
        $pdo->exec("
            INSERT INTO categories (id, name, slug, sort_order) VALUES
            (1, 'Tablet', 'tablet', 1),
            (2, 'Syrup', 'syrup', 2);
        ");

        // Seed 4 Medicines:
        // Med 1: Paracetamol (Tablet, 100 units unexpired)
        // Med 2: Azithromycin (Tablet, 8 units -> LOW STOCK <= 10)
        // Med 3: Amoxicillin (Tablet, 0 units unexpired -> only expired batch)
        // Med 4: Cetirizine (Syrup, 30 units unexpired)
        $pdo->exec("
            INSERT INTO medicines (id, name, slug, category_id, is_active) VALUES
            (1, 'Paracetamol 500', 'paracetamol-500', 1, 1),
            (2, 'Azithromycin 500', 'azithromycin-500', 1, 1),
            (3, 'Amoxicillin 250', 'amoxicillin-250', 1, 1),
            (4, 'Cetirizine Syrup', 'cetirizine-syrup', 2, 1);
        ");

        // Seed Batches:
        // Med 1:
        //   - Batch 1: SAFE (+180d), qty=100, reserved=0, purchase=10.00
        // Med 2:
        //   - Batch 2: WITHIN 30d (+15d), qty=8, reserved=0, purchase=40.00 (Low stock!)
        // Med 3:
        //   - Batch 3: EXPIRED (-10d), qty=50, reserved=0, purchase=15.00 (Disposal status = 'none')
        // Med 4:
        //   - Batch 4: WITHIN 90d (+60d), qty=30, reserved=5, purchase=25.00 (Avail=25)
        $pdo->exec("
            INSERT INTO batches (id, medicine_id, batch_number, quantity, reserved_quantity, purchase_price, mrp, selling_price, mfg_date, exp_date) VALUES
            (1, 1, 'B1-SAFE', 100, 0, 10.00, 15.00, 15.00, '2024-01-01', '{$expPlus180}'),
            (2, 2, 'B2-EXP15', 8, 0, 40.00, 60.00, 55.00, '2024-01-01', '{$expPlus15}'),
            (3, 3, 'B3-EXPD', 50, 0, 15.00, 25.00, 20.00, '2023-01-01', '{$expMinus10}'),
            (4, 4, 'B4-EXP60', 30, 5, 25.00, 40.00, 35.00, '2024-01-01', '{$expPlus60}');
        ");

        $pdo->exec("INSERT INTO users (id, name, email, role) VALUES (1, 'Admin User', 'admin@example.com', 'admin');");

        $this->settingRepo = new SettingRepository($this->db);
        $this->settingRepo->set('low_stock_threshold', '10');

        $this->dashboardService = new AdminDashboardService($this->db, $this->settingRepo);

        $container = Container::getInstance();
        $container->instance(Database::class, $this->db);
        $container->instance(SettingRepository::class, $this->settingRepo);
        $container->instance(AdminDashboardService::class, $this->dashboardService);

        $userRepo = new \MediTrack\Repositories\UserRepository($this->db);
        $container->instance(\MediTrack\Repositories\UserRepository::class, $userRepo);

        $jwt = new Jwt('test-secret-key-must-be-at-least-32-chars-long!');
        $container->instance(Jwt::class, $jwt);

        $this->adminToken = $jwt->encode(['sub' => 1, 'role' => 'admin']);

        $this->router = require dirname(__DIR__, 3) . '/bootstrap/app.php';
    }

    public function testDashboardStatsNumbersMatchSeedExpectations(): void
    {
        $req = new Request('GET', '/api/v1/admin/dashboard/stats', [
            'Authorization' => "Bearer {$this->adminToken}",
        ]);
        $response = $this->router->dispatch($req);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['success']);
        $stats = $payload['data'];

        // 1. Total Medicines: 4 active medicines
        $this->assertSame(4, $stats['total_medicines']);

        // 2. Total Units: unexpired available units = (100 - 0) + (8 - 0) + (30 - 5) = 133 units
        // (Batch 3 has 50 units but is expired, so it is strictly EXCLUDED!)
        $this->assertSame(133, $stats['total_units']);

        // 3. Stock Value: sum of unexpired quantity * purchase_price
        // = (100 * 10.00) + (8 * 40.00) + (30 * 25.00) = 1000 + 320 + 750 = 2070.00
        $this->assertEquals(2070.00, $stats['stock_value']);

        // 4. Expiring in 30 days: Batch 2 only (+15d)
        $this->assertSame(1, $stats['expiring_30d']);

        // 5. Expired count: Batch 3 only (-10d)
        $this->assertSame(1, $stats['expired']);

        // 6. Low stock count (threshold <= 10):
        // Med 2 (8 units <= 10) AND Med 3 (0 unexpired units <= 10) = 2 medicines!
        $this->assertSame(2, $stats['low_stock']);

        // 7. Expiry Overview:
        // safe (>90d): Batch 1
        // within_90d (31-90d): Batch 4
        // within_30d: Batch 2
        // expired: Batch 3
        $this->assertSame(1, $stats['expiry_overview']['safe']);
        $this->assertSame(1, $stats['expiry_overview']['within_90d']);
        $this->assertSame(1, $stats['expiry_overview']['within_30d']);
        $this->assertSame(1, $stats['expiry_overview']['expired']);

        // 8. Active Alerts: Must contain danger (expired), warning (expiring), info (low stock)
        $this->assertNotEmpty($stats['active_alerts']);
        $alertTypes = array_column($stats['active_alerts'], 'type');
        $this->assertContains('expired', $alertTypes);
        $this->assertContains('expiring', $alertTypes);
        $this->assertContains('low_stock', $alertTypes);
    }
}
