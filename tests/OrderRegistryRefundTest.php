<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\Commerce\OrderRegistry;
use PHPUnit\Framework\TestCase;

final class OrderRegistryRefundTest extends TestCase
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

    public function testFindByPaymentIntentAndMarkRefunded(): void
    {
        $registry = new OrderRegistry($this->dir);
        $registry->save('cs_test_99', [
            'status' => 'paid',
            'amount_total' => 5000,
            'stripe_payment_intent' => 'pi_test_abc',
        ]);

        $found = $registry->findByPaymentIntent('pi_test_abc');
        $this->assertNotNull($found);
        $registry->markRefunded('cs_test_99', 5000, 'full');
        $order = $registry->get('cs_test_99');
        $this->assertSame('refunded', $order['status'] ?? '');
    }
}
