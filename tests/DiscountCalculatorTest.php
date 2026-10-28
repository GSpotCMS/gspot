<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\Commerce\DiscountCalculator;
use PHPUnit\Framework\TestCase;

final class DiscountCalculatorTest extends TestCase
{
    public function testPercentAndFixedDiscount(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/DiscountCalculator.php';
        $calc = new DiscountCalculator();

        $this->assertSame(1000, $calc->discountCents(10000, ['type' => 'percent', 'amount' => 10]));
        $this->assertSame(500, $calc->discountCents(10000, ['type' => 'fixed', 'amount' => 500]));
    }

    public function testApplyToLinesReducesTotal(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/DiscountCalculator.php';
        $calc = new DiscountCalculator();
        $lines = [
            [
                'product' => ['slug' => 'a', 'price_cents' => 5000, 'title' => 'A'],
                'qty' => 1,
                'line_cents' => 5000,
            ],
            [
                'product' => ['slug' => 'b', 'price_cents' => 5000, 'title' => 'B'],
                'qty' => 1,
                'line_cents' => 5000,
            ],
        ];

        $adjusted = $calc->applyToLines($lines, 2000);
        $total = 0;
        foreach ($adjusted as $line) {
            $total += $line['line_cents'];
        }

        $this->assertSame(8000, $total);
    }
}
