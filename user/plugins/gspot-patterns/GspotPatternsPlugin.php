<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\GspotPatterns;

use GSpot\Plugin\AbstractPlugin;
use GSpot\Router\Page;

class GspotPatternsPlugin extends AbstractPlugin
{
    private ?LayeredPatternRegistry $layeredRegistry = null;
    private ?PatternRenderer $renderer = null;

    public function onAppBoot(mixed &$app): void
    {
        require_once __DIR__ . '/classes/PatternRegistry.php';
        require_once __DIR__ . '/classes/LayeredPatternRegistry.php';
        require_once __DIR__ . '/classes/PatternRenderer.php';

        $cssFile = __DIR__ . '/assets/css/gspot-patterns-fences.css';
        $ver = is_file($cssFile) ? filemtime($cssFile) : time();
        $app->renderer()->share('gspotPatternsCss', '/plugin-assets/gspot-patterns/css/gspot-patterns-fences.css?v=' . $ver);
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

    public function layeredRegistry(): LayeredPatternRegistry
    {
        return $this->layeredRegistry ??= LayeredPatternRegistry::forApp($this->app, __DIR__);
    }

    public function registry(): LayeredPatternRegistry
    {
        return $this->layeredRegistry();
    }

    public function renderer(): PatternRenderer
    {
        return $this->renderer ??= new PatternRenderer($this->layeredRegistry());
    }
}
