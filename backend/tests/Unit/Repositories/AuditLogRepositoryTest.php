<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Repositories\AuditLogRepository;

class AuditLogRepositoryTest extends TestCase
{
    private Database $db;
    private AuditLogRepository $repo;

    protected function setUp(): void
    {
        $this->db = new Database([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->db->pdo()->exec("
            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                action TEXT NOT NULL,
                entity TEXT NOT NULL,
                entity_id INTEGER NULL,
                old_values TEXT NULL,
                new_values TEXT NULL,
                ip TEXT NULL,
                created_at TIMESTAMP NULL
            );
        ");

        $this->repo = new AuditLogRepository($this->db);
    }

    public function testLogAndRetrieveByEntityAndUser(): void
    {
        $logId = $this->repo->log(
            'update_stock',
            'batches',
            101,
            ['quantity' => 50],
            ['quantity' => 40],
            1,
            '127.0.0.1'
        );

        $this->assertGreaterThan(0, $logId);

        $byEntity = $this->repo->getByEntity('batches', 101);
        $this->assertCount(1, $byEntity);
        $this->assertSame('update_stock', $byEntity[0]['action']);
        $this->assertSame(101, (int)$byEntity[0]['entity_id']);
        $this->assertSame(1, (int)$byEntity[0]['user_id']);

        $byUser = $this->repo->getByUser(1);
        $this->assertCount(1, $byUser);

        $this->assertSame(1, $this->repo->count());
    }
}

