<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Database;
use MediTrack\Core\QueryBuilder;

class QueryBuilderTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->db->pdo()->exec('CREATE TABLE medicines (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, category TEXT, price REAL)');
    }

    public function testSelectAndWheres(): void
    {
        $qb = QueryBuilder::for($this->db, 'medicines')
            ->select(['id', 'name'])
            ->where('category', 'Tablet')
            ->where('price', '>', 50)
            ->orderBy('name', 'DESC')
            ->limit(10)
            ->offset(5);

        $sql = $qb->toSql();
        $this->assertSame('SELECT id, name FROM medicines WHERE category = ? AND price > ? ORDER BY name DESC LIMIT 10 OFFSET 5', $sql);
        $this->assertSame(['Tablet', 50], $qb->getBindings());
    }

    public function testCrudOperations(): void
    {
        $qb = QueryBuilder::for($this->db, 'medicines');

        // Insert
        $id1 = $qb->insert(['name' => 'Dolo 650', 'category' => 'Tablet', 'price' => 30.50]);
        $this->assertEquals(1, $id1);

        $id2 = $qb->insert(['name' => 'Benadryl', 'category' => 'Syrup', 'price' => 110.00]);
        $this->assertEquals(2, $id2);

        // Count
        $count = QueryBuilder::for($this->db, 'medicines')->count();
        $this->assertSame(2, $count);

        // First
        $first = QueryBuilder::for($this->db, 'medicines')->where('name', 'Dolo 650')->first();
        $this->assertNotNull($first);
        $this->assertSame('Tablet', $first['category']);

        // Update
        $updated = QueryBuilder::for($this->db, 'medicines')->where('id', 1)->update(['price' => 35.00]);
        $this->assertSame(1, $updated);

        // Delete
        $deleted = QueryBuilder::for($this->db, 'medicines')->where('id', 2)->delete();
        $this->assertSame(1, $deleted);
        $this->assertSame(1, QueryBuilder::for($this->db, 'medicines')->count());
    }

    public function testInvalidIdentifierThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        QueryBuilder::for($this->db, 'medicines; DROP TABLE medicines;--');
    }
}

