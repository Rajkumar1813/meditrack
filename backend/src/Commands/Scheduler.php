<?php

declare(strict_types=1);

namespace MediTrack\Commands;

use MediTrack\Core\Container;
use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;

class Scheduler
{
    private Container $container;
    private ?RedisClient $redis;
    private ?LoggerInterface $logger;
    /** @var CommandInterface[] */
    private array $commands = [];

    public function __construct(Container $container, ?RedisClient $redis = null, ?LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->redis = $redis;
        $this->logger = $logger;
    }

    public function register(CommandInterface $command): self
    {
        $this->commands[$command->name()] = $command;
        return $this;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function run(?\DateTimeImmutable $now = null, bool $force = false): array
    {
        $now = $now ?? new \DateTimeImmutable();
        $results = [];

        foreach ($this->commands as $command) {
            $name = $command->name();

            if (!$force && !$command->shouldRun($now)) {
                continue;
            }

            // Acquire Redis Mutex Lock with SET NX
            if ($this->redis !== null) {
                $lockKey = "schedule:lock:{$name}:" . $this->resolveLockSlot($name, $now);
                $lockTtl = $this->resolveLockTtl($name);

                try {
                    $res = $this->redis->set($lockKey, (string)time(), 'EX', $lockTtl, 'NX');
                    $acquired = ($res === true || $res === 'OK' || (string)$res === '1');

                    if (!$acquired) {
                        $results[$name] = 'skipped: locked by another process';
                        if ($this->logger) {
                            $this->logger->info("Scheduled command [{$name}] skipped (Redis lock active: {$lockKey}).");
                        }
                        continue;
                    }
                } catch (\Throwable $e) {
                    // Redis connection issue: continue running command
                    if ($this->logger) {
                        $this->logger->warning("Redis lock check failed for [{$name}]: " . $e->getMessage());
                    }
                }
            }

            try {
                $output = $command->execute();
                $results[$name] = $output;
                if ($this->logger) {
                    $this->logger->info("Scheduled command [{$name}] executed successfully: " . json_encode($output));
                }
            } catch (\Throwable $e) {
                $results[$name] = 'error: ' . $e->getMessage();
                if ($this->logger) {
                    $this->logger->error("Scheduled command [{$name}] failed: " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    private function resolveLockSlot(string $name, \DateTimeImmutable $now): string
    {
        return match ($name) {
            'expire_unpaid_orders' => $now->format('Y-m-d_H_') . (int)((int)$now->format('i') / 5),
            'hourly_refill_reminder' => $now->format('Y-m-d_H'),
            'daily_expiry_alert', 'nightly_low_stock_digest' => $now->format('Y-m-d'),
            default => $now->format('Y-m-d_H:i'),
        };
    }

    private function resolveLockTtl(string $name): int
    {
        return match ($name) {
            'expire_unpaid_orders' => 290,       // ~5 min
            'hourly_refill_reminder' => 3500,    // ~1 hour
            'daily_expiry_alert', 'nightly_low_stock_digest' => 86000, // ~24 hours
            default => 300,
        };
    }
}

