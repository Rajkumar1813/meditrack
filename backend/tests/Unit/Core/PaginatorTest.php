<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Paginator;

class PaginatorTest extends TestCase
{
    public function testOffsetPaginationMathAndClamping(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        // Request page 2, per_page 20, total 45
        $result = Paginator::offset(2, 20, 45, $items);

        $meta = $result['meta'];
        $this->assertSame('offset', $meta['type']);
        $this->assertSame(2, $meta['page']);
        $this->assertSame(20, $meta['per_page']);
        $this->assertSame(45, $meta['total']);
        $this->assertSame(3, $meta['last_page']);
        $this->assertSame(21, $meta['from']);
        $this->assertSame(40, $meta['to']);

        // Check clamping > 100
        $clamped = Paginator::offset(1, 500, 1000, []);
        $this->assertSame(Paginator::MAX_OFFSET_PER_PAGE, $clamped['meta']['per_page']);
    }

    public function testCursorPaginationAndEncoding(): void
    {
        $cursorPayload = ['id' => 120, 'created_at' => '2026-11-01 00:00:00'];
        $encoded = Paginator::encodeCursor($cursorPayload);

        $this->assertIsString($encoded);
        $decoded = Paginator::decodeCursor($encoded);
        $this->assertSame($cursorPayload, $decoded);

        $result = Paginator::cursor([['id' => 121]], 20, $encoded, true);
        $this->assertSame('cursor', $result['meta']['type']);
        $this->assertSame(20, $result['meta']['limit']);
        $this->assertSame($encoded, $result['meta']['next_cursor']);
        $this->assertTrue($result['meta']['has_more']);

        // Check clamping > 50
        $clamped = Paginator::cursor([], 100, null, false);
        $this->assertSame(Paginator::MAX_CURSOR_LIMIT, $clamped['meta']['limit']);
    }
}

