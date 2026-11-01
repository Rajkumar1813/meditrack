<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;

class DatabaseTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->db->pdo()->exec('CREATE TABLE test_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, qty INTEGER)');
    }

    public function testPreparedExecutionAndFetch(): void
    {
        $inserted = $this->db->execute('INSERT INTO test_items (name, qty) VALUES (?, ?)', ['Aspirin', 100]);
        $this->assertSame(1, $inserted);

        $row = $this->db->fetchOne('SELECT * FROM test_items WHERE name = ?', ['Aspirin']);
        $this->assertNotNull($row);
        $this->assertSame('Aspirin', $row['name']);
        $this->assertEquals(100, $row['qty']);
    }

    public function testTransactionCommit(): void
    {
        $this->db->transaction(function (Database $db) {
            $db->execute('INSERT INTO test_items (name, qty) VALUES (?, ?)', ['Item1', 10]);
            $db->execute('INSERT INTO test_items (name, qty) VALUES (?, ?)', ['Item2', 20]);
        });

        $items = $this->db->fetchAll('SELECT * FROM test_items');
        $this->assertCount(2, $items);
    }

    public function testTransactionRollbackOnException(): void
    {
        try {
            $this->db->transaction(function (Database $db) {
                $db->execute('INSERT INTO test_items (name, qty) VALUES (?, ?)', ['ItemRollback', 50]);
                throw new \RuntimeException('Fail inside transaction');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $row = $this->db->fetchOne('SELECT * FROM test_items WHERE name = ?', ['ItemRollback']);
        $this->assertNull($row);
    }
}

