<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Container;
use MediTrack\Core\Database;
use MediTrack\Core\Request;
use MediTrack\Core\Router;
use MediTrack\Core\Jwt;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\AuthgearService;
use MediTrack\Middleware\Auth;
use Firebase\JWT\JWT as FirebaseJwt;

class AuthgearTest extends TestCase
{
    private Database $db;
    private UserRepository $userRepo;
    private AuthgearService $authgearService;
    private $privateKey;
    private string $publicKeyPem;
    private string $kid = 'test-key-id-1';
    private string $mockEndpoint = 'https://test-project.authgear.cloud';
    private string $mockClientId = 'test-client-id-xyz';

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
                authgear_user_id TEXT NULL UNIQUE,
                name TEXT NULL,
                phone TEXT NULL UNIQUE,
                email TEXT NULL UNIQUE,
                role TEXT NOT NULL DEFAULT 'customer',
                locale TEXT NOT NULL DEFAULT 'en',
                is_blocked INTEGER NOT NULL DEFAULT 0,
                phone_verified_at TIMESTAMP NULL,
                email_verified_at TIMESTAMP NULL,
                pharmacist_reg_no TEXT NULL,
                last_login_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            );
        ");

        $this->userRepo = new UserRepository($this->db);

        // Generate temporary 2048-bit RSA key for testing RS256 JWT
        $cnfCandidates = [
            'C:/xampp/apache/conf/openssl.cnf',
            'C:/xampp/php/extras/ssl/openssl.cnf',
            getenv('OPENSSL_CONF') ?: '',
        ];
        $options = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        foreach ($cnfCandidates as $cnf) {
            if (!empty($cnf) && file_exists($cnf)) {
                $options['config'] = $cnf;
                break;
            }
        }
        $res = openssl_pkey_new($options);
        $this->privateKey = $res;
        $details = openssl_pkey_get_details($res);
        $this->publicKeyPem = $details['key'];

        // Convert RSA public key components to JWK format
        $n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

        $jwks = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => $this->kid,
                    'n' => $n,
                    'e' => $e,
                ],
            ],
        ];

        // Save mock JWKS in cache directory so AuthgearService reads it without making network calls
        $cacheDir = dirname(__DIR__, 2) . '/storage/framework/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cacheDir . '/authgear_jwks.json', json_encode($jwks));

        $this->authgearService = new AuthgearService($this->userRepo, [
            'endpoint' => $this->mockEndpoint,
            'client_id' => $this->mockClientId,
            'redirect_uri' => 'http://localhost:5173/auth-callback',
            'jwks_cache_ttl' => 3600,
        ]);
    }

    private function createSignedToken(array $claims, int $ttl = 3600): string
    {
        $payload = array_merge([
            'iss' => $this->mockEndpoint,
            'aud' => $this->mockClientId,
            'sub' => 'authgear_usr_test_' . bin2hex(random_bytes(4)),
            'iat' => time(),
            'exp' => time() + $ttl,
        ], $claims);

        return FirebaseJwt::encode($payload, $this->privateKey, 'RS256', $this->kid);
    }

    public function testVerifyValidToken(): void
    {
        $token = $this->createSignedToken([
            'sub' => 'authgear_user_12345',
            'email' => 'testuser@example.com',
            'phone_number' => '+919876543210',
        ]);

        $claims = $this->authgearService->verifyToken($token);
        $this->assertSame('authgear_user_12345', $claims['sub']);
        $this->assertSame('testuser@example.com', $claims['email']);
        $this->assertSame('+919876543210', $claims['phone_number']);
    }

    public function testAutoProvisionNewUserFromClaims(): void
    {
        $claims = [
            'sub' => 'authgear_new_user_999',
            'name' => 'Aditi Sharma',
            'email' => 'aditi@example.com',
            'phone_number' => '+919988776655',
            'email_verified' => true,
        ];

        $user = $this->authgearService->syncUserFromClaims($claims);
        $this->assertNotNull($user);
        $this->assertSame('authgear_new_user_999', $user['authgear_user_id']);
        $this->assertSame('aditi@example.com', $user['email']);
        $this->assertSame('+919988776655', $user['phone']);
        $this->assertSame('customer', $user['role']);
    }

    public function testLinkExistingUserWithSamePhone(): void
    {
        // Pre-create an existing user with phone
        $existingId = $this->userRepo->create([
            'name' => 'Rahul Verma',
            'phone' => '+919123456789',
            'email' => 'rahul@example.com',
            'role' => 'pharmacist',
        ]);

        $claims = [
            'sub' => 'authgear_rahul_sub_101',
            'phone_number' => '+919123456789',
            'email' => 'rahul@example.com',
        ];

        $user = $this->authgearService->syncUserFromClaims($claims);
        $this->assertSame($existingId, (int)$user['id']);
        $this->assertSame('authgear_rahul_sub_101', $user['authgear_user_id']);
        $this->assertSame('pharmacist', $user['role']); // Role preserved!
    }

    public function testMiddlewareAuthenticatesWithAuthgearToken(): void
    {
        $token = $this->createSignedToken([
            'sub' => 'authgear_mw_user_555',
            'phone_number' => '+919000111222',
            'name' => 'Middleware User',
        ]);

        $jwt = new Jwt('test-secret');
        $authMiddleware = new Auth($jwt, $this->userRepo, $this->authgearService);

        $request = new Request('GET', '/api/v1/me', [
            'authorization' => 'Bearer ' . $token,
        ]);

        $reachedController = false;
        $response = $authMiddleware->process($request, function ($req) use (&$reachedController) {
            $reachedController = true;
            $this->assertNotNull($req->getAttribute('user'));
            $this->assertSame('authgear_mw_user_555', $req->getAttribute('user')['authgear_user_id']);
            $this->assertSame('customer', $req->getAttribute('role'));
            return new \MediTrack\Core\Response('OK', 200);
        });

        $this->assertTrue($reachedController);
        $this->assertSame(200, $response->getStatusCode());
    }
}
