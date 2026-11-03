<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\RateLimiter;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Middleware\RateLimit;
use MediTrack\Core\Exceptions\RateLimitException;

class RateLimitAuditTest extends TestCase
{
    private array $rateLimits;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateLimits = require dirname(__DIR__, 2) . '/config/rate_limits.php';
    }

    /**
     * Helper to create a PHPUnit mock for Predis\Client with atomic Lua rate limiter behavior.
     */
    private function createMockRedis(): \Predis\Client
    {
        $mock = $this->getMockBuilder(\Predis\Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['eval'])
            ->getMock();

        $state = new \stdClass();
        $state->count = 0;

        $mock->method('eval')->willReturnCallback(function ($script, $numKeys, $key, $decaySeconds) use ($state) {
            $state->count++;
            $ttl = (int)$decaySeconds;
            return [$state->count, $ttl];
        });

        return $mock;
    }

    public function testOtpRequestRateLimitRow(): void
    {
        $config = $this->rateLimits['otp_request'];
        $this->assertSame(5, $config['limit'], 'docs/04: OTP request must allow 5 requests per hour');
        $this->assertSame(3600, $config['window']);
        $this->assertTrue($config['fail_closed']);

        $mockRedis = $this->createMockRedis();
        $limiter = new RateLimiter($mockRedis);

        // 5 allowed
        for ($i = 0; $i < 5; $i++) {
            [$exceeded, $remaining] = $limiter->check('rl:otp_req:test', $config['limit'], $config['window'], $config['fail_closed']);
            $this->assertFalse($exceeded);
        }

        // 6th exceeded
        [$exceeded, $remaining, $retryAfter] = $limiter->check('rl:otp_req:test', $config['limit'], $config['window'], $config['fail_closed']);
        $this->assertTrue($exceeded);
        $this->assertSame(0, $remaining);
        $this->assertGreaterThan(0, $retryAfter);
    }

    public function testOtpVerifyRateLimitRow(): void
    {
        $config = $this->rateLimits['otp_verify'];
        $this->assertSame(5, $config['limit'], 'docs/04: OTP verify must enforce 5 attempts');
        $this->assertTrue($config['fail_closed']);

        $mockRedis = $this->createMockRedis();
        $limiter = new RateLimiter($mockRedis);

        for ($i = 0; $i < 5; $i++) {
            [$exceeded] = $limiter->check('rl:otp_ver:test', $config['limit'], $config['window'], $config['fail_closed']);
            $this->assertFalse($exceeded);
        }

        [$exceeded, $remaining, $retryAfter] = $limiter->check('rl:otp_ver:test', $config['limit'], $config['window'], $config['fail_closed']);
        $this->assertTrue($exceeded);
        $this->assertSame(0, $remaining);
    }

    public function testSearchSuggestRateLimitRow(): void
    {
        $config = $this->rateLimits['search_suggest'];
        $this->assertSame(60, $config['limit'], 'docs/04: Search suggest must be 60 / min');
        $this->assertSame(60, $config['window']);
        $this->assertFalse($config['fail_closed'], 'Search suggest must fail open if Redis is down');
    }

    public function testPublicCatalogRateLimitRow(): void
    {
        $config = $this->rateLimits['public_catalog'];
        $this->assertSame(120, $config['limit'], 'docs/04: Public catalog must be 120 / min');
        $this->assertSame(60, $config['window']);
        $this->assertFalse($config['fail_closed']);
    }

    public function testAuthenticatedDefaultRateLimitRow(): void
    {
        $config = $this->rateLimits['authenticated'];
        $this->assertSame(120, $config['limit'], 'docs/04: Authenticated user limit must be 120 / min');
        $this->assertSame(60, $config['window']);
    }

    public function testPrescriptionsUploadRateLimitRow(): void
    {
        $config = $this->rateLimits['prescriptions_upload'];
        $this->assertSame(10, $config['limit'], 'docs/04: Prescriptions upload must be 10 / hour');
        $this->assertSame(3600, $config['window']);
        $this->assertTrue($config['fail_closed']);
    }

    public function testOrdersCreateRateLimitRow(): void
    {
        $config = $this->rateLimits['orders_create'];
        $this->assertSame(10, $config['limit'], 'docs/04: Orders create must be 10 / min');
        $this->assertSame(60, $config['window']);
        $this->assertTrue($config['fail_closed']);
    }

    public function testPaymentsRateLimitRow(): void
    {
        $config = $this->rateLimits['payments'];
        $this->assertSame(20, $config['limit'], 'docs/04: Payments must be 20 / min');
        $this->assertSame(60, $config['window']);
        $this->assertTrue($config['fail_closed']);
    }

    public function testAdminRateLimitRow(): void
    {
        $config = $this->rateLimits['admin'];
        $this->assertSame(300, $config['limit'], 'docs/04: Admin rate limit must be 300 / min');
        $this->assertSame(60, $config['window']);
    }

    public function testAdminCsvImportRateLimitRow(): void
    {
        $config = $this->rateLimits['admin_import'];
        $this->assertSame(5, $config['limit'], 'docs/04: Admin CSV import must be 5 / hour');
        $this->assertSame(3600, $config['window']);
        $this->assertTrue($config['fail_closed']);
    }

    public function testRateLimitMiddlewareThrows429WithCorrectHeaders(): void
    {
        $mockRedis = $this->createMockRedis();
        $limiter = new RateLimiter($mockRedis);
        $middleware = new RateLimit('orders_create', $limiter);

        $request = new Request('POST', '/api/v1/orders', ['host' => 'localhost'], [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        // First 10 requests succeed
        for ($i = 0; $i < 10; $i++) {
            $resp = $middleware->process($request, function ($req) {
                return new Response('ok', 200);
            });
            $this->assertSame(200, $resp->getStatusCode());
        }

        // 11th request triggers 429 RateLimitException
        try {
            $middleware->process($request, function ($req) {
                return new Response('ok', 200);
            });
            $this->fail('Expected RateLimitException was not thrown');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertSame('RATE_LIMITED', $e->getErrorCode());
            $this->assertGreaterThan(0, $e->getRetryAfter());
            $this->assertArrayHasKey('Retry-After', $e->getHeaders());
        }
    }
}
