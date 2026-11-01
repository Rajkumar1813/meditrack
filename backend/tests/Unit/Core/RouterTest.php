<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Router;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Exceptions\NotFoundException;
use MediTrack\Middleware\MiddlewareInterface;

class SampleMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);
        $response->header('X-Middleware-Applied', 'true');
        return $response;
    }
}

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testRouteMatchingAndGroupPrefix(): void
    {
        $this->router->group(['prefix' => '/api/v1'], function (Router $r) {
            $r->get('/items/{id}', function (Request $req, $id) {
                return new Response("Item: {$id}");
            });
        });

        $request = new Request('GET', '/api/v1/items/99');
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Item: 99', $response->getContent());
    }

    public function testMiddlewarePipelineExecution(): void
    {
        $this->router->get('/test-mw', function () {
            return new Response('OK');
        }, [SampleMiddleware::class]);

        $request = new Request('GET', '/test-mw');
        $response = $this->router->dispatch($request);

        $this->assertSame('OK', $response->getContent());
        $this->assertSame('true', $response->getHeaders()['X-Middleware-Applied']);
    }

    public function testRouteNotFoundThrowsNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        $request = new Request('GET', '/non-existent-path');
        $this->router->dispatch($request);
    }
}

