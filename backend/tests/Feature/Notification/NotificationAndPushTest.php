<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature\Notification;

use MediTrack\Controllers\Api\V1\NotificationController;
use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Jwt;
use MediTrack\Core\Request;
use MediTrack\Core\Router;
use MediTrack\Repositories\DeviceTokenRepository;
use MediTrack\Repositories\NotificationPreferenceRepository;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\NotificationService;
use MediTrack\Services\Push\FakePushService;
use MediTrack\Services\Push\PushServiceInterface;
use PHPUnit\Framework\TestCase;

class NotificationAndPushTest extends TestCase
{
    private Database $db;
    private Container $container;
    private Router $router;
    private Jwt $jwt;
    private UserRepository $userRepo;
    private DeviceTokenRepository $deviceTokenRepo;
    private NotificationRepository $notificationRepo;
    private NotificationPreferenceRepository $prefRepo;
    private FakePushService $fakePush;
    private NotificationService $notificationService;

    private int $userId;
    private int $adminId;
    private string $userToken;
    private string $adminToken;

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

            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                title TEXT NOT NULL,
                body TEXT NOT NULL,
                data TEXT NULL,
                read_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL
            );

            CREATE TABLE user_notification_preferences (
                user_id INTEGER PRIMARY KEY,
                order_updates INTEGER NOT NULL DEFAULT 1,
                refill_reminders INTEGER NOT NULL DEFAULT 1,
                promotional_push INTEGER NOT NULL DEFAULT 1,
                stock_alerts INTEGER NOT NULL DEFAULT 1,
                updated_at TIMESTAMP NULL
            );
        ");

        $this->userRepo = new UserRepository($this->db);
        $this->deviceTokenRepo = new DeviceTokenRepository($this->db);
        $this->notificationRepo = new NotificationRepository($this->db);
        $this->prefRepo = new NotificationPreferenceRepository($this->db);
        $this->fakePush = new FakePushService($this->deviceTokenRepo);

        $this->notificationService = new NotificationService(
            $this->notificationRepo,
            $this->prefRepo,
            $this->userRepo,
            $this->fakePush
        );

        $this->userId = $this->userRepo->create([
            'name' => 'Amit Verma',
            'phone' => '+919876543210',
            'email' => 'amit@example.com',
            'role' => 'customer',
        ]);

        $this->adminId = $this->userRepo->create([
            'name' => 'Owner Admin',
            'phone' => '+919876543299',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $this->jwt = new Jwt('test-secret-key-32-characters-min!!');
        $this->userToken = $this->jwt->encode([
            'sub' => $this->userId,
            'role' => 'customer',
            'phone' => '+919876543210',
        ]);

        $this->adminToken = $this->jwt->encode([
            'sub' => $this->adminId,
            'role' => 'admin',
            'phone' => '+919876543299',
        ]);

        $this->container = Container::getInstance();
        $this->container->instance(Database::class, $this->db);
        $this->container->instance(UserRepository::class, $this->userRepo);
        $this->container->instance(DeviceTokenRepository::class, $this->deviceTokenRepo);
        $this->container->instance(NotificationRepository::class, $this->notificationRepo);
        $this->container->instance(NotificationPreferenceRepository::class, $this->prefRepo);
        $this->container->instance(PushServiceInterface::class, $this->fakePush);
        $this->container->instance(NotificationService::class, $this->notificationService);
        $this->container->instance(Jwt::class, $this->jwt);

        $notifController = new NotificationController($this->notificationService);
        $this->container->instance(NotificationController::class, $notifController);

        $this->router = require dirname(__DIR__, 3) . '/bootstrap/app.php';
    }

    public function testCursorPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->notificationRepo->create($this->userId, 'order_status', "Notice #{$i}", "Body #{$i}");
        }

        // Page 1: limit 2
        $request1 = Request::create(
            'GET',
            '/api/v1/notifications?limit=2',
            ['authorization' => "Bearer {$this->userToken}"]
        );

        $res1 = $this->router->dispatch($request1);
        $this->assertSame(200, $res1->getStatusCode());
        $body1 = json_decode($res1->getContent(), true);

        $this->assertCount(2, $body1['data']);
        $this->assertTrue($body1['meta']['cursor']['has_more']);
        $nextCursor = $body1['meta']['cursor']['next_cursor'];
        $this->assertNotNull($nextCursor);

        // Page 2: with cursor
        $request2 = Request::create(
            'GET',
            "/api/v1/notifications?limit=2&cursor={$nextCursor}",
            ['authorization' => "Bearer {$this->userToken}"]
        );

        $res2 = $this->router->dispatch($request2);
        $this->assertSame(200, $res2->getStatusCode());
        $body2 = json_decode($res2->getContent(), true);

        $this->assertCount(2, $body2['data']);
        // IDs should be strictly descending
        $this->assertLessThan($body1['data'][1]['id'], $body2['data'][0]['id']);
    }

    public function testMarkAsRead(): void
    {
        $id = $this->notificationRepo->create($this->userId, 'refill', 'Refill Due', 'Time to refill');

        $request = Request::create(
            'POST',
            '/api/v1/notifications/read',
            [
                'authorization' => "Bearer {$this->userToken}",
                'content-type' => 'application/json',
            ],
            [],
            ['id' => $id]
        );

        $res = $this->router->dispatch($request);
        $this->assertSame(200, $res->getStatusCode());

        $notif = $this->notificationRepo->findById($id);
        $this->assertNotNull($notif['read_at']);
    }

    public function testMarkAllAsRead(): void
    {
        $this->notificationRepo->create($this->userId, 'order_status', 'Notice 1', 'Body 1');
        $this->notificationRepo->create($this->userId, 'order_status', 'Notice 2', 'Body 2');
        $this->assertSame(2, $this->notificationRepo->getUnreadCount($this->userId));

        $request = Request::create(
            'POST',
            '/api/v1/notifications/read',
            [
                'authorization' => "Bearer {$this->userToken}",
                'content-type' => 'application/json',
            ],
            [],
            ['all' => true]
        );

        $res = $this->router->dispatch($request);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(0, $this->notificationRepo->getUnreadCount($this->userId));
    }

    public function testNotificationPreferencesEndpoints(): void
    {
        // 1. Get default preferences
        $getReq = Request::create(
            'GET',
            '/api/v1/notifications/preferences',
            ['authorization' => "Bearer {$this->userToken}"]
        );
        $res = $this->router->dispatch($getReq);
        $this->assertSame(200, $res->getStatusCode());
        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['data']['order_updates']);
        $this->assertTrue($body['data']['refill_reminders']);

        // 2. Update preferences
        $putReq = Request::create(
            'PUT',
            '/api/v1/notifications/preferences',
            [
                'authorization' => "Bearer {$this->userToken}",
                'content-type' => 'application/json',
            ],
            [],
            ['order_updates' => false]
        );
        $res2 = $this->router->dispatch($putReq);
        $this->assertSame(200, $res2->getStatusCode());
        $body2 = json_decode($res2->getContent(), true);
        $this->assertFalse($body2['data']['order_updates']);
        $this->assertTrue($body2['data']['refill_reminders']);
    }

    public function testPushRespectsNotificationPreferences(): void
    {
        $this->deviceTokenRepo->save($this->userId, 'active-fcm-token-111', 'android');

        // Case A: User has order_updates disabled
        $this->prefRepo->updatePreferences($this->userId, ['order_updates' => false]);
        $this->fakePush->clear();

        $this->notificationService->notifyUser(
            $this->userId,
            'order_status',
            'Order Confirmed',
            'Your order has been confirmed'
        );

        // In-app notification should exist, but Push should NOT have been sent
        $this->assertSame(0, $this->fakePush->count());
        $this->assertSame(1, $this->notificationRepo->getUnreadCount($this->userId));

        // Case B: User re-enables order_updates
        $this->prefRepo->updatePreferences($this->userId, ['order_updates' => true]);
        $this->fakePush->clear();

        $this->notificationService->notifyUser(
            $this->userId,
            'order_status',
            'Order Shipped',
            'Your order has been shipped'
        );

        // Now push is dispatched!
        $this->assertSame(1, $this->fakePush->count());
    }

    public function testInvalidTokensCleanedUpAutomatically(): void
    {
        $this->deviceTokenRepo->save($this->userId, 'valid-token-aaa', 'android');
        $this->deviceTokenRepo->save($this->userId, 'dead-token-bbb', 'android');

        $this->assertCount(2, $this->deviceTokenRepo->getTokensByUserId($this->userId));

        // Mark dead-token-bbb as invalid in fake FCM
        $this->fakePush->markAsInvalid('dead-token-bbb');

        $this->notificationService->notifyUser(
            $this->userId,
            'order_status',
            'Order Delivered',
            'Package delivered'
        );

        // Dead token must be automatically deleted from device_tokens
        $remainingTokens = $this->deviceTokenRepo->getTokensByUserId($this->userId);
        $this->assertCount(1, $remainingTokens);
        $this->assertContains('valid-token-aaa', $remainingTokens);
        $this->assertNotContains('dead-token-bbb', $remainingTokens);
    }
}

