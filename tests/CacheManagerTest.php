<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\Cache\CacheManager;
use PHPUnit\Framework\TestCase;

final class CacheManagerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gspot-cache-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            (new CacheManager($this->dir))->clear();
            @rmdir($this->dir);
        }
    }

    public function testSetGetAndExpiry(): void
    {
        $cache = new CacheManager($this->dir);
        $cache->set('page:test', '<html>ok</html>', 3600);
        $this->assertSame('<html>ok</html>', $cache->get('page:test'));

        $cache->set('page:ttl0', 'gone', 0);
        $this->assertNull($cache->get('page:ttl0'));
    }
}
