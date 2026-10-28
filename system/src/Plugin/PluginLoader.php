<?php

declare(strict_types=1);

namespace GSpot\Plugin;

use GSpot\Application;
use Symfony\Component\Yaml\Yaml;

class PluginLoader
{
    private string $pluginsPath;
    private Application $app;
    private array $plugins = [];

    public function __construct(string $pluginsPath, Application $app)
    {
        $this->pluginsPath = $pluginsPath;
        $this->app = $app;
    }

    public function loadAll(): void
    {
        if (!is_dir($this->pluginsPath)) {
            return;
        }

        $dirs = glob($this->pluginsPath . '/*', GLOB_ONLYDIR);
        if ($dirs === false) {
            return;
        }

        foreach ($dirs as $dir) {
            $this->loadPlugin($dir);
        }
    }

    public function loadPlugin(string $path): void
    {
        $manifest = $path . '/plugin.yaml';
        if (!file_exists($manifest)) {
            return;
        }

        $config = Yaml::parseFile($manifest);
        if (!is_array($config)) {
            return;
        }

        $name = $config['name'] ?? basename($path);
        $enabled = $config['enabled'] ?? true;

        if (!$enabled) {
            return;
        }

        $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
        $pluginFile = $path . '/' . $className . 'Plugin.php';

        if (file_exists($pluginFile)) {
            require_once $pluginFile;

            $fqcn = "GSpot\\UserPlugins\\{$className}\\{$className}Plugin";
            if (class_exists($fqcn)) {
                $instance = new $fqcn($this->app, $config);
                $this->plugins[$name] = $instance;

                $events = $config['events'] ?? [];
                foreach ($events as $event) {
                    if (method_exists($instance, $event)) {
                        $priority = $config['priority'] ?? 0;
                        $this->app->events()->listen(
                            $event,
                            [$instance, $event],
                            $priority
                        );
                    }
                }
            }
        }
    }

    public function fireEvent(string $event, mixed &$data = null): void
    {
        $this->app->events()->dispatch($event, $data);
    }

    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function isLoaded(string $name): bool
    {
        return isset($this->plugins[$name]);
    }
}
