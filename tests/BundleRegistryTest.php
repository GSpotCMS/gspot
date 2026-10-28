<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\Commerce\BundleRegistry;
use GSpot\UserPlugins\Commerce\ProductRegistry;
use PHPUnit\Framework\TestCase;

final class BundleRegistryTest extends TestCase
{
    public function testLineItemsFromDemoBundle(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/ProductRegistry.php';
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/BundleRegistry.php';

        $products = new ProductRegistry(dirname(__DIR__) . '/user/plugins/commerce/data/products');
        $bundles = new BundleRegistry(dirname(__DIR__) . '/user/plugins/commerce/data/bundles', $products);
        $items = $bundles->lineItems('spot-starter');

        $this->assertNotSame([], $items);
        $slugs = array_column($items, 'slug');
        $this->assertContains('team-dc-mug', $slugs);
    }
}
