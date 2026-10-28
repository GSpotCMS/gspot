<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\Config\ConfigManager;
use GSpot\Router\Router;
use PHPUnit\Framework\TestCase;

final class MarkdownGfmTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $root = dirname(__DIR__);
        $this->router = new Router(
            $root . '/user/content',
            new ConfigManager($root . '/system/config', $root . '/user/config'),
        );
    }

    public function testPipeTablesRenderAsHtmlTable(): void
    {
        $tmp = sys_get_temp_dir() . '/gspot-md-table-' . uniqid('', true);
        mkdir($tmp . '/99.table-test', 0755, true);
        file_put_contents($tmp . '/99.table-test/page.md', <<<'MD'
---
title: Table test
---

| A | B |
|---|---|
| 1 | 2 |
MD);

        $router = new Router($tmp, new ConfigManager(
            dirname(__DIR__) . '/system/config',
            dirname(__DIR__) . '/user/config',
        ));

        $pages = $router->listPages();
        $this->assertCount(1, $pages);
        $html = $pages[0]->content();
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>', $html);
        $this->assertStringContainsString('<td>1</td>', $html);

        $this->deleteDir($tmp);
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
