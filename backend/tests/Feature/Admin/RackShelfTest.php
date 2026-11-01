<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Admin;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Router;
use MediTrack\Core\Request;
use MediTrack\Core\Jwt;
use MediTrack\Repositories\RackRepository;
use MediTrack\Repositories\ShelfRepository;
use MediTrack\Repositories\AuditLogRepository;

class RackShelfTest extends TestCase
{
    private Database $db;
    private Router $router;
    private string $adminToken;
    private RackRepository $rackRepo;
    private ShelfRepository $shelfRepo;

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

            CREATE TABLE racks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
            );

            CREATE TABLE shelves (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rack_id INTEGER NOT NULL,
                name TEXT NOT NULL
            );

            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL
            );

            CREATE TABLE medicines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                generic_name TEXT NULL,
                category_id INTEGER NOT NULL,
                deleted_at TIMESTAMP NULL
            );

            CREATE TABLE batches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                medicine_id INTEGER NOT NULL,
                batch_number TEXT NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 0,
                reserved_quantity INTEGER NOT NULL DEFAULT 0,
                rack_id INTEGER NULL,
                shelf_id INTEGER NULL,
                exp_date TEXT NOT NULL
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
            INSERT INTO racks (id, name) VALUES (1, 'Rack A'), (2, 'Rack B');
            INSERT INTO shelves (id, rack_id, name) VALUES (1, 1, 'Shelf 1'), (2, 1, 'Shelf 2');
            INSERT INTO categories (id, name, slug) VALUES (1, 'Tablets', 'tablets');
            INSERT INTO medicines (id, name, slug, category_id) VALUES (1, 'Paracetamol 500', 'paracetamol-500', 1);
            INSERT INTO batches (id, medicine_id, batch_number, quantity, rack_id, shelf_id, exp_date) VALUES (1, 1, 'B-01', 50, 1, 1, '2026-12-31');
        ");

        $this->rackRepo = new RackRepository($this->db);
        $this->shelfRepo = new ShelfRepository($this->db);

        $container = Container::getInstance();
        $container->instance(Database::class, $this->db);
        $container->instance(RackRepository::class, $this->rackRepo);
        $container->instance(ShelfRepository::class, $this->shelfRepo);

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

    public function testRacksListWithCounts(): void
    {
        $res = $this->dispatchAdmin('GET', '/api/v1/admin/racks');
        $this->assertSame(200, $res->getStatusCode());

        $payload = json_decode($res->getContent(), true);
        $this->assertTrue($payload['success']);
        $racks = $payload['data'];

        $this->assertCount(2, $racks);
        // Rack A has 2 shelves, 1 medicine, 50 units
        $rackA = $racks[0];
        $this->assertSame('Rack A', $rackA['name']);
        $this->assertSame(2, (int)$rackA['shelf_count']);
        $this->assertSame(1, (int)$rackA['medicine_count']);
        $this->assertSame(50, (int)$rackA['total_units']);
    }

    public function testRackCreateUpdateDelete(): void
    {
        // 1. Create Rack C
        $createRes = $this->dispatchAdmin('POST', '/api/v1/admin/racks', ['name' => 'Rack C']);
        $this->assertSame(201, $createRes->getStatusCode());
        $createdPayload = json_decode($createRes->getContent(), true);
        $rackId = (int)$createdPayload['data']['id'];
        $this->assertSame('Rack C', $createdPayload['data']['name']);

        // 2. Update Rack C -> Rack Central
        $updateRes = $this->dispatchAdmin('PUT', "/api/v1/admin/racks/{$rackId}", ['name' => 'Rack Central']);
        $this->assertSame(200, $updateRes->getStatusCode());
        $updatePayload = json_decode($updateRes->getContent(), true);
        $this->assertSame('Rack Central', $updatePayload['data']['name']);

        // 3. Delete Rack Central
        $delRes = $this->dispatchAdmin('DELETE', "/api/v1/admin/racks/{$rackId}");
        $this->assertSame(200, $delRes->getStatusCode());

        $afterDel = $this->rackRepo->findById($rackId);
        $this->assertNull($afterDel);
    }

    public function testShelvesCRUD(): void
    {
        // 1. List shelves in Rack 1
        $listRes = $this->dispatchAdmin('GET', '/api/v1/admin/shelves?rack_id=1');
        $this->assertSame(200, $listRes->getStatusCode());
        $listPayload = json_decode($listRes->getContent(), true);
        $this->assertCount(2, $listPayload['data']);

        // 2. Create Shelf 3 in Rack 1
        $createRes = $this->dispatchAdmin('POST', '/api/v1/admin/shelves', ['rack_id' => 1, 'name' => 'Shelf 3']);
        $this->assertSame(201, $createRes->getStatusCode());
        $shelfId = (int)json_decode($createRes->getContent(), true)['data']['id'];

        // 3. Update Shelf 3 -> Shelf Top
        $updateRes = $this->dispatchAdmin('PUT', "/api/v1/admin/shelves/{$shelfId}", ['name' => 'Shelf Top']);
        $this->assertSame(200, $updateRes->getStatusCode());
        $this->assertSame('Shelf Top', json_decode($updateRes->getContent(), true)['data']['name']);

        // 4. Delete Shelf Top
        $delRes = $this->dispatchAdmin('DELETE', "/api/v1/admin/shelves/{$shelfId}");
        $this->assertSame(200, $delRes->getStatusCode());
    }

    public function testGetMedicinesInRack(): void
    {
        $res = $this->dispatchAdmin('GET', '/api/v1/admin/racks/1/medicines');
        $this->assertSame(200, $res->getStatusCode());

        $payload = json_decode($res->getContent(), true);
        $this->assertTrue($payload['success']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('Paracetamol 500', $payload['data'][0]['name']);
        $this->assertSame('B-01', $payload['data'][0]['batch_number']);
    }
}
