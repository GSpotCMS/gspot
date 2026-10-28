<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\Commerce\DownloadGrantRegistry;
use PHPUnit\Framework\TestCase;

final class DownloadGrantRegistryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/DownloadGrantRegistry.php';
        $this->dir = sys_get_temp_dir() . '/gspot-grants-' . uniqid('', true);
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.yaml') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testCreateAndRedeem(): void
    {
        $registry = new DownloadGrantRegistry($this->dir);
        $token = $registry->create('cs_test_1', 'gspot-support', 2, 7);
        $this->assertSame(32, strlen($token));

        $first = $registry->redeem($token);
        $this->assertNotNull($first);
        $this->assertSame('gspot-support', $first['slug']);

        $second = $registry->redeem($token);
        $this->assertNotNull($second);

        $third = $registry->redeem($token);
        $this->assertNull($third);
    }
}
