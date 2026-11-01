<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Request;
use MediTrack\Core\Response;

class ForceJson implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = $response->getHeaders();
        if (!isset($headers['Content-Type']) && !isset($headers['content-type'])) {
            $response->header('Content-Type', 'application/json; charset=utf-8');
        }

        return $response;
    }
}

