<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Router;
use MediTrack\Core\Request;

class BrandEndpointTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = require dirname(__DIR__, 2) . '/bootstrap/app.php';
    }

    public function testGetBrandReturnsBrandDataWithEtag(): void
    {
        $request = new Request('GET', '/api/v1/brand');
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertSame('MediTrack', $payload['data']['app']['name']);
        $this->assertNotEmpty($payload['meta']['etag']);
        $this->assertNotEmpty($response->getHeaders()['ETag']);
    }

    public function testGetBrandReturns304WhenEtagMatches(): void
    {
        $req1 = new Request('GET', '/api/v1/brand');
        $res1 = $this->router->dispatch($req1);
        $etag = $res1->getHeaders()['ETag'];

        $req2 = new Request('GET', '/api/v1/brand', ['If-None-Match' => $etag]);
        $res2 = $this->router->dispatch($req2);

        $this->assertSame(304, $res2->getStatusCode());
        $this->assertEmpty($res2->getContent());
    }
}

