<?php

declare(strict_types=1);

namespace MediTrack\Tests\Feature;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Router;
use MediTrack\Core\Request;

class HealthEndpointTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = require dirname(__DIR__, 2) . '/bootstrap/app.php';
    }

    public function testHealthEndpointReturnsStatus(): void
    {
        $request = new Request('GET', '/health');
        $response = $this->router->dispatch($request);

        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('status', $payload['data']);
        $this->assertArrayHasKey('services', $payload['data']);
        $this->assertArrayHasKey('storage', $payload['data']['services']);
    }
}

