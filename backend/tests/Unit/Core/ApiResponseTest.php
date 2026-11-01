<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\ApiResponse;

class ApiResponseTest extends TestCase
{
    public function testSuccessEnvelopeShape(): void
    {
        $response = ApiResponse::success(['id' => 1, 'name' => 'Dolo 650'], 200, ['cached' => true]);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertSame('Dolo 650', $payload['data']['name']);
        $this->assertTrue($payload['meta']['cached']);
        $this->assertNull($payload['message']);
    }

    public function testErrorEnvelopeShape(): void
    {
        $response = ApiResponse::error('VALIDATION_FAILED', 'Invalid input', 422, ['phone' => ['Invalid phone']]);

        $this->assertSame(422, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['success']);
        $this->assertSame('VALIDATION_FAILED', $payload['error']['code']);
        $this->assertSame('Invalid input', $payload['error']['message']);
        $this->assertSame(['Invalid phone'], $payload['error']['fields']['phone']);
    }

    public function testPaginatedEnvelopeShape(): void
    {
        $paginationMeta = [
            'type' => 'offset',
            'page' => 1,
            'per_page' => 20,
            'total' => 50,
        ];

        $response = ApiResponse::paginated([['id' => 1]], $paginationMeta);
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('offset', $payload['meta']['pagination']['type']);
        $this->assertSame(50, $payload['meta']['pagination']['total']);
    }
}

