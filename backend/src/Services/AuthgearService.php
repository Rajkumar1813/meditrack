<?php

declare(strict_types=1);

namespace MediTrack\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use MediTrack\Repositories\UserRepository;
use MediTrack\Core\Exceptions\UnauthorizedException;

class AuthgearService
{
    private string $endpoint;
    private string $clientId;
    private string $redirectUri;
    private int $cacheTtl;
    private UserRepository $userRepo;
    private string $cacheFile;

    public function __construct(UserRepository $userRepo, ?array $config = null)
    {
        $this->userRepo = $userRepo;
        $config = $config ?? (file_exists(dirname(__DIR__, 2) . '/config/authgear.php')
            ? require dirname(__DIR__, 2) . '/config/authgear.php'
            : []);

        $this->endpoint = rtrim($config['endpoint'] ?? ($_ENV['AUTHGEAR_ENDPOINT'] ?? ''), '/');
        $this->clientId = $config['client_id'] ?? ($_ENV['AUTHGEAR_CLIENT_ID'] ?? '');
        $this->redirectUri = $config['redirect_uri'] ?? ($_ENV['AUTHGEAR_REDIRECT_URI'] ?? '');
        $this->cacheTtl = (int)($config['jwks_cache_ttl'] ?? 86400);

        $cacheDir = dirname(__DIR__, 2) . '/storage/framework/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $this->cacheFile = $cacheDir . '/authgear_jwks.json';
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * Fetch JWKS from Authgear endpoint with disk caching
     */
    public function getJwks(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && file_exists($this->cacheFile)) {
            $mtime = filemtime($this->cacheFile);
            if ($mtime && (time() - $mtime < $this->cacheTtl)) {
                $cached = json_decode((string)file_get_contents($this->cacheFile), true);
                if (is_array($cached) && !empty($cached['keys'])) {
                    return $cached;
                }
            }
        }

        if (empty($this->endpoint) || str_contains($this->endpoint, 'your-project')) {
            return ['keys' => []];
        }

        $jwksUrl = $this->endpoint . '/oauth2/jwks';
        $ch = curl_init($jwksUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MediTrack-Authgear-Verifier/1.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($response)) {
            $jwks = json_decode($response, true);
            if (is_array($jwks) && !empty($jwks['keys'])) {
                @file_put_contents($this->cacheFile, $response);
                return $jwks;
            }
        }

        return ['keys' => []];
    }

    /**
     * Verify Authgear JWT access token against JWKS
     *
     * @param string $token
     * @return array Decoded token claims
     * @throws UnauthorizedException
     */
    public function verifyToken(string $token): array
    {
        $jwks = $this->getJwks();

        try {
            $claims = $this->decodeWithJwks($token, $jwks);
        } catch (\Throwable $e) {
            // Retry once with fresh JWKS in case key rotation occurred
            $jwks = $this->getJwks(true);
            try {
                $claims = $this->decodeWithJwks($token, $jwks);
            } catch (\Throwable $secondEx) {
                throw new UnauthorizedException('Invalid or expired Authgear token: ' . $secondEx->getMessage(), 'TOKEN_INVALID');
            }
        }

        // Validate Issuer
        $expectedIss = $this->endpoint;
        if (!empty($expectedIss) && !str_contains($expectedIss, 'your-project')) {
            $tokenIss = rtrim((string)($claims['iss'] ?? ''), '/');
            if ($tokenIss !== $expectedIss) {
                throw new UnauthorizedException('Token issuer mismatch.', 'INVALID_ISSUER');
            }
        }

        // Validate Audience (if configured)
        if (!empty($this->clientId) && $this->clientId !== 'your_client_id') {
            $aud = $claims['aud'] ?? null;
            $audValid = false;
            if (is_string($aud) && $aud === $this->clientId) {
                $audValid = true;
            } elseif (is_array($aud) && in_array($this->clientId, $aud, true)) {
                $audValid = true;
            }
            if (!$audValid) {
                throw new UnauthorizedException('Token audience mismatch.', 'INVALID_AUDIENCE');
            }
        }

        // Validate Expiration
        if (isset($claims['exp']) && $claims['exp'] < time()) {
            throw new UnauthorizedException('Token has expired.', 'TOKEN_EXPIRED');
        }

        // Ensure subject exists
        if (empty($claims['sub'])) {
            throw new UnauthorizedException('Token subject (sub) missing.', 'INVALID_TOKEN');
        }

        return $claims;
    }

