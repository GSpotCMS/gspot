<?php

declare(strict_types=1);

namespace GSpot\Renderer;

use Symfony\Component\Yaml\Yaml;

class ThemeManager
{
    private string $themesPath;
    private string $activeTheme;
    private array $manifest = [];
    private array $vibeTokens = [];

    public function __construct(string $themesPath, string $activeTheme)
    {
        $this->themesPath = $themesPath;
        $this->activeTheme = $activeTheme;
        $this->loadManifest();
    }

    public function viewPath(): string
    {
        return $this->themesPath . '/' . $this->activeTheme . '/views';
    }

    public function assetPath(): string
    {
        return $this->themesPath . '/' . $this->activeTheme . '/assets';
    }

    /** Public URL for a theme asset with cache-busting filemtime query. */
    public function assetUrl(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        $file = $this->assetPath() . '/' . $relativePath;
        $version = is_file($file) ? (string) filemtime($file) : (string) time();

        return '/assets/' . $relativePath . '?v=' . $version;
    }

    public function manifest(): array
    {
        return $this->manifest;
    }

    public function activeTheme(): string
    {
        return $this->activeTheme;
    }

    public function setTheme(string $name): void
    {
        $this->activeTheme = $name;
        $this->loadManifest();
    }

    public function applyVibe(string $preset): void
    {
        $presets = $this->manifest['presets'] ?? [];
        $vibes = $this->manifest['vibes'] ?? [];

        if (isset($vibes[$preset])) {
            $base = $vibes[$preset]['extends'] ?? 'default';
            $this->vibeTokens = array_merge(
                $presets[$base] ?? [],
                $vibes[$preset]
            );
            unset($this->vibeTokens['extends']);
        } elseif (isset($presets[$preset])) {
            $this->vibeTokens = $presets[$preset];
        }
    }

    public function vibeTokens(): array
    {
        return $this->vibeTokens;
    }

    /** Map theme.yaml keys to CSS custom property names used in theme CSS. */
    private const CSS_VAR_ALIASES = [
        'background' => 'bg',
    ];

    public function cssVariables(): string
    {
        if (empty($this->vibeTokens)) {
            return '';
        }

        $vars = [];
        foreach ($this->vibeTokens as $key => $value) {
            $cssKey = self::CSS_VAR_ALIASES[$key] ?? $key;
            $vars[] = "  --{$cssKey}: {$value};";
        }

        return ":root {\n" . implode("\n", $vars) . "\n}\n";
    }

    public function listThemes(): array
    {
        $themes = [];
        $dirs = glob($this->themesPath . '/*', GLOB_ONLYDIR);

        if ($dirs === false) {
            return $themes;
        }

        foreach ($dirs as $dir) {
            $manifest = $dir . '/theme.yaml';
            if (file_exists($manifest)) {
                $config = Yaml::parseFile($manifest);
                $themes[] = [
                    'name' => basename($dir),
                    'title' => $config['name'] ?? basename($dir),
                    'version' => $config['version'] ?? '0.0.0',
                    'description' => $config['description'] ?? '',
                    'presets' => array_keys($config['presets'] ?? []),
                    'vibes' => array_keys($config['vibes'] ?? []),
                ];
            }
        }

        return $themes;
    }

    public function assetManifest(): array
    {
        $manifestFile = $this->assetPath() . '/manifest.json';
        if (file_exists($manifestFile)) {
            return json_decode(file_get_contents($manifestFile), true) ?? [];
        }
        return [];
    }

    public function asset(string $path): string
    {
        $manifest = $this->assetManifest();
        return '/assets/' . ($manifest[$path] ?? $path);
    }

    private function loadManifest(): void
    {
        $file = $this->themesPath . '/' . $this->activeTheme . '/theme.yaml';
        if (file_exists($file)) {
            $this->manifest = Yaml::parseFile($file) ?? [];
        }

        $defaultPreset = $this->manifest['presets']['default'] ?? [];
        $this->vibeTokens = $defaultPreset;
    }
}
