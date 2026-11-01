<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Request;
use MediTrack\Core\Response;

interface MiddlewareInterface
{
    /**
     * Process an incoming request and return a response.
     *
     * @param Request $request
     * @param callable $next receives Request and returns Response
     * @return Response
     */
    public function process(Request $request, callable $next): Response;
}

