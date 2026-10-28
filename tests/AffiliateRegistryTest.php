<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\Commerce\AffiliateRegistry;
use PHPUnit\Framework\TestCase;

final class AffiliateRegistryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/AffiliateRegistry.php';
        $this->dir = sys_get_temp_dir() . '/gspot-aff-' . uniqid('', true);
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

    public function testCommissionAndRecordSale(): void
    {
        $registry = new AffiliateRegistry($this->dir);
        $registry->save('PARTNER', [
            'name' => 'Partner',
            'commission_percent' => 10,
            'active' => true,
        ]);

        $affiliate = $registry->resolveActive('PARTNER');
        $this->assertNotNull($affiliate);
        $commission = $registry->commissionCents(10000, $affiliate);
        $this->assertSame(1000, $commission);
        $registry->recordSale('PARTNER', 10000, $commission);
        $updated = $registry->get('PARTNER');
        $this->assertSame(1, (int) ($updated['orders'] ?? 0));
    }
}
