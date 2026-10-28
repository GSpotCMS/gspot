<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\Commerce\OrderRegistry;
use PHPUnit\Framework\TestCase;

final class OrderRegistryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/OrderRegistry.php';
        $this->dir = sys_get_temp_dir() . '/gspot-orders-' . uniqid('', true);
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

    public function testSaveAndListOrders(): void
    {
        $registry = new OrderRegistry($this->dir);
        $registry->save('cs_test_abc', [
            'status' => 'paid',
            'email' => 'buyer@example.com',
            'amount_total' => 2500,
            'currency' => 'aud',
            'payment_status' => 'paid',
            'created' => date('c'),
        ]);

        $orders = $registry->all(10);
        $this->assertCount(1, $orders);
        $this->assertSame('cs_test_abc', $orders[0]['id']);
        $this->assertSame(2500, $orders[0]['amount_total']);
        $this->assertSame(1, $registry->count());
    }
}
