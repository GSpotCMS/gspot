<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\Commerce\CouponRegistry;
use PHPUnit\Framework\TestCase;

final class CouponRegistryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/CouponRegistry.php';
        $this->dir = sys_get_temp_dir() . '/gspot-coupons-' . uniqid('', true);
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

    public function testSaveResolveAndIncrement(): void
    {
        $registry = new CouponRegistry($this->dir);
        $registry->save('save10', [
            'type' => 'percent',
            'amount' => 10,
            'active' => true,
            'max_uses' => 2,
            'uses' => 0,
            'expires' => '',
        ]);

        $resolved = $registry->resolveForCheckout('save10');
        $this->assertNotNull($resolved);
        $this->assertSame('SAVE10', $resolved['code']);

        $registry->incrementUses('SAVE10');
        $registry->incrementUses('SAVE10');
        $this->assertNull($registry->resolveForCheckout('SAVE10'));
    }
}
