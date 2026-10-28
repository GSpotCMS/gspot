<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\GspotPatterns;

use GSpot\Application;
use GSpot\Security\PathGuard;

/**
 * Core (plugin) → theme → site (user/pattern-overrides). Saves and deletes affect site layer only.
 */
final class LayeredPatternRegistry
{
    public function __construct(
        private readonly PatternRegistry $core,
        private readonly ?PatternRegistry $theme,
        private readonly PatternRegistry $site,
    ) {}

    public static function forApp(Application $app, string $pluginDir): self
    {
        $core = new PatternRegistry($pluginDir . '/data/patterns');
        $site = new PatternRegistry($app->rootPath() . '/user/pattern-overrides');

        $themeName = PathGuard::sanitizeName($app->themes()->activeTheme());
        $themeDir = $app->rootPath() . '/user/themes/' . $themeName . '/patterns';
        $theme = is_dir($themeDir) ? new PatternRegistry($themeDir) : null;

        return new self($core, $theme, $site);
    }

    /** @return array<string, mixed>|null */
    public function get(string $slug): ?array
    {
        foreach (array_reverse($this->layersForRead(), true) as $source => $registry) {
            $pattern = $registry->get($slug);
            if ($pattern !== null) {
                $pattern['source'] = $source;

                return $pattern;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        /** @var array<string, array<string, mixed>> $bySlug */
        $bySlug = [];

        foreach ($this->layersForRead() as $source => $registry) {
            foreach ($registry->all() as $meta) {
                $slug = (string) ($meta['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                $meta['source'] = $source;
                $bySlug[$slug] = $meta;
            }
        }

        $out = array_values($bySlug);
        usort(
            $out,
            fn ($a, $b) => strcmp($a['category'] ?? '', $b['category'] ?? '')
                ?: strcmp($a['title'] ?? '', $b['title'] ?? ''),
        );

        return $out;
    }

    /** @param array<string, mixed> $meta */
    public function save(string $slug, array $meta, string $html): void
    {
        $this->site->save($slug, $meta, $html);
    }

    public function delete(string $slug): bool
    {
        $resolved = $this->get($slug);
        if ($resolved === null || ($resolved['source'] ?? '') !== 'site') {
            return false;
        }

        return $this->site->delete($slug);
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

    public function siteRegistry(): PatternRegistry
    {
        return $this->site;
    }

    /**
     * @return array<string, PatternRegistry>
     */
    private function layersForRead(): array
    {
        $layers = ['core' => $this->core];
        if ($this->theme !== null) {
            $layers['theme'] = $this->theme;
        }
        $layers['site'] = $this->site;

        return $layers;
    }
}
