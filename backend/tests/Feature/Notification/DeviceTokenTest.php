<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Notification;

use MediTrack\Controllers\Api\V1\DeviceController;
use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Jwt;
use MediTrack\Core\Request;
use MediTrack\Core\Router;
use MediTrack\Repositories\DeviceTokenRepository;
use MediTrack\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

class DeviceTokenTest extends TestCase
{
    private Database $db;
    private Container $container;
    private Router $router;
    private Jwt $jwt;
    private DeviceTokenRepository $deviceTokenRepo;
    private UserRepository $userRepo;

    private int $userId;
    private int $otherUserId;
    private string $userToken;
    private string $otherToken;

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
                phone TEXT NULL,
                email TEXT NULL,
                name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'customer',
                locale TEXT NOT NULL DEFAULT 'en',
                is_blocked INTEGER DEFAULT 0,
                pharmacist_reg_no TEXT NULL,
                phone_verified_at TIMESTAMP NULL,
                email_verified_at TIMESTAMP NULL,
                last_login_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            );

            CREATE TABLE device_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                fcm_token TEXT NOT NULL UNIQUE,
                platform TEXT NOT NULL DEFAULT 'android',
                last_seen_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL
            );
        ");

        $this->userRepo = new UserRepository($this->db);
        $this->deviceTokenRepo = new DeviceTokenRepository($this->db);

        $this->userId = $this->userRepo->create([
            'name' => 'Rahul Sharma',
            'phone' => '+919876543210',
            'email' => 'rahul@example.com',
            'role' => 'customer',
        ]);

        $this->otherUserId = $this->userRepo->create([
            'name' => 'Priya Patel',
            'phone' => '+919876543211',
            'email' => 'priya@example.com',
            'role' => 'customer',
        ]);

        $this->jwt = new Jwt('test-secret-key-32-characters-min!!');
        $this->userToken = $this->jwt->encode([
            'sub' => $this->userId,
            'role' => 'customer',
            'phone' => '+919876543210',
        ]);

        $this->otherToken = $this->jwt->encode([
            'sub' => $this->otherUserId,
            'role' => 'customer',
            'phone' => '+919876543211',
        ]);

        $this->container = Container::getInstance();
        $this->container->instance(Database::class, $this->db);
        $this->container->instance(UserRepository::class, $this->userRepo);
        $this->container->instance(DeviceTokenRepository::class, $this->deviceTokenRepo);
        $this->container->instance(Jwt::class, $this->jwt);

        $this->router = require dirname(__DIR__, 3) . '/bootstrap/app.php';
    }

    public function testRegisterDeviceTokenSuccessful(): void
    {
        $request = Request::create(
            'POST',
            '/api/v1/devices',
            [
                'authorization' => "Bearer {$this->userToken}",
                'content-type' => 'application/json',
            ],
            [],
            [
                'fcm_token' => 'fcm-sample-token-abc12345',
                'platform' => 'android',
            ]
        );

        $response = $this->router->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('fcm-sample-token-abc12345', $body['data']['fcm_token']);

        $tokens = $this->deviceTokenRepo->getTokensByUserId($this->userId);
        $this->assertContains('fcm-sample-token-abc12345', $tokens);
    }

    public function testRegisterSameTokenTransfersToNewUser(): void
    {
        // First register for user 1
        $this->deviceTokenRepo->save($this->userId, 'shared-token-xyz', 'ios');
        $this->assertCount(1, $this->deviceTokenRepo->getTokensByUserId($this->userId));

        // Now user 2 registers the same token
        $request = Request::create(
            'POST',
            '/api/v1/devices',
            [
                'authorization' => "Bearer {$this->otherToken}",
                'content-type' => 'application/json',
            ],
            [],
            [
                'fcm_token' => 'shared-token-xyz',
                'platform' => 'ios',
            ]
        );

        $response = $this->router->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        // User 1 no longer has it, User 2 now has it
        $this->assertEmpty($this->deviceTokenRepo->getTokensByUserId($this->userId));
        $this->assertContains('shared-token-xyz', $this->deviceTokenRepo->getTokensByUserId($this->otherUserId));
    }

    public function testRegisterRequiresFcmToken(): void
    {
        $request = Request::create(
            'POST',
            '/api/v1/devices',
            [
                'authorization' => "Bearer {$this->userToken}",
                'content-type' => 'application/json',
            ],
            [],
            ['platform' => 'android']
        );

        $response = $this->router->dispatch($request);
        $this->assertSame(422, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertFalse($body['success']);
    }

    public function testUnregisterDeviceToken(): void
    {
        $this->deviceTokenRepo->save($this->userId, 'token-to-delete-999', 'android');
        $this->assertContains('token-to-delete-999', $this->deviceTokenRepo->getTokensByUserId($this->userId));

        $request = Request::create(
            'DELETE',
            '/api/v1/devices/token-to-delete-999',
            [
                'authorization' => "Bearer {$this->userToken}",
            ]
        );

        $response = $this->router->dispatch($request);
        $this->assertSame(200, $response->getStatusCode());

        $tokens = $this->deviceTokenRepo->getTokensByUserId($this->userId);
        $this->assertNotContains('token-to-delete-999', $tokens);
    }
}
