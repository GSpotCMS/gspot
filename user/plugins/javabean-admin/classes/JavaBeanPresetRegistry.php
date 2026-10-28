<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\JavabeanAdmin;

class JavaBeanPresetRegistry
{
    private static ?array $cache = null;

    public static function fontStacks(): array
    {
        require_once __DIR__ . '/JavaBeanFontCatalog.php';
        return JavaBeanFontCatalog::stacks();
    }

    public static function all(): array
    {
        if (self::$cache !== null) {
            return array_values(self::$cache);
        }

        $file = dirname(__DIR__) . '/assets/presets/all.json';
        if (!is_file($file)) {
            self::$cache = ['javabean-classic' => self::fallbackClassic()];
            return array_values(self::$cache);
        }

        $raw = json_decode((string) file_get_contents($file), true);
        $presets = is_array($raw['presets'] ?? null) ? $raw['presets'] : [];
        self::$cache = [];
        foreach ($presets as $preset) {
            if (!is_array($preset) || empty($preset['slug'])) {
                continue;
            }
            self::$cache[(string) $preset['slug']] = $preset;
        }

        if (self::$cache === []) {
            self::$cache = ['javabean-classic' => self::fallbackClassic()];
        }

        return array_values(self::$cache);
    }

    public static function get(string $slug): ?array
    {
        self::all();
        return self::$cache[$slug] ?? null;
    }

    private static function fallbackClassic(): array
    {
        return [
            'slug' => 'javabean-classic',
            'name' => 'JavaBean Classic',
            'tagline' => '2004 coffee energy',
            'fontFamily' => 'jost',
            'accentDefault' => ['hue' => 25, 'saturation' => 95],
            'tokens' => [
                'light' => [
                    'background' => 'hsl(35 33% 97%)',
                    'foreground' => 'hsl(25 30% 18%)',
                    'primary' => 'hsl(25 95% 42%)',
                    'sidebar' => 'hsl(30 28% 94%)',
                    'border' => 'hsl(30 18% 85%)',
                    'muted' => 'hsl(32 20% 92%)',
                    'mutedForeground' => 'hsl(25 12% 42%)',
                ],
                'dark' => [
                    'background' => 'hsl(25 18% 9%)',
                    'foreground' => 'hsl(35 25% 92%)',
                    'primary' => 'hsl(28 90% 55%)',
                    'sidebar' => 'hsl(25 16% 12%)',
                    'border' => 'hsl(25 12% 22%)',
                    'muted' => 'hsl(25 14% 16%)',
                    'mutedForeground' => 'hsl(30 10% 62%)',
                ],
            ],
        ];
    }
}
