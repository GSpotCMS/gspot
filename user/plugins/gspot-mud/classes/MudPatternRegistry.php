<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\GspotMud;

use GSpot\Security\PathGuard;
use Symfony\Component\Yaml\Yaml;

class MudPatternRegistry
{
    public function __construct(private readonly string $patternsDir) {}

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        if (!is_dir($this->patternsDir)) {
            return [];
        }

        $out = [];
        foreach (glob($this->patternsDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $meta = $this->readMeta($dir);
            if ($meta !== null) {
                $out[] = $meta;
            }
        }

        usort($out, fn($a, $b) => strcmp($a['category'] ?? '', $b['category'] ?? '') ?: strcmp($a['title'] ?? '', $b['title'] ?? ''));

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function get(string $slug): ?array
    {
        $slug = PathGuard::sanitizeSlug($slug);
        if ($slug === '') {
            return null;
        }
        $dir = PathGuard::resolveUnder($this->patternsDir, $slug);
        if ($dir === null || !is_dir($dir)) {
            return null;
        }

        $meta = $this->readMeta($dir);
        if ($meta === null) {
            return null;
        }

        $meta['html'] = $this->readHtml($dir);

        return $meta;
    }

    /** @param array<string, mixed> $meta */
    public function save(string $slug, array $meta, string $html): void
    {
        $slug = PathGuard::sanitizeSlug($slug);
        if ($slug === '') {
            throw new \InvalidArgumentException('Invalid pattern slug');
        }

        $dir = PathGuard::resolveUnder($this->patternsDir, $slug);
        if ($dir === null) {
            throw new \InvalidArgumentException('Invalid pattern path');
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pattern = [
            'slug' => $slug,
            'title' => (string) ($meta['title'] ?? $slug),
            'category' => (string) ($meta['category'] ?? 'general'),
            'description' => (string) ($meta['description'] ?? ''),
            'updated' => date('c'),
        ];

        file_put_contents($dir . '/pattern.yaml', Yaml::dump($pattern, 2));
        file_put_contents($dir . '/body.html', $html);
    }

    public function delete(string $slug): bool
    {
        $slug = PathGuard::sanitizeSlug($slug);
        if ($slug === '') {
            return false;
        }
        $dir = PathGuard::resolveUnder($this->patternsDir, $slug);
        if ($dir === null || !is_dir($dir)) {
            return false;
        }

        $this->deleteDir($dir);

        return true;
    }

    /** @return array<string, string> */
    public function categories(): array
    {
        $cats = [];
        foreach ($this->all() as $p) {
            $cat = $p['category'] ?? 'general';
            $cats[$cat] = ucwords(str_replace('-', ' ', $cat));
        }
        ksort($cats);

        return $cats;
    }

    /** @return array<string, mixed>|null */
    private function readMeta(string $dir): ?array
    {
        $yamlFile = $dir . '/pattern.yaml';
        if (!is_file($yamlFile)) {
            return null;
        }

        $meta = Yaml::parseFile($yamlFile);
        if (!is_array($meta)) {
            return null;
        }

        $meta['slug'] = $meta['slug'] ?? basename($dir);
        $meta['dir'] = $dir;

        return $meta;
    }

    private function readHtml(string $dir): string
    {
        $file = $dir . '/body.html';
        if (!is_file($file)) {
            return '';
        }

        return (string) file_get_contents($file);
    }

    private function deleteDir(string $dir): void
    {
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
