<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\Commerce\AbandonedCartRegistry;
use PHPUnit\Framework\TestCase;

final class AbandonedCartRegistryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/AbandonedCartRegistry.php';
        $this->dir = sys_get_temp_dir() . '/gspot-abandoned-' . uniqid('', true);
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

    public function testUpsertAndDelete(): void
    {
        $registry = new AbandonedCartRegistry($this->dir);
        $registry->upsert('buyer@example.com', [['slug' => 'mug', 'qty' => 1]]);
        $this->assertCount(1, glob($this->dir . '/*.yaml') ?: []);

        $registry->deleteByEmail('buyer@example.com');
        $this->assertSame([], glob($this->dir . '/*.yaml') ?: []);
    }
}
