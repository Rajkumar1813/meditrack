<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Request;

class RequestTest extends TestCase
{
    public function testRequestParsingAndGetters(): void
    {
        $request = new Request(
            'POST',
            '/api/v1/medicines?category=tablet',
            ['Authorization' => 'Bearer sample_token_123', 'Content-Type' => 'application/json'],
            ['category' => 'tablet'],
            ['name' => 'Paracetamol', 'qty' => 10],
            ['session_id' => 'abc'],
            ['REMOTE_ADDR' => '192.168.1.10']
        );

        $this->assertSame('POST', $request->getMethod());
        $this->assertTrue($request->isMethod('post'));
        $this->assertSame('/api/v1/medicines', $request->getPath());
        $this->assertSame('tablet', $request->query('category'));
        $this->assertSame('Paracetamol', $request->input('name'));
        $this->assertSame(10, $request->input('qty'));
        $this->assertSame('sample_token_123', $request->bearerToken());
        $this->assertSame('192.168.1.10', $request->ip());
        $this->assertSame('abc', $request->cookie('session_id'));
    }

    public function testAttributesImmutability(): void
    {
        $req1 = new Request('GET', '/test');
        $req2 = $req1->withAttribute('user_id', 42);

        $this->assertNull($req1->getAttribute('user_id'));
        $this->assertSame(42, $req2->getAttribute('user_id'));
    }
}

