<?php

declare(strict_types=1);

namespace GSpot;

use GSpot\Config\ConfigManager;
use GSpot\Security\PathGuard;
use GSpot\Renderer\BladeRenderer;
use GSpot\Renderer\ThemeManager;
use GSpot\Router\Router;
use GSpot\Plugin\PluginLoader;
use GSpot\Plugin\EventDispatcher;
use GSpot\Cache\CacheManager;
use Illuminate\Container\Container;

class Application
{
    public const VERSION = '0.9.0';

    private static ?self $instance = null;

    private Container $container;
    private ConfigManager $config;
    private Router $router;
    private BladeRenderer $renderer;
    private ThemeManager $themes;
    private PluginLoader $plugins;
    private EventDispatcher $events;
    private CacheManager $cache;
    private string $rootPath;
    private bool $booted = false;

    private function __construct()
    {
        $this->rootPath = defined('GSPOT_ROOT') ? GSPOT_ROOT : dirname(__DIR__, 2);
        $this->container = new Container();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->config = new ConfigManager(
            $this->rootPath . '/system/config',
            $this->rootPath . '/user/config'
        );

        $this->cache = new CacheManager(
            $this->rootPath . '/user/.cache'
        );

        $this->events = new EventDispatcher();

        $this->themes = new ThemeManager(
            $this->rootPath . '/user/themes',
            $this->config->get('system.theme', 'starter')
        );

        $this->renderer = new BladeRenderer(
            $this->getViewPaths(),
            $this->rootPath . '/user/.cache/views'
        );

        $this->renderer->share('theme', $this->themes);

        $this->router = new Router(
            $this->rootPath . '/user/content',
            $this->config
        );

        $this->plugins = new PluginLoader(
            $this->rootPath . '/user/plugins',
            $this
        );
        $this->plugins->loadAll();

        $this->events->dispatch('onAppBoot', $this);

        $this->booted = true;
    }

    public function handleRequest(string $uri, string $method = 'GET'): string
    {
        $route = $this->router->resolve($uri);

        if ($route === null) {
            $this->events->dispatch('onPageNotFound', $uri);
            return $this->renderer->render('errors.404', [
                'uri' => $uri,
                'site' => $this->siteData(),
            ]);
        }

        $this->events->dispatch('onPageRoute', $route);

        $page = $route->getPage();

        $cacheKey = null;
        if ($method === 'GET' && $this->pageCacheEnabled()) {
            $cacheKey = $this->pageCacheKey($uri, $page);
            $cached = $this->cache->get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $this->events->dispatch('onPageRender', $page);

        $html = $this->renderer->render($page->getTemplate(), [
            'page' => $page,
            'site' => $this->siteData(),
            'config' => $this->config,
        ]);

        if ($cacheKey !== null) {
            $ttl = (int) $this->config->get('system.cache.ttl', 3600);
            $this->cache->set($cacheKey, $html, $ttl);
        }

        return $html;
    }

    private function pageCacheEnabled(): bool
    {
        return (bool) $this->config->get('system.cache.enabled', false);
    }

    private function pageCacheKey(string $uri, \GSpot\Router\Page $page): string
    {
        $mtime = 0;
        $path = $page->filePath();
        if ($path !== '' && is_file($path)) {
            $mtime = (int) filemtime($path);
        }

        $theme = (string) $this->config->get('system.theme', 'starter');

        return 'page:' . hash('sha256', $uri . '|' . $theme . '|' . $mtime);
    }

    public function config(): ConfigManager
    {
        return $this->config;
    }

    public function renderer(): BladeRenderer
    {
        return $this->renderer;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function plugins(): PluginLoader
    {
        return $this->plugins;
    }

    public function events(): EventDispatcher
    {
        return $this->events;
    }

    public function themes(): ThemeManager
    {
        return $this->themes;
    }

    public function cache(): CacheManager
    {
        return $this->cache;
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function scaffoldPlugin(string $name): void
    {
        $name = PathGuard::sanitizeName($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Invalid plugin name');
        }
        $dir = PathGuard::resolveUnder($this->rootPath . '/user/plugins', $name)
            ?? throw new \InvalidArgumentException('Invalid plugin path');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            mkdir($dir . '/views', 0755, true);
        }

        $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));

        file_put_contents($dir . '/plugin.yaml', "name: {$name}\nversion: 1.0.0\ndescription: A GSpot plugin\nauthor: \nevents:\n  - onPageRender\n");
        file_put_contents($dir . '/' . $className . 'Plugin.php', "<?php\n\nnamespace GSpot\\UserPlugins\\{$className};\n\nuse GSpot\\Plugin\\AbstractPlugin;\n\nclass {$className}Plugin extends AbstractPlugin\n{\n    public function onPageRender(array &\$data): void\n    {\n        // Modify page data before rendering\n    }\n}\n");
    }

    public function scaffoldTheme(string $name): void
    {
        $name = PathGuard::sanitizeName($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Invalid theme name');
        }
        $dir = PathGuard::resolveUnder($this->rootPath . '/user/themes', $name)
            ?? throw new \InvalidArgumentException('Invalid theme path');
        if (!is_dir($dir)) {
            mkdir($dir . '/views/layouts', 0755, true);
            mkdir($dir . '/views/partials', 0755, true);
            mkdir($dir . '/assets/css', 0755, true);
            mkdir($dir . '/assets/js', 0755, true);
        }

        file_put_contents($dir . '/theme.yaml', "name: {$name}\nversion: 1.0.0\ndescription: A GSpot theme\nauthor: \n\npresets:\n  default:\n    primary: '#6b21a8'\n    secondary: '#f43f5e'\n    background: '#ffffff'\n    text: '#1e293b'\n");
        file_put_contents($dir . '/views/layouts/default.blade.php', "@extends('layouts.base')\n\n@section('content')\n  {!! \$page->content() !!}\n@endsection\n");
    }

    public function installKit(string $name): void
    {
        $kits = new \GSpot\Renderer\StarterKitManager($this->rootPath);
        if (!$kits->install($name)) {
            echo "Kit '{$name}' not found.\n";
        }
    }

    private function getViewPaths(): array
    {
        $theme = $this->config->get('system.theme', 'starter');
        return [
            $this->rootPath . '/user/themes/' . $theme . '/views',
            $this->rootPath . '/system/views',
        ];
    }

    private function siteData(): array
    {
        return [
            'title' => $this->config->get('site.title', 'GSpot CMS'),
            'description' => $this->config->get('site.description', ''),
            'url' => $this->config->get('site.url', 'http://localhost:8080'),
            'version' => self::VERSION,
        ];
    }
}
