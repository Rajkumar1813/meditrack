<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Database;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Core\Exceptions\HttpException;
use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Core\Exceptions\RateLimitException;
use MediTrack\Repositories\UserRepository;
use MediTrack\Jobs\SendOtpJob;
use MediTrack\Enums\Role;
use Monolog\Logger;

class OtpService
{
    private Database $db;
    private UserRepository $userRepo;
    private ?Logger $logger;

    public function __construct(Database $db, UserRepository $userRepo, ?Logger $logger = null)
    {
        $this->db = $db;
        $this->userRepo = $userRepo;
        $this->logger = $logger;
    }

    public function requestOtp(string $identifier, string $type, ?string $ip = null): array
    {
        $cleanIdentifier = $this->normalizeIdentifier($identifier, $type);

        // 1. Check if identifier is currently locked (15-min lock)
        $lockedRecord = $this->db->fetchOne(
            'SELECT locked_until FROM otp_codes WHERE identifier = ? AND locked_until > CURRENT_TIMESTAMP ORDER BY id DESC LIMIT 1',
            [$cleanIdentifier]
        );

        if ($lockedRecord) {
            $diff = strtotime($lockedRecord['locked_until']) - time();
            $retryAfter = max(1, $diff);
            throw new HttpException(
                423,
                "Too many failed attempts. Account is locked. Try again in {$retryAfter} seconds.",
                'ACCOUNT_LOCKED',
                ['Retry-After' => (string)$retryAfter]
            );
        }

        // 2. Check 60-second cooldown
        $latestRecord = $this->db->fetchOne(
            'SELECT created_at FROM otp_codes WHERE identifier = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1',
            [$cleanIdentifier]
        );

        if ($latestRecord && !empty($latestRecord['created_at'])) {
            $elapsed = time() - strtotime($latestRecord['created_at']);
            if ($elapsed < 60) {
                $retryAfter = 60 - $elapsed;
                throw new RateLimitException(
                    $retryAfter,
                    "Please wait {$retryAfter} seconds before requesting a new OTP."
                );
            }
        }

        // 3. Generate 6-digit random code
        $code = sprintf('%06d', random_int(100000, 999999));
        $codeHash = password_hash($code, PASSWORD_BCRYPT);

        // Dev log
        $env = $_ENV['APP_ENV'] ?? 'local';
        if ($env !== 'production') {
            $logMsg = "[OTP DEV LOG] Target: {$cleanIdentifier} ({$type}) | OTP: {$code}";
            if ($this->logger) {
                $this->logger->info($logMsg);
            } else {
                error_log($logMsg);
            }
        }

        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 min expiry

        // 4. Save to DB
        $sql = 'INSERT INTO otp_codes (identifier, type, code_hash, attempts, locked_until, expires_at, consumed_at, ip, created_at)
                VALUES (?, ?, ?, 0, NULL, ?, NULL, ?, ?)';
        $this->db->execute($sql, [$cleanIdentifier, $type, $codeHash, $expiresAt, $ip, $now]);

        // 5. Dispatch Queue Job for SMS/Email
        SendOtpJob::dispatch($type, $cleanIdentifier, $code);

        return [
            'identifier' => $cleanIdentifier,
            'type' => $type,
            'cooldown_seconds' => 60,
            'expires_in' => 300,
        ];
    }

