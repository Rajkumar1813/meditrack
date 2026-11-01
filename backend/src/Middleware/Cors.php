<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Request;
use MediTrack\Core\Response;

class Cors implements MiddlewareInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = !empty($config) ? $config : (require dirname(__DIR__, 2) . '/config/cors.php');
    }

    public function process(Request $request, callable $next): Response
    {
        $origin = $request->header('origin');
        $allowedOrigins = $this->config['allowed_origins'] ?? [];

        $isAllowed = false;
        if ($origin && (in_array($origin, $allowedOrigins, true) || in_array('*', $allowedOrigins, true))) {
            $isAllowed = true;
        }

        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            $response = new Response('', 204);
            $this->addCorsHeaders($response, $origin, $isAllowed);
            return $response;
        }

        /** @var Response $response */
        $response = $next($request);
        $this->addCorsHeaders($response, $origin, $isAllowed);

        return $response;
    }

    private function addCorsHeaders(Response $response, ?string $origin, bool $isAllowed): void
    {
        if ($isAllowed && $origin) {
            $response->header('Access-Control-Allow-Origin', $origin);
            if (!empty($this->config['supports_credentials'])) {
                $response->header('Access-Control-Allow-Credentials', 'true');
            }
        }

        $methods = implode(', ', $this->config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
        $headers = implode(', ', $this->config['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With']);
        $exposed = implode(', ', $this->config['exposed_headers'] ?? []);

        $response->header('Access-Control-Allow-Methods', $methods);
        $response->header('Access-Control-Allow-Headers', $headers);

        if (!empty($exposed)) {
            $response->header('Access-Control-Expose-Headers', $exposed);
        }

        if (isset($this->config['max_age'])) {
            $response->header('Access-Control-Max-Age', (string)$this->config['max_age']);
        }
    }
}

