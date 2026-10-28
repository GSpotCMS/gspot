<?php

declare(strict_types=1);

namespace GSpot\Renderer;

use Symfony\Component\Yaml\Yaml;

class StarterKitManager
{
    private string $rootPath;
    private string $kitsPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        $this->kitsPath = $rootPath . '/system/kits';
    }

    public function listKits(): array
    {
        $kits = [];

        if (!is_dir($this->kitsPath)) {
            return $kits;
        }

        $dirs = glob($this->kitsPath . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $manifest = $dir . '/kit.yaml';
            if (file_exists($manifest)) {
                $config = Yaml::parseFile($manifest);
                $kits[] = [
                    'name' => basename($dir),
                    'title' => $config['title'] ?? basename($dir),
                    'description' => $config['description'] ?? '',
                    'theme' => $config['theme'] ?? 'starter',
                    'pages' => $config['pages'] ?? [],
                ];
            }
        }

        return $kits;
    }

    public function install(string $kitName): bool
    {
        $kitDir = $this->kitsPath . '/' . $kitName;
        $manifest = $kitDir . '/kit.yaml';

        if (!file_exists($manifest)) {
            return false;
        }

        $config = Yaml::parseFile($manifest);

        // Install content pages
        $contentSource = $kitDir . '/content';
        if (is_dir($contentSource)) {
            $this->copyDir($contentSource, $this->rootPath . '/user/content');
        }

        // Install theme if bundled
        $themeSource = $kitDir . '/theme';
        if (is_dir($themeSource)) {
            $themeName = $config['theme'] ?? $kitName;
            $this->copyDir($themeSource, $this->rootPath . '/user/themes/' . $themeName);
        }

        // Install media
        $mediaSource = $kitDir . '/media';
        if (is_dir($mediaSource)) {
            $this->copyDir($mediaSource, $this->rootPath . '/user/media');
        }

        // Set theme if specified
        if (isset($config['theme'])) {
            $sysConfig = $this->rootPath . '/user/config/system.yaml';
            $existing = file_exists($sysConfig) ? Yaml::parseFile($sysConfig) : [];
            $existing['theme'] = $config['theme'];
            file_put_contents($sysConfig, Yaml::dump($existing, 4, 2));
        }

        return true;
    }

    public function createKit(string $name, array $options = []): string
    {
        $kitDir = $this->kitsPath . '/' . $name;
        if (!is_dir($kitDir)) {
            mkdir($kitDir, 0755, true);
            mkdir($kitDir . '/content', 0755, true);
            mkdir($kitDir . '/media', 0755, true);
        }

        $manifest = [
            'title' => $options['title'] ?? ucfirst($name),
            'description' => $options['description'] ?? 'A GSpot CMS starter kit',
            'theme' => $options['theme'] ?? 'starter',
            'version' => '1.0.0',
            'pages' => $options['pages'] ?? ['home', 'about', 'contact'],
        ];

        file_put_contents($kitDir . '/kit.yaml', Yaml::dump($manifest, 4, 2));

        // Create blueprint pages
        foreach ($manifest['pages'] as $i => $page) {
            $order = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
            $pageDir = $kitDir . '/content/' . $order . '.' . $page;
            mkdir($pageDir, 0755, true);
            file_put_contents($pageDir . '/page.md', "---\ntitle: " . ucfirst($page) . "\ntemplate: default\n---\n\n# " . ucfirst($page) . "\n\nContent here.\n");
        }

        return $kitDir;
    }

    private function copyDir(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $items = scandir($source);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $srcPath = $source . '/' . $item;
            $dstPath = $dest . '/' . $item;

            if (is_dir($srcPath)) {
                $this->copyDir($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }
}
