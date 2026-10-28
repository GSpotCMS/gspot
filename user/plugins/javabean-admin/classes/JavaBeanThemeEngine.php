<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\JavabeanAdmin;

class JavaBeanThemeEngine
{
    public function buildCss(array $pluginConfig): string
    {
        if (empty($pluginConfig['enabled'])) {
            return '';
        }

        $slug = (string) ($pluginConfig['active_preset'] ?? 'javabean-classic');
        $preset = JavaBeanPresetRegistry::get($slug) ?? JavaBeanPresetRegistry::get('javabean-classic');
        if ($preset === null) {
            return '';
        }

        $styling = $this->normalizeStyling(
            is_array($pluginConfig['styling'] ?? null) ? $pluginConfig['styling'] : [],
            $pluginConfig
        );

        $fontKey = (string) ($preset['fontFamily'] ?? 'jost');
        if (array_key_exists('usePresetFont', $styling) && $styling['usePresetFont'] === false && !empty($styling['fontFamily'])) {
            $fontKey = (string) $styling['fontFamily'];
        }

        $stacks = JavaBeanPresetRegistry::fontStacks();
        $fontStack = $stacks[$fontKey] ?? $stacks['jost'];

        $tokens = is_array($preset['tokens'] ?? null) ? $preset['tokens'] : [];
        $light = is_array($tokens['light'] ?? null) ? $tokens['light'] : [];
        $dark = is_array($tokens['dark'] ?? null) ? $tokens['dark'] : [];

        if (empty($styling['usePresetAccent'])) {
            $accent = sprintf(
                'hsl(%d %d%% 55%%)',
                (int) ($styling['accentHue'] ?? 271),
                (int) ($styling['accentSaturation'] ?? 91)
            );
            $light['primary'] = $accent;
            $dark['primary'] = $accent;
        }

        $density = (string) ($styling['density'] ?? 'comfy');
        $radiusKey = (string) ($styling['radius'] ?? 'default');

        $radius = match ($radiusKey) {
            'subtle' => '0.35rem',
            'round' => '1rem',
            default => match ($density) {
                'compact' => '0.4rem',
                'spacious' => '0.85rem',
                default => '0.625rem',
            },
        };

        $spacing = match ($density) {
            'compact' => '0.85',
            'spacious' => '1.12',
            default => '1',
        };

        $customCss = trim((string) ($styling['customCss'] ?? ''));

        $css = "/* JavaBean for GSpot Admin — {$preset['name']} */\n";
        $css .= ":root {\n";
        $css .= $this->tokenBlock($light, $fontStack, $radius, $spacing);
        $css .= "}\n\n";
        $css .= "@media (prefers-color-scheme: dark) {\n  :root {\n";
        $css .= $this->tokenBlock($dark, $fontStack, $radius, $spacing, '    ');
        $css .= "  }\n}\n\n";
        $css .= ".dark {\n";
        $css .= $this->tokenBlock($dark, $fontStack, $radius, $spacing);
        $css .= "}\n";

        if ($customCss !== '') {
            $css .= "\n/* JavaBean custom CSS */\n" . $customCss . "\n";
        }

        return $css;
    }

    private function normalizeStyling(array $styling, array $legacyCfg = []): array
    {
        if ($styling === [] && (isset($legacyCfg['density']) || isset($legacyCfg['custom_css']))) {
            $styling = [
                'density' => (string) ($legacyCfg['density'] ?? 'comfy'),
                'customCss' => (string) ($legacyCfg['custom_css'] ?? ''),
            ];
        }

        $defaults = [
            'accentHue' => null,
            'accentSaturation' => null,
            'usePresetAccent' => true,
            'density' => 'comfy',
            'fontFamily' => null,
            'usePresetFont' => true,
            'radius' => 'default',
            'customCss' => '',
        ];

        return array_merge($defaults, $styling);
    }

    private function tokenBlock(array $tokens, string $fontStack, string $radius, string $spacing, string $indent = '  '): string
    {
        $bg = $tokens['background'] ?? 'hsl(0 0% 100%)';
        $fg = $tokens['foreground'] ?? 'hsl(240 10% 4%)';
        $primary = $tokens['primary'] ?? 'hsl(271 91% 55%)';
        $sidebar = $tokens['sidebar'] ?? $bg;
        $border = $tokens['border'] ?? 'hsl(240 6% 90%)';
        $muted = $tokens['muted'] ?? $sidebar;
        $mutedFg = $tokens['mutedForeground'] ?? $fg;

        $lines = [
            "{$indent}--bg: {$bg};",
            "{$indent}--surface: {$sidebar};",
            "{$indent}--card: {$bg};",
            "{$indent}--text: {$fg};",
            "{$indent}--muted: {$mutedFg};",
            "{$indent}--primary: {$primary};",
            "{$indent}--secondary: {$muted};",
            "{$indent}--accent: {$primary};",
            "{$indent}--border: {$border};",
            "{$indent}--radius: {$radius};",
            "{$indent}--font-body: {$fontStack};",
            "{$indent}--javabean-density: {$spacing};",
        ];

        return implode("\n", $lines) . "\n";
    }
}
