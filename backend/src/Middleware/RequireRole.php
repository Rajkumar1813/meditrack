<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Core\Exceptions\UnauthorizedException;
use MediTrack\Enums\Role;

class RequireRole implements MiddlewareInterface
{
    private array $allowedRoles;

    /**
     * @param array<Role|string> $allowedRoles
     */
    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = array_map(function ($role) {
            return $role instanceof Role ? $role->value : (string)$role;
        }, $allowedRoles);
    }

    public static function roles(Role|string ...$roles): self
    {
        return new self($roles);
    }

    public function process(Request $request, callable $next): Response
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            throw new UnauthorizedException('Authentication required.', 'UNAUTHORIZED');
        }

        $userRole = $user['role'] ?? null;

        if (!$userRole || !in_array($userRole, $this->allowedRoles, true)) {
            throw new ForbiddenException(
                'You do not have permission to access this resource.',
                'FORBIDDEN'
            );
        }

        return $next($request);
    }
}

