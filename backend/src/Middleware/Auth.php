<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Jwt;
use MediTrack\Core\Exceptions\UnauthorizedException;
use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Repositories\UserRepository;

class Auth implements MiddlewareInterface
{
    private Jwt $jwt;
    private UserRepository $userRepo;

    public function __construct(Jwt $jwt, UserRepository $userRepo)
    {
        $this->jwt = $jwt;
        $this->userRepo = $userRepo;
    }

    public function process(Request $request, callable $next): Response
    {
        $authHeader = $request->header('authorization');

        if (!$authHeader || !preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            throw new UnauthorizedException('Authentication token required.', 'UNAUTHORIZED');
        }

        $token = $matches[1];

        try {
            $claims = $this->jwt->decode($token);
        } catch (\Throwable $e) {
            throw new UnauthorizedException('Invalid or expired authentication token.', 'TOKEN_EXPIRED');
        }

        $userId = isset($claims['sub']) ? (int)$claims['sub'] : null;
        if (!$userId) {
            throw new UnauthorizedException('Invalid token payload.', 'INVALID_TOKEN');
        }

        $user = $this->userRepo->findById($userId);
        if (!$user) {
            throw new UnauthorizedException('User account not found.', 'USER_NOT_FOUND');
        }

        if ((int)$user['is_blocked'] === 1) {
            throw new ForbiddenException('Your account has been blocked. Please contact support.', 'ACCOUNT_BLOCKED');
        }

        // Attach user object and ID to request attributes
        $request = $request
            ->withAttribute('user', $user)
            ->withAttribute('user_id', $user['id'])
            ->withAttribute('role', $user['role']);

        return $next($request);
    }
}

