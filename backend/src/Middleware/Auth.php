<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Jwt;
use MediTrack\Core\Exceptions\UnauthorizedException;
use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Repositories\UserRepository;
use MediTrack\Services\AuthgearService;

class Auth implements MiddlewareInterface
{
    private Jwt $jwt;
    private UserRepository $userRepo;
    private ?AuthgearService $authgearService;

    public function __construct(Jwt $jwt, UserRepository $userRepo, ?AuthgearService $authgearService = null)
    {
        $this->jwt = $jwt;
        $this->userRepo = $userRepo;
        $this->authgearService = $authgearService;
    }

    public function process(Request $request, callable $next): Response
    {
        $authHeader = $request->header('authorization');

        if (!$authHeader || !preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            throw new UnauthorizedException('Authentication token required.', 'UNAUTHORIZED');
        }

        $token = $matches[1];
        $user = null;

        // 1. Attempt Authgear JWKS token verification
        if ($this->authgearService !== null) {
            try {
                $claims = $this->authgearService->verifyToken($token);
                $user = $this->authgearService->syncUserFromClaims($claims, $token);
            } catch (\Throwable $e) {
                // Token was not verified by Authgear; attempt fallback to local JWT
            }
        }

        // 2. Fallback to local JWT verification (for unit/integration tests and internal tokens)
        if (!$user) {
            try {
                $claims = $this->jwt->decode($token);
                $userId = isset($claims['sub']) ? (int)$claims['sub'] : null;
                if ($userId) {
                    $user = $this->userRepo->findById($userId);
                }
            } catch (\Throwable $e) {
                throw new UnauthorizedException('Invalid or expired authentication token.', 'TOKEN_EXPIRED');
            }
        }

        if (!$user) {
            throw new UnauthorizedException('User account not found.', 'USER_NOT_FOUND');
        }

        if ((int)$user['is_blocked'] === 1) {
            throw new ForbiddenException('Your account has been blocked. Please contact support.', 'ACCOUNT_BLOCKED');
        }

        // Attach user object, user ID, and role to request attributes
        $request = $request
            ->withAttribute('user', $user)
            ->withAttribute('user_id', (int)$user['id'])
            ->withAttribute('role', $user['role']);

        return $next($request);
    }
}
