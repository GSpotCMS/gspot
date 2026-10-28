<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\JavabeanAdmin;

class JavaBeanFontCatalog
{
    public static function all(): array
    {
        return [
            'google-sans' => [
                'label' => 'Google Sans',
                'category' => 'sans',
                'stack' => "'Google Sans', ui-sans-serif, system-ui, -apple-system, sans-serif",
                'google' => null,
                'andy' => false,
            ],
            'inter' => [
                'label' => 'Inter',
                'category' => 'sans',
                'stack' => "'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif",
                'google' => 'Inter:wght@400;500;600;700',
                'andy' => false,
            ],
            'public-sans' => [
                'label' => 'Public Sans',
                'category' => 'sans',
                'stack' => "'Public Sans', ui-sans-serif, system-ui, -apple-system, sans-serif",
                'google' => 'Public+Sans:wght@400;500;600;700',
                'andy' => false,
            ],
            'nunito-sans' => [
                'label' => 'Nunito Sans',
                'category' => 'sans',
                'stack' => "'Nunito Sans', ui-sans-serif, system-ui, -apple-system, sans-serif",
                'google' => 'Nunito+Sans:wght@400;500;600;700',
                'andy' => false,
            ],
            'jost' => [
                'label' => 'Jost',
                'category' => 'sans',
                'stack' => "'Jost', ui-sans-serif, system-ui, -apple-system, sans-serif",
                'google' => 'Jost:wght@400;500;600;700',
                'andy' => false,
            ],
            'space-grotesk' => [
                'label' => 'Space Grotesk',
                'category' => 'sans',
                'stack' => "'Space Grotesk', ui-sans-serif, system-ui, sans-serif",
                'google' => 'Space+Grotesk:wght@400;500;600;700',
                'andy' => false,
            ],
            'outfit' => [
                'label' => 'Outfit',
                'category' => 'sans',
                'stack' => "'Outfit', ui-sans-serif, system-ui, sans-serif",
                'google' => 'Outfit:wght@400;500;600;700',
                'andy' => false,
            ],
            'raleway' => [
                'label' => 'Raleway',
                'category' => 'sans',
                'stack' => "'Raleway', ui-sans-serif, system-ui, sans-serif",
                'google' => 'Raleway:wght@400;500;600;700',
                'andy' => false,
            ],
            'sora' => [
                'label' => 'Sora',
                'category' => 'sans',
                'stack' => "'Sora', ui-sans-serif, system-ui, sans-serif",
                'google' => 'Sora:wght@400;500;600;700',
                'andy' => false,
            ],
            'lora' => [
                'label' => 'Lora',
                'category' => 'serif',
                'stack' => "'Lora', ui-serif, Georgia, 'Times New Roman', serif",
                'google' => 'Lora:wght@400;500;600;700',
                'andy' => false,
            ],
            'merriweather' => [
                'label' => 'Merriweather',
                'category' => 'serif',
                'stack' => "'Merriweather', ui-serif, Georgia, 'Times New Roman', serif",
                'google' => 'Merriweather:wght@400;700',
                'andy' => false,
            ],
            'bitter' => [
                'label' => 'Bitter',
                'category' => 'serif',
                'stack' => "'Bitter', ui-serif, Georgia, 'Times New Roman', serif",
                'google' => 'Bitter:wght@400;500;600;700',
                'andy' => false,
            ],
            'jetbrains-mono' => [
                'label' => 'JetBrains Mono',
                'category' => 'mono',
                'stack' => "'JetBrains Mono', ui-monospace, 'Cascadia Code', Consolas, monospace",
                'google' => 'JetBrains+Mono:wght@400;500;600;700',
                'andy' => false,
            ],
            'fira-code' => [
                'label' => 'Fira Code',
                'category' => 'mono',
                'stack' => "'Fira Code', ui-monospace, 'Cascadia Code', Consolas, monospace",
                'google' => 'Fira+Code:wght@400;500;600;700',
                'andy' => false,
            ],
        ];
    }

    public static function stacks(): array
    {
        $out = [];
        foreach (self::all() as $slug => $meta) {
            $out[$slug] = $meta['stack'];
        }
        return $out;
    }

    public static function isValid(string $slug): bool
    {
        return isset(self::all()[$slug]);
    }

    public static function forClient(): array
    {
        $out = [];
        foreach (self::all() as $slug => $meta) {
            $out[] = [
                'slug' => $slug,
                'label' => $meta['label'],
                'category' => $meta['category'],
                'stack' => $meta['stack'],
                'google' => $meta['google'],
            ];
        }
        return $out;
    }
}
