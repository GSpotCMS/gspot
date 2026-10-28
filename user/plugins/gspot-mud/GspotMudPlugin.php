<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\GspotMud;

use GSpot\Plugin\AbstractPlugin;
use GSpot\Router\Page;

class GspotMudPlugin extends AbstractPlugin
{
    private ?LayeredMudPatternRegistry $layeredRegistry = null;
    private ?MudPatternRenderer $renderer = null;

    public function onAppBoot(mixed &$app): void
    {
        require_once __DIR__ . '/classes/MudPatternRegistry.php';
        require_once __DIR__ . '/classes/LayeredMudPatternRegistry.php';
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

    public function layeredRegistry(): LayeredMudPatternRegistry
    {
        return $this->layeredRegistry ??= LayeredMudPatternRegistry::forApp($this->app, __DIR__);
    }

    /** @deprecated Use layeredRegistry(); kept for internal renderer wiring */
    public function registry(): LayeredMudPatternRegistry
    {
        return $this->layeredRegistry();
    }

    public function renderer(): MudPatternRenderer
    {
        return $this->renderer ??= new MudPatternRenderer($this->layeredRegistry());
    }
}
