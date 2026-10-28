<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\GspotMud;

use GSpot\Plugin\AbstractPlugin;
use GSpot\Router\Page;

class GspotMudPlugin extends AbstractPlugin
{
    private ?MudPatternRegistry $registry = null;
    private ?MudPatternRenderer $renderer = null;

    public function onAppBoot(mixed &$app): void
    {
        require_once __DIR__ . '/classes/MudPatternRegistry.php';
        require_once __DIR__ . '/classes/MudPatternRenderer.php';

        $cssFile = __DIR__ . '/assets/css/gspot-mud-fences.css';
        $ver = is_file($cssFile) ? filemtime($cssFile) : time();
        $app->renderer()->share('gspotMudCss', '/plugin-assets/gspot-mud/css/gspot-mud-fences.css?v=' . $ver);
    }

    public function onPageRender(Page &$page): void
    {
        if (empty($this->config['config']['expand_shortcodes'])) {
            return;
        }

        $content = $page->content();
        $expanded = $this->renderer()->expand($content);

        if ($expanded !== $content) {
            $page->setContent($expanded);
        }
    }

    public function registry(): MudPatternRegistry
    {
        return $this->registry ??= new MudPatternRegistry($this->patternsDir());
    }

    public function renderer(): MudPatternRenderer
    {
        return $this->renderer ??= new MudPatternRenderer($this->registry());
    }

    public function patternsDir(): string
    {
        $rel = $this->config['config']['patterns_path'] ?? 'data/patterns';

        return __DIR__ . '/' . ltrim($rel, '/');
    }
}
