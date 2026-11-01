<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Repositories\SettingRepository;

class SettingRepositoryTest extends TestCase
{
    private Database $db;
    private SettingRepository $repo;

    protected function setUp(): void
    {
        $this->db = new Database([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->db->pdo()->exec("
            CREATE TABLE settings (
                `key` TEXT PRIMARY KEY,
                `value` TEXT NULL,
                updated_at TIMESTAMP NULL
            );
        ");

        $this->repo = new SettingRepository($this->db);
    }

    public function testSetAndGetSetting(): void
    {
        $this->repo->set('shop_name', 'Sharma Medical Store');
        $this->assertSame('Sharma Medical Store', $this->repo->get('shop_name'));

        // Update existing setting
        $this->repo->set('shop_name', 'New Sharma Medical Store');
        $this->assertSame('New Sharma Medical Store', $this->repo->get('shop_name'));
    }

    public function testGetDefaultWhenKeyNotFound(): void
    {
        $this->assertNull($this->repo->get('non_existent_key'));
        $this->assertSame('fallback', $this->repo->get('non_existent_key', 'fallback'));
    }

    public function testAllAndHasAndDelete(): void
    {
        $this->repo->set('k1', 'v1');
        $this->repo->set('k2', 'v2');

        $this->assertTrue($this->repo->has('k1'));
        $this->assertFalse($this->repo->has('k3'));

        $all = $this->repo->all();
        $this->assertSame(['k1' => 'v1', 'k2' => 'v2'], $all);

        $this->assertTrue($this->repo->delete('k1'));
        $this->assertFalse($this->repo->has('k1'));
    }
}

