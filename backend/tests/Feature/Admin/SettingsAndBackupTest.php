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
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Services\BackupService;
use MediTrack\Validators\SettingValidator;

class SettingsAndBackupTest extends TestCase
{
    private Database $db;
    private Cache $cache;
    private Router $router;
    private string $adminToken;
    private SettingRepository $settingRepo;
    private BackupService $backupService;

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

            CREATE TABLE settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT NOT NULL UNIQUE,
                value TEXT NULL,
                updated_at TIMESTAMP NULL
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
        ");

        $this->cache = new Cache(null);
        $this->settingRepo = new SettingRepository($this->db);
        $this->backupService = new BackupService($this->db, $this->cache, sys_get_temp_dir() . '/meditrack_test_backups');

        $container = Container::getInstance();
        $container->instance(Database::class, $this->db);
        $container->instance(Cache::class, $this->cache);
        $container->instance(SettingRepository::class, $this->settingRepo);
        $container->instance(BackupService::class, $this->backupService);

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

    public function testGetAndPutSettings(): void
    {
        // 1. Initial GET
        $getRes = $this->dispatchAdmin('GET', '/api/v1/admin/settings');
        $this->assertSame(200, $getRes->getStatusCode());
        $getPayload = json_decode($getRes->getContent(), true);
        $this->assertTrue($getPayload['success']);
        $this->assertSame('Sharma Medical Store', $getPayload['data']['shop_name']);

        // 2. PUT Update Settings
        $newSettings = [
            'shop_name' => 'MediTrack Flagship Store',
            'owner_name' => 'Aakash Verma',
            'low_stock_threshold' => 15,
            'gst_number' => '07AABCM1234F1Z9',
            'drug_license_no' => 'DL-2026-DEL-999',
            'delivery_fee' => 50.00,
        ];

        $putRes = $this->dispatchAdmin('PUT', '/api/v1/admin/settings', $newSettings);
        $this->assertSame(200, $putRes->getStatusCode());
        $putPayload = json_decode($putRes->getContent(), true);
        $this->assertTrue($putPayload['success']);
        $this->assertSame('MediTrack Flagship Store', $putPayload['data']['shop_name']);
        $this->assertSame('15', (string)$putPayload['data']['low_stock_threshold']);

        // Verify audit log
        $audit = $this->db->fetchOne("SELECT * FROM audit_logs WHERE action = 'update' AND entity = 'settings'");
        $this->assertNotNull($audit);
    }

    public function testBackupCreateAndSignedDownload(): void
    {
        // 1. Create Backup
        $backupRes = $this->dispatchAdmin('POST', '/api/v1/admin/backup');
        $this->assertSame(201, $backupRes->getStatusCode());

        $payload = json_decode($backupRes->getContent(), true);
        $this->assertTrue($payload['success']);
        $backupData = $payload['data'];

        $this->assertNotEmpty($backupData['download_token']);
        $this->assertNotEmpty($backupData['file_name']);

        // 2. Download with valid token
        $token = $backupData['download_token'];
        $dlRes = $this->dispatchAdmin('GET', "/api/v1/admin/backup/download?token={$token}");
        $this->assertSame(200, $dlRes->getStatusCode());
        $this->assertSame('application/json', $dlRes->getHeaders()['Content-Type']);

        $json = json_decode($dlRes->getContent(), true);
        $this->assertSame('MediTrack', $json['meta']['app']);
        $this->assertArrayHasKey('tables', $json);
    }
}
