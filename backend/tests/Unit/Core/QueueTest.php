<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Queue;
use MediTrack\Core\Database;

class QueueTest extends TestCase
{
    public function testFailedJobsRecordedInDatabase(): void
    {
        $db = new Database(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->pdo()->exec('
            CREATE TABLE failed_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue TEXT,
                payload TEXT,
                error TEXT,
                failed_at DATETIME
            )
        ');

        $queue = new Queue(null, $db);
        $exception = new \Exception('Email provider timeout');

        $queue->fail('emails', ['to' => 'user@example.com'], $exception);

        $row = $db->fetchOne('SELECT * FROM failed_jobs WHERE queue = ?', ['emails']);
        $this->assertNotNull($row);
        $this->assertStringContainsString('Email provider timeout', $row['error']);
        $this->assertStringContainsString('user@example.com', $row['payload']);
    }
}

