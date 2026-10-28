<?php

declare(strict_types=1);

namespace GSpot\Config;

use Symfony\Component\Yaml\Yaml;

class ConfigManager
{
    private array $config = [];
    private string $systemPath;
    private string $userPath;

    public function __construct(string $systemPath, string $userPath)
    {
        $this->systemPath = $systemPath;
        $this->userPath = $userPath;
        $this->load();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach (array_slice($keys, 0, -1) as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config[end($keys)] = $value;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function save(string $file, array $data): void
    {
        $path = $this->userPath . '/' . $file . '.yaml';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, Yaml::dump($data, 4, 2));
    }

    private function load(): void
    {
        $this->config = $this->loadDirectory($this->systemPath);

        $userConfig = $this->loadDirectory($this->userPath);

        $this->config = $this->mergeDeep($this->config, $userConfig);
    }

    private function loadDirectory(string $path): array
    {
        $config = [];

        if (!is_dir($path)) {
            return $config;
        }

        $files = glob($path . '/*.yaml');
        foreach ($files as $file) {
            $key = basename($file, '.yaml');
            $parsed = Yaml::parseFile($file);
            if (is_array($parsed)) {
                $config[$key] = $parsed;
            }
        }

        return $config;
    }

    private function mergeDeep(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $base[$key] = $this->mergeDeep($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
