<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Cache;

class CacheTest extends TestCase
{
    public function testCacheFallbackGracefulWhenRedisOffline(): void
    {
        $cache = new Cache(null);

        $this->assertNull($cache->get('missing_key'));
        $this->assertSame('default', $cache->get('missing_key', 'default'));
        $this->assertFalse($cache->put('test', 'value'));

        $computed = $cache->remember('key', 60, function () {
            return 'computed_val';
        });
        $this->assertSame('computed_val', $computed);
    }
}

