<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\Commerce\ProductRegistry;
use PHPUnit\Framework\TestCase;

final class ProductRegistryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce/classes/ProductRegistry.php';
        $this->dir = sys_get_temp_dir() . '/gspot-products-' . uniqid('', true);
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '/*') ?: [] as $path) {
                is_dir($path) ? $this->removeDir($path) : unlink($path);
            }
            rmdir($this->dir);
        }
    }

    public function testSaveGetDeleteProduct(): void
    {
        $registry = new ProductRegistry($this->dir);
        $registry->save('test-mug', [
            'title' => 'Test Mug',
            'description' => 'Hot',
            'price_cents' => 999,
            'currency' => 'aud',
            'active' => true,
        ]);

        $product = $registry->get('test-mug');
        $this->assertNotNull($product);
        $this->assertSame('Test Mug', $product['title']);
        $this->assertSame(999, $product['price_cents']);

        $this->assertTrue($registry->delete('test-mug'));
        $this->assertNull($registry->get('test-mug'));
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
