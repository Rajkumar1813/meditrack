<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Database;
use MediTrack\Core\Jwt;
use MediTrack\Core\Exceptions\UnauthorizedException;
use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Repositories\UserRepository;
use Monolog\Logger;

class AuthService
{
    private Database $db;
    private UserRepository $userRepo;
    private Jwt $jwt;
    private ?Logger $logger;

    public function __construct(Database $db, UserRepository $userRepo, Jwt $jwt, ?Logger $logger = null)
    {
        $this->db = $db;
        $this->userRepo = $userRepo;
        $this->jwt = $jwt;
        $this->logger = $logger;
    }

    /**
     * Generate an access token (15-min JWT) and a rotating refresh token (stored as sha256 hash).
     */
    public function issueTokens(array $user, ?string $ip = null, ?string $deviceName = null): array
    {
        // 1. Generate Access Token (15 min = 900 seconds)
        $claims = [
            'sub' => (int)$user['id'],
            'role' => $user['role'],
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'phone' => $user['phone'] ?? null,
        ];
        $accessToken = $this->jwt->encode($claims, 900);

        // 2. Generate Refresh Token (64 hex characters from 32 random bytes)
        $plainRefreshToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainRefreshToken);

        // 30 days expiry
        $expiresAt = date('Y-m-d H:i:s', time() + (30 * 86400));
        $now = date('Y-m-d H:i:s');

        $sql = 'INSERT INTO refresh_tokens (user_id, token_hash, device_name, ip, expires_at, revoked_at, replaced_by, created_at)
                VALUES (?, ?, ?, ?, ?, NULL, NULL, ?)';
        $this->db->execute($sql, [
            $user['id'],
            $tokenHash,
            $deviceName,
            $ip,
            $expiresAt,
            $now,
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $plainRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 900,
            'user' => $this->sanitizeUser($user),
        ];
    }

    /**
     * Rotate refresh token. Implements reuse detection: if a revoked token is re-used,
     * immediately revoke ALL tokens for that user.
     */
    public function rotateRefreshToken(string $plainRefreshToken, ?string $ip = null, ?string $deviceName = null): array
    {
        $tokenHash = hash('sha256', trim($plainRefreshToken));

        $record = $this->db->fetchOne(
            'SELECT * FROM refresh_tokens WHERE token_hash = ?',
            [$tokenHash]
        );

        if (!$record) {
            throw new UnauthorizedException('Invalid refresh token.', 'INVALID_TOKEN');
        }

        $userId = (int)$record['user_id'];

        // Token Reuse Detection: If token is already revoked, potential token theft!
        if (!empty($record['revoked_at'])) {
            // Revoke all active sessions for this user immediately!
            $this->db->execute(
                'UPDATE refresh_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND revoked_at IS NULL',
                [$userId]
            );

            if ($this->logger) {
                $this->logger->alert("Refresh token reuse detected for User ID {$userId}. All tokens revoked.", [
                    'ip' => $ip,
                    'compromised_token_id' => $record['id'],
                ]);
            }

            throw new UnauthorizedException(
                'Token reuse detected. All sessions have been terminated for security. Please log in again.',
                'TOKEN_REUSE_DETECTED'
            );
        }

        // Check if token has expired
        if (strtotime($record['expires_at']) < time()) {
            throw new UnauthorizedException('Refresh token has expired. Please log in again.', 'TOKEN_EXPIRED');
        }

        // Fetch user and check blocked status
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            throw new UnauthorizedException('User account not found.', 'USER_NOT_FOUND');
        }

        if ((int)$user['is_blocked'] === 1) {
            throw new ForbiddenException('Your account has been blocked. Please contact support.', 'ACCOUNT_BLOCKED');
        }

        // Generate new token pair
        $newTokens = $this->issueTokens($user, $ip, $deviceName);
        $newTokenRecord = $this->db->fetchOne(
            'SELECT id FROM refresh_tokens WHERE token_hash = ?',
            [hash('sha256', $newTokens['refresh_token'])]
        );
        $newId = $newTokenRecord ? (int)$newTokenRecord['id'] : null;

        // Mark old token revoked and record replacement
        $this->db->execute(
            'UPDATE refresh_tokens SET revoked_at = CURRENT_TIMESTAMP, replaced_by = ? WHERE id = ?',
            [$newId, $record['id']]
        );

        return $newTokens;
    }

    /**
     * Revoke refresh token upon logout.
     */
    public function revokeToken(?string $plainRefreshToken): void
    {
        if (empty($plainRefreshToken)) {
            return;
        }

        $tokenHash = hash('sha256', trim($plainRefreshToken));
        $this->db->execute(
            'UPDATE refresh_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE token_hash = ? AND revoked_at IS NULL',
            [$tokenHash]
        );
    }

    /**
     * Revoke all refresh tokens for a user.
     */
    public function revokeAllUserTokens(int $userId): void
    {
        $this->db->execute(
            'UPDATE refresh_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND revoked_at IS NULL',
            [$userId]
        );
    }

    public function sanitizeUser(array $user): array
    {
        return [
            'id' => (int)$user['id'],
            'name' => $user['name'] ?? null,
            'phone' => $user['phone'] ?? null,
            'email' => $user['email'] ?? null,
            'role' => $user['role'],
            'locale' => $user['locale'] ?? 'en',
            'is_blocked' => (bool)($user['is_blocked'] ?? 0),
            'pharmacist_reg_no' => $user['pharmacist_reg_no'] ?? null,
        ];
    }
}