    private function decodeWithJwks(string $token, array $jwks): array
    {
        if (empty($jwks['keys'])) {
            throw new \RuntimeException('JWKS keys empty or unconfigured');
        }
        $keys = JWK::parseKeySet($jwks);
        $decoded = JWT::decode($token, $keys);
        return (array)$decoded;
    }

    /**
     * Fetch UserInfo from Authgear /oauth2/userinfo using access token
     */
    public function fetchUserInfo(string $accessToken): ?array
    {
        if (empty($this->endpoint) || str_contains($this->endpoint, 'your-project')) {
            return null;
        }

        $userInfoUrl = $this->endpoint . '/oauth2/userinfo';
        $ch = curl_init($userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($response)) {
            $data = json_decode($response, true);
            return is_array($data) ? $data : null;
        }

        return null;
    }

    /**
     * Map or auto-provision user from Authgear claims & UserInfo
     *
     * @param array $claims
     * @param string|null $rawToken
     * @return array Local user database record
     */
    public function syncUserFromClaims(array $claims, ?string $rawToken = null): array
    {
        $authgearUserId = (string)$claims['sub'];

        // 1. Check if user already linked via authgear_user_id
        $user = $this->userRepo->findByAuthgearId($authgearUserId);
        if ($user) {
            $this->userRepo->updateLastLogin((int)$user['id']);
            return $user;
        }

        // 2. If claims lack email or phone, query /oauth2/userinfo if rawToken provided
        $userInfo = null;
        if ($rawToken && (empty($claims['email']) && empty($claims['phone_number']))) {
            $userInfo = $this->fetchUserInfo($rawToken);
            if ($userInfo) {
                $claims = array_merge($claims, $userInfo);
            }
        }

        $email = !empty($claims['email']) ? (string)$claims['email'] : null;
        $phone = !empty($claims['phone_number']) ? (string)$claims['phone_number'] : null;
        $name = !empty($claims['name']) ? (string)$claims['name'] : (!empty($claims['formatted_name']) ? (string)$claims['formatted_name'] : null);

        // 3. Try to link with existing user by email or phone
        $existing = null;
        if ($phone) {
            $existing = $this->userRepo->findByPhone($phone);
        }
        if (!$existing && $email) {
            $existing = $this->userRepo->findByEmail($email);
        }

        if ($existing) {
            $updateData = ['authgear_user_id' => $authgearUserId];
            if ($phone && empty($existing['phone'])) {
                $updateData['phone'] = $phone;
            }
            if ($email && empty($existing['email'])) {
                $updateData['email'] = $email;
            }
            $this->userRepo->update((int)$existing['id'], $updateData);
            $this->userRepo->updateLastLogin((int)$existing['id']);
            return $this->userRepo->findById((int)$existing['id']);
        }

        // 4. Auto-provision new customer user
        $newUserId = $this->userRepo->create([
            'authgear_user_id' => $authgearUserId,
            'name' => $name ?? 'MediTrack User',
            'phone' => $phone,
            'email' => $email,
            'role' => 'customer',
            'locale' => 'en',
            'is_blocked' => 0,
            'phone_verified_at' => !empty($claims['phone_number_verified']) ? date('Y-m-d H:i:s') : ($phone ? date('Y-m-d H:i:s') : null),
            'email_verified_at' => !empty($claims['email_verified']) ? date('Y-m-d H:i:s') : ($email ? date('Y-m-d H:i:s') : null),
        ]);

        $this->userRepo->updateLastLogin($newUserId);
        return $this->userRepo->findById($newUserId);
    }
}

