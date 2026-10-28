<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\Commerce\TaxCalculator;
use GSpot\UserPlugins\Commerce\TaxRegionRegistry;
use PHPUnit\Framework\TestCase;

final class TaxCalculatorTest extends TestCase
{
    public function testRegionRateAndTax(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/TaxCalculator.php';
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/TaxRegionRegistry.php';

        $regions = new TaxRegionRegistry(dirname(__DIR__) . '/user/plugins/commerce/data/tax/regions.yaml');
        $calc = new TaxCalculator();

        $this->assertSame(10.0, $regions->rateFor('AU'));
        $this->assertSame(1000, $calc->taxCents(10000, $regions->rateFor('AU')));
    }
}
