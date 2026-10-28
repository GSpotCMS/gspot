<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\GspotPatterns\LayeredPatternRegistry;
use GSpot\UserPlugins\GspotPatterns\PatternRegistry;
use PHPUnit\Framework\TestCase;

final class LayeredPatternRegistryTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/gspot-patterns/classes/PatternRegistry.php';
        require_once dirname(__DIR__) . '/user/plugins/gspot-patterns/classes/LayeredPatternRegistry.php';

        $this->tmp = sys_get_temp_dir() . '/gspot-layered-' . uniqid('', true);
        mkdir($this->tmp . '/core/demo', 0755, true);
        mkdir($this->tmp . '/theme/demo', 0755, true);
        mkdir($this->tmp . '/site', 0755, true);

        file_put_contents($this->tmp . '/core/demo/pattern.yaml', "slug: demo\ntitle: Core\n");
        file_put_contents($this->tmp . '/core/demo/body.html', '<p>core</p>');
        file_put_contents($this->tmp . '/theme/demo/pattern.yaml', "slug: demo\ntitle: Theme\n");
        file_put_contents($this->tmp . '/theme/demo/body.html', '<p>theme</p>');
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmp);
    }

    public function testSiteOverridesThemeAndCore(): void
    {
        $registry = new LayeredPatternRegistry(
            new PatternRegistry($this->tmp . '/core'),
            new PatternRegistry($this->tmp . '/theme'),
            new PatternRegistry($this->tmp . '/site'),
        );

        $this->assertSame('theme', $registry->get('demo')['source']);
        $this->assertStringContainsString('theme', (string) $registry->get('demo')['html']);

        $registry->save('demo', ['title' => 'Site', 'category' => 'general'], '<p>site</p>');
        $this->assertSame('site', $registry->get('demo')['source']);

        $this->assertTrue($registry->delete('demo'));
        $this->assertSame('theme', $registry->get('demo')['source']);

        $this->assertFalse($registry->delete('demo'));
        $this->assertSame('theme', $registry->get('demo')['source']);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