    public function verifyOtp(string $identifier, string $code, ?string $ip = null): array
    {
        $cleanIdentifier = $this->normalizeIdentifier($identifier, str_contains($identifier, '@') ? 'email' : 'phone');

        // 1. Check if currently locked
        $lockedRecord = $this->db->fetchOne(
            'SELECT locked_until FROM otp_codes WHERE identifier = ? AND locked_until > CURRENT_TIMESTAMP ORDER BY id DESC LIMIT 1',
            [$cleanIdentifier]
        );

        if ($lockedRecord) {
            $diff = strtotime($lockedRecord['locked_until']) - time();
            $retryAfter = max(1, $diff);
            throw new HttpException(
                423,
                "Too many failed attempts. Account is locked. Try again in {$retryAfter} seconds.",
                'ACCOUNT_LOCKED',
                ['Retry-After' => (string)$retryAfter]
            );
        }

        // 2. Fetch latest active non-consumed OTP
        $record = $this->db->fetchOne(
            'SELECT * FROM otp_codes WHERE identifier = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1',
            [$cleanIdentifier]
        );

        if (!$record) {
            throw new ValidationException(['code' => ['No active OTP found. Please request a new one.']]);
        }

        // 3. Check expiry
        if (strtotime($record['expires_at']) < time()) {
            throw new ValidationException(['code' => ['OTP has expired. Please request a new one.']]);
        }

        // 4. Check attempts
        if ((int)$record['attempts'] >= 5) {
            $lockUntil = date('Y-m-d H:i:s', time() + 900);
            $this->db->execute('UPDATE otp_codes SET locked_until = ? WHERE id = ?', [$lockUntil, $record['id']]);
            throw new HttpException(
                423,
                'Too many failed attempts. Try again in 15 minutes.',
                'ACCOUNT_LOCKED',
                ['Retry-After' => '900']
            );
        }

        // 5. Verify code
        if (!password_verify($code, $record['code_hash'])) {
            $newAttempts = (int)$record['attempts'] + 1;
            if ($newAttempts >= 5) {
                $lockUntil = date('Y-m-d H:i:s', time() + 900);
                $this->db->execute('UPDATE otp_codes SET attempts = ?, locked_until = ? WHERE id = ?', [$newAttempts, $lockUntil, $record['id']]);
                throw new HttpException(
                    423,
                    'Too many failed attempts. Try again in 15 minutes.',
                    'ACCOUNT_LOCKED',
                    ['Retry-After' => '900']
                );
            }

            $this->db->execute('UPDATE otp_codes SET attempts = ? WHERE id = ?', [$newAttempts, $record['id']]);
            $remaining = 5 - $newAttempts;
            throw new ValidationException(['code' => ["Invalid OTP code. {$remaining} attempts remaining."]]);
        }

        // 6. Valid OTP -> Mark consumed
        $this->db->execute('UPDATE otp_codes SET consumed_at = CURRENT_TIMESTAMP WHERE id = ?', [$record['id']]);

        // 7. Find or create User
        $type = $record['type'];
        $user = $type === 'phone'
            ? $this->userRepo->findByPhone($cleanIdentifier)
            : $this->userRepo->findByEmail($cleanIdentifier);

        if ($user) {
            if ((int)$user['is_blocked'] === 1) {
                throw new ForbiddenException('Your account is blocked. Please contact support.', 'ACCOUNT_BLOCKED');
            }

            // Update verification timestamp if not yet set
            $col = $type === 'phone' ? 'phone_verified_at' : 'email_verified_at';
            if (empty($user[$col])) {
                $this->db->execute("UPDATE users SET {$col} = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$user['id']]);
                $user[$col] = date('Y-m-d H:i:s');
            }
        } else {
            // Auto-create new user with customer role
            $insertData = [
                'name' => null,
                'role' => Role::CUSTOMER->value,
                'locale' => 'en',
                'is_blocked' => 0,
            ];
            if ($type === 'phone') {
                $insertData['phone'] = $cleanIdentifier;
                $insertData['phone_verified_at'] = date('Y-m-d H:i:s');
            } else {
                $insertData['email'] = $cleanIdentifier;
                $insertData['email_verified_at'] = date('Y-m-d H:i:s');
            }

            $userId = $this->userRepo->create($insertData);
            $user = $this->userRepo->findById($userId);
        }

        return $user;
    }

    public function normalizeIdentifier(string $identifier, string $type): string
    {
        $id = trim($identifier);
        if ($type === 'email') {
            return strtolower($id);
        }

        // Phone normalization: keep digits and leading '+'
        $clean = preg_replace('/[^\d\+]/', '', $id);
        if (str_starts_with($clean, '+91')) {
            return $clean;
        }
        if (str_starts_with($clean, '91') && strlen($clean) === 12) {
            return '+' . $clean;
        }
        if (strlen($clean) === 10) {
            return '+91' . $clean;
        }

        return $clean;
    }
}

