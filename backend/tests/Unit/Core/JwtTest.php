<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Jwt;
use MediTrack\Core\Exceptions\UnauthorizedException;

class JwtTest extends TestCase
{
    private Jwt $jwt;

    protected function setUp(): void
    {
        $this->jwt = new Jwt('super-secret-key-32-bytes-test-suite!!');
    }

    public function testEncodeAndDecode(): void
    {
        $token = $this->jwt->encode(['sub' => 101, 'role' => 'pharmacist'], 300);
        $this->assertNotEmpty($token);

        $decoded = $this->jwt->decode($token);
        $this->assertSame(101, $decoded['sub']);
        $this->assertSame('pharmacist', $decoded['role']);
    }

    public function testExpiredTokenThrowsUnauthorized(): void
    {
        $token = $this->jwt->encode(['sub' => 101], -10); // expired 10 seconds ago

        $this->expectException(UnauthorizedException::class);
        $this->jwt->decode($token);
    }
}

