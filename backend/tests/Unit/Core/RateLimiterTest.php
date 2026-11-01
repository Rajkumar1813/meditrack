<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\RateLimiter;

class RateLimiterTest extends TestCase
{
    public function testFallbackWhenRedisIsNull(): void
    {
        $limiter = new RateLimiter(null);

        // Fail-open for catalog/search
        [$exceededOpen, $remainingOpen, $retryAfterOpen] = $limiter->check('test_key', 60, 60, false);
        $this->assertFalse($exceededOpen);
        $this->assertSame(60, $remainingOpen);
        $this->assertSame(0, $retryAfterOpen);

        // Fail-closed for auth/otp
        [$exceededClosed, $remainingClosed, $retryAfterClosed] = $limiter->check('test_key', 5, 300, true);
        $this->assertTrue($exceededClosed);
        $this->assertSame(0, $remainingClosed);
        $this->assertSame(300, $retryAfterClosed);
    }
}

