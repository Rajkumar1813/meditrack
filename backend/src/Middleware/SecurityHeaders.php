<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Request;
use MediTrack\Core\Response;

class SecurityHeaders implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'DENY');
        $response->header('X-XSS-Protection', '1; mode=block');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Content-Security-Policy', "default-src 'self'; script-src 'self'; object-src 'none'; frame-ancestors 'none';");

        // HSTS in production or over HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}

