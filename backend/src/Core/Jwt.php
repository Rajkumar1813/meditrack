<?php

declare(strict_types=1);

namespace MediTrack\Core;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use MediTrack\Core\Exceptions\UnauthorizedException;

class Jwt
{
    private string $secret;
    private string $algorithm;

    public function __construct(?string $secret = null, string $algorithm = 'HS256')
    {
        $this->secret = $secret ?? ($_ENV['JWT_SECRET'] ?? 'default-secret-change-in-production-min32');
        $this->algorithm = $algorithm;
    }

    public function encode(array $claims, int $ttlSeconds = 900): string
    {
        $now = time();
        $payload = array_merge([
            'iss' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttlSeconds,
        ], $claims);

        return FirebaseJWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function decode(string $token): array
    {
        try {
            $decoded = FirebaseJWT::decode($token, new Key($this->secret, $this->algorithm));
            return (array)$decoded;
        } catch (\Throwable $e) {
            throw new UnauthorizedException('Invalid or expired token: ' . $e->getMessage());
        }
    }

    public function tryDecode(string $token): ?array
    {
        try {
            return $this->decode($token);
        } catch (\Throwable) {
            return null;
        }
    }
}

