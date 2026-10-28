<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\Commerce\GiftCardRegistry;
use PHPUnit\Framework\TestCase;

final class GiftCardRegistryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/GiftCardRegistry.php';
        $this->dir = sys_get_temp_dir() . '/gspot-gift-' . uniqid('', true);
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

    public function testRedeemBalance(): void
    {
        $registry = new GiftCardRegistry($this->dir);
        $registry->save('TESTCARD', [
            'balance_cents' => 1000,
            'currency' => 'aud',
            'active' => true,
        ]);

        $this->assertTrue($registry->redeem('TESTCARD', 400));
        $card = $registry->get('TESTCARD');
        $this->assertSame(600, (int) ($card['balance_cents'] ?? 0));
    }
}
