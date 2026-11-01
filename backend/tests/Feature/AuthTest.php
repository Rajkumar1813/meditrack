<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Router;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Jwt;
use MediTrack\Core\Exceptions\HttpException;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Core\Exceptions\UnauthorizedException;
use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Core\Exceptions\RateLimitException;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\OtpService;
use MediTrack\Services\AuthService;
use MediTrack\Enums\Role;
use MediTrack\Middleware\RequireRole;

class AuthTest extends TestCase
{
    private Database $db;
    private Router $router;
    private UserRepository $userRepo;
    private OtpService $otpService;
    private AuthService $authService;
    private Jwt $jwt;

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
                name TEXT NULL,
                phone TEXT NULL UNIQUE,
                email TEXT NULL UNIQUE,
                role TEXT NOT NULL DEFAULT 'customer',
                locale TEXT NOT NULL DEFAULT 'en',
                is_blocked INTEGER NOT NULL DEFAULT 0,
                phone_verified_at TIMESTAMP NULL,
                email_verified_at TIMESTAMP NULL,
                pharmacist_reg_no TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            );

            CREATE TABLE otp_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier TEXT NOT NULL,
                type TEXT NOT NULL,
                code_hash TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                locked_until TIMESTAMP NULL,
                expires_at TIMESTAMP NOT NULL,
                consumed_at TIMESTAMP NULL,
                ip TEXT NULL,
                created_at TIMESTAMP NULL
            );

            CREATE TABLE refresh_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                device_name TEXT NULL,
                ip TEXT NULL,
                expires_at TIMESTAMP NOT NULL,
                revoked_at TIMESTAMP NULL,
                replaced_by INTEGER NULL,
                created_at TIMESTAMP NULL
            );
        ");

        $container = Container::getInstance();
        $container->instance(Database::class, $this->db);

        $this->userRepo = new UserRepository($this->db);
        $this->jwt = new Jwt('test-secret-key-must-be-at-least-32-chars-long!');
        $this->authService = new AuthService($this->db, $this->userRepo, $this->jwt);
        $this->otpService = new OtpService($this->db, $this->userRepo);

        $container->instance(UserRepository::class, $this->userRepo);
        $container->instance(Jwt::class, $this->jwt);
        $container->instance(AuthService::class, $this->authService);
        $container->instance(OtpService::class, $this->otpService);

        // Load api routes into router
        $this->router = require dirname(__DIR__, 2) . '/bootstrap/app.php';
    }

    public function testOtpRequestSuccess(): void
    {
        $phone = '+919876543210';
        $res = $this->otpService->requestOtp($phone, 'phone', '127.0.0.1');

        $this->assertSame($phone, $res['identifier']);
        $this->assertSame('phone', $res['type']);
        $this->assertSame(60, $res['cooldown_seconds']);

        $dbRecord = $this->db->fetchOne('SELECT * FROM otp_codes WHERE identifier = ?', [$phone]);
        $this->assertNotNull($dbRecord);
        $this->assertSame(0, (int)$dbRecord['attempts']);
    }

    public function testOtpRequestCooldownRejection(): void
    {
        $email = 'user@example.com';
        $this->otpService->requestOtp($email, 'email', '127.0.0.1');

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessageMatches('/Please wait \d+ seconds before requesting a new OTP/i');

        // Immediate second request within 60s
        $this->otpService->requestOtp($email, 'email', '127.0.0.1');
    }

    public function testOtpVerifySuccessAndNewUserCreatedAsCustomer(): void
    {
        $phone = '+919999888877';
        // Insert known OTP code: 123456
        $code = '123456';
        $codeHash = password_hash($code, PASSWORD_BCRYPT);
        $this->db->execute(
            'INSERT INTO otp_codes (identifier, type, code_hash, attempts, expires_at, created_at)
             VALUES (?, "phone", ?, 0, ?, ?)',
            [$phone, $codeHash, date('Y-m-d H:i:s', time() + 300), date('Y-m-d H:i:s')]
        );

        $user = $this->otpService->verifyOtp($phone, $code);

        $this->assertNotNull($user);
        $this->assertSame($phone, $user['phone']);
        $this->assertSame(Role::CUSTOMER->value, $user['role']);
        $this->assertNotNull($user['phone_verified_at']);

        // Check consumed_at marked
        $rec = $this->db->fetchOne('SELECT consumed_at FROM otp_codes WHERE identifier = ?', [$phone]);
        $this->assertNotNull($rec['consumed_at']);
    }

    public function testOtpVerifyWrongCodeDecrementsAttempts(): void
    {
        $phone = '+919999111122';
        $code = '123456';
        $codeHash = password_hash($code, PASSWORD_BCRYPT);
        $this->db->execute(
            'INSERT INTO otp_codes (identifier, type, code_hash, attempts, expires_at, created_at)
             VALUES (?, "phone", ?, 0, ?, ?)',
            [$phone, $codeHash, date('Y-m-d H:i:s', time() + 300), date('Y-m-d H:i:s')]
        );

        try {
            $this->otpService->verifyOtp($phone, '999999'); // Wrong code
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->getErrors());
            $this->assertStringContainsString('4 attempts remaining', $e->getErrors()['code'][0]);
        }

        $rec = $this->db->fetchOne('SELECT attempts FROM otp_codes WHERE identifier = ?', [$phone]);
        $this->assertSame(1, (int)$rec['attempts']);
    }

    public function testOtpLockAfterFiveFailedAttempts(): void
    {
        $phone = '+919999333344';
        $code = '123456';
        $codeHash = password_hash($code, PASSWORD_BCRYPT);
        $this->db->execute(
            'INSERT INTO otp_codes (identifier, type, code_hash, attempts, expires_at, created_at)
             VALUES (?, "phone", ?, 4, ?, ?)', // already 4 attempts
            [$phone, $codeHash, date('Y-m-d H:i:s', time() + 300), date('Y-m-d H:i:s')]
        );

        try {
            $this->otpService->verifyOtp($phone, '000000'); // 5th attempt
            $this->fail('Expected 423 Locked HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertSame(423, $e->getStatusCode());
            $this->assertSame('ACCOUNT_LOCKED', $e->getErrorCode());
            $this->assertArrayHasKey('Retry-After', $e->getHeaders());
        }

        // Identifier is now locked
        $rec = $this->db->fetchOne('SELECT locked_until FROM otp_codes WHERE identifier = ?', [$phone]);
        $this->assertNotNull($rec['locked_until']);
    }

    public function testOtpExpiredRejection(): void
    {
        $phone = '+919999555566';
        $code = '123456';
        $codeHash = password_hash($code, PASSWORD_BCRYPT);
        $this->db->execute(
            'INSERT INTO otp_codes (identifier, type, code_hash, attempts, expires_at, created_at)
             VALUES (?, "phone", ?, 0, ?, ?)',
            [$phone, $codeHash, date('Y-m-d H:i:s', time() - 10), date('Y-m-d H:i:s', time() - 320)] // Expired 10s ago
        );

        try {
            $this->otpService->verifyOtp($phone, $code);
            $this->fail('Expected ValidationException was not thrown on expired OTP.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->getErrors());
            $this->assertStringContainsString('OTP has expired', $e->getErrors()['code'][0]);
        }
    }

    public function testWebClientReceivesHttpOnlySameSiteStrictCookie(): void
    {
        $user = [
            'id' => 42,
            'role' => 'customer',
            'name' => 'Web User',
            'phone' => '+919811223344',
            'email' => 'web@example.com',
            'is_blocked' => 0,
        ];

        // Direct controller call or router dispatch
        $req = new Request('POST', '/api/v1/auth/otp/verify', ['content-type' => 'application/json'], [], [
            'identifier' => '+919811223344',
            'code' => '123456',
            'client' => 'web',
        ]);

        $codeHash = password_hash('123456', PASSWORD_BCRYPT);
        $this->db->execute(
            'INSERT INTO otp_codes (identifier, type, code_hash, attempts, expires_at, created_at)
             VALUES ("+919811223344", "phone", ?, 0, ?, ?)',
            [$codeHash, date('Y-m-d H:i:s', time() + 300), date('Y-m-d H:i:s')]
        );

        $response = $this->router->dispatch($req);
        $this->assertSame(200, $response->getStatusCode());

        $cookies = $response->getCookies();
        $this->assertCount(1, $cookies);
        $refreshCookie = $cookies[0];

        $this->assertSame('refresh_token', $refreshCookie['name']);
        $this->assertNotEmpty($refreshCookie['value']);
        $this->assertTrue($refreshCookie['httponly'], 'Cookie must be HttpOnly');
        $this->assertSame('Strict', $refreshCookie['samesite'], 'Cookie must be SameSite=Strict');
        $this->assertSame('/api/v1/auth', $refreshCookie['path'], 'Cookie path must be /api/v1/auth');

        // Confirm refresh_token is NOT in JSON body for web
        $body = json_decode($response->getContent(), true);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('access_token', $body['data']['tokens']);
        $this->assertArrayNotHasKey('refresh_token', $body['data']['tokens']);
    }

    public function testMobileClientReceivesRefreshTokenInBody(): void
    {
        $codeHash = password_hash('654321', PASSWORD_BCRYPT);
        $this->db->execute(
            'INSERT INTO otp_codes (identifier, type, code_hash, attempts, expires_at, created_at)
             VALUES ("+919800000000", "phone", ?, 0, ?, ?)',
            [$codeHash, date('Y-m-d H:i:s', time() + 300), date('Y-m-d H:i:s')]
        );

        $req = new Request('POST', '/api/v1/auth/otp/verify', ['content-type' => 'application/json'], [], [
            'identifier' => '+919800000000',
            'code' => '654321',
            'client' => 'mobile',
        ]);

        $response = $this->router->dispatch($req);
        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('access_token', $body['data']['tokens']);
        $this->assertArrayHasKey('refresh_token', $body['data']['tokens']);
        $this->assertEmpty($response->getCookies(), 'Mobile should not receive cookies');
    }

    public function testRefreshTokenRotation(): void
    {
        $userId = $this->userRepo->create([
            'name' => 'Rotate User',
            'phone' => '+919899999999',
            'role' => 'customer',
        ]);
        $user = $this->userRepo->findById($userId);

        $initial = $this->authService->issueTokens($user);
        $oldToken = $initial['refresh_token'];

        // Rotate
        $rotated = $this->authService->rotateRefreshToken($oldToken);

        $this->assertNotSame($oldToken, $rotated['refresh_token']);
        $this->assertNotEmpty($rotated['access_token']);

        // Check DB: old token is marked revoked and has replaced_by set
        $oldRecord = $this->db->fetchOne('SELECT * FROM refresh_tokens WHERE token_hash = ?', [hash('sha256', $oldToken)]);
        $this->assertNotNull($oldRecord['revoked_at']);
        $this->assertNotNull($oldRecord['replaced_by']);
    }

    public function testRefreshTokenReuseDetectionRevokesAllUserTokens(): void
    {
        $userId = $this->userRepo->create([
            'name' => 'Attacked User',
            'email' => 'victim@example.com',
            'role' => 'customer',
        ]);
        $user = $this->userRepo->findById($userId);

        // Session 1
        $initial = $this->authService->issueTokens($user);
        $oldToken = $initial['refresh_token'];

        // Session 2 (another device)
        $session2 = $this->authService->issueTokens($user);

        // Legitimate rotation of Session 1
        $this->authService->rotateRefreshToken($oldToken);

        // Attacker attempts to reuse the already-rotated $oldToken!
        try {
            $this->authService->rotateRefreshToken($oldToken);
            $this->fail('Expected UnauthorizedException was not thrown on token reuse.');
        } catch (UnauthorizedException $e) {
            $this->assertSame('TOKEN_REUSE_DETECTED', $e->getErrorCode());
        }

        // All active tokens for this user must now be revoked
        $activeTokens = $this->db->fetchAll(
            'SELECT * FROM refresh_tokens WHERE user_id = ? AND revoked_at IS NULL',
            [$userId]
        );
        $this->assertCount(0, $activeTokens, 'All user tokens must be revoked on reuse detection');
    }

    public function testBlockedUserCannotLoginOrRefresh(): void
    {
        $userId = $this->userRepo->create([
            'name' => 'Blocked Bad Actor',
            'phone' => '+919999999999',
            'role' => 'customer',
            'is_blocked' => 1,
        ]);

        $codeHash = password_hash('123456', PASSWORD_BCRYPT);
        $this->db->execute(
            'INSERT INTO otp_codes (identifier, type, code_hash, attempts, expires_at, created_at)
             VALUES ("+919999999999", "phone", ?, 0, ?, ?)',
            [$codeHash, date('Y-m-d H:i:s', time() + 300), date('Y-m-d H:i:s')]
        );

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessageMatches('/account (is|has been) blocked/i');

        $this->otpService->verifyOtp('+919999999999', '123456');
    }

    public function testOriginCheckRejectsCrossSitePost(): void
    {
        $req = new Request(
            'POST',
            '/api/v1/auth/otp/request',
            [
                'origin' => 'https://malicious-phishing-site.example',
                'host' => 'localhost:8080',
                'content-type' => 'application/json',
            ],
            [],
            ['identifier' => '+919811111111', 'type' => 'phone']
        );

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessageMatches('/Cross-site request blocked by OriginCheck/i');

        $this->router->dispatch($req);
    }

    public function testRequireRoleMiddlewareEnforcesRole(): void
    {
        $middleware = new RequireRole([Role::ADMIN, Role::PHARMACIST]);

        // Customer request -> Forbidden
        $customerReq = (new Request('GET', '/api/v1/admin/dashboard'))->withAttribute('user', [
            'id' => 1,
            'role' => 'customer',
        ]);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessageMatches('/do not have permission/i');

        $middleware->process($customerReq, fn() => new Response('ok'));
    }

    public function testAuthMiddlewareProtectsMeEndpoint(): void
    {
        // 1. Without Token -> 401
        try {
            $unauthReq = new Request('GET', '/api/v1/me');
            $this->router->dispatch($unauthReq);
            $this->fail('Expected UnauthorizedException was not thrown.');
        } catch (UnauthorizedException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }

        // 2. With Valid Token -> 200 and user profile
        $userId = $this->userRepo->create([
            'name' => 'Profile User',
            'phone' => '+919877777777',
            'email' => 'profile@example.com',
            'role' => 'customer',
        ]);
        $user = $this->userRepo->findById($userId);
        $token = $this->jwt->encode(['sub' => $userId, 'role' => 'customer']);

        $authReq = new Request('GET', '/api/v1/me', ['authorization' => "Bearer {$token}"]);
        $response = $this->router->dispatch($authReq);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('Profile User', $body['data']['name']);
        $this->assertSame('profile@example.com', $body['data']['email']);
    }
}
