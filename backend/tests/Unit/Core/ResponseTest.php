<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Response;

class ResponseTest extends TestCase
{
    public function testResponseHeadersAndStatus(): void
    {
        $response = new Response('Hello World', 201, ['X-Custom' => 'Value']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Hello World', $response->getContent());
        $this->assertSame('Value', $response->getHeaders()['X-Custom']);

        $response->setStatusCode(200);
        $response->header('X-Another', 'Test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Test', $response->getHeaders()['X-Another']);
    }
}

