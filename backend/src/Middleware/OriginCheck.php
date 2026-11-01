<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Exceptions\ForbiddenException;

class OriginCheck implements MiddlewareInterface
{
    private array $allowedOrigins;

    public function __construct(array $allowedOrigins = [])
    {
        if (empty($allowedOrigins)) {
            $corsConfig = require dirname(__DIR__, 2) . '/config/cors.php';
            $this->allowedOrigins = $corsConfig['allowed_origins'] ?? [];
        } else {
            $this->allowedOrigins = $allowedOrigins;
        }
    }

    public function process(Request $request, callable $next): Response
    {
        $method = $request->getMethod();
        $stateChangingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (in_array($method, $stateChangingMethods, true)) {
            $origin = $request->header('origin');
            $host = $request->header('host');

            if ($origin !== null) {
                $originHost = parse_url($origin, PHP_URL_HOST);
                $originPort = parse_url($origin, PHP_URL_PORT);
                $originWithPort = $originPort ? "{$originHost}:{$originPort}" : $originHost;

                $matchesHost = ($originWithPort === $host || $originHost === $host);
                $matchesAllowed = in_array($origin, $this->allowedOrigins, true);

                if (!$matchesHost && !$matchesAllowed) {
                    throw new ForbiddenException('Cross-site request blocked by OriginCheck.');
                }
            }
        }

        return $next($request);
    }
}

