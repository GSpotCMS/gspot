<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\GspotPatterns;

class PatternRenderer
{
    public function __construct(private readonly LayeredPatternRegistry $registry) {}

    public function expand(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = (string) preg_replace(
            '/<p>\s*(\[pattern\s+slug=["\'][a-z0-9-]+["\']\s*\])\s*<\/p>/i',
            '$1',
            $html
        );

        $html = $this->expandShortcodes($html);
        $html = $this->expandWpBlocks($html);

        return $html;
    }

    private function expandShortcodes(string $html): string
    {
        return (string) preg_replace_callback(
            '/(?:<p>\s*)?\[pattern\s+slug=["\']([a-z0-9-]+)["\']\s*\](?:\s*<\/p>)?/i',
            function (array $m): string {
                $pattern = $this->registry->get($m[1]);

                return $pattern ? $this->wrapPattern($pattern) : '<!-- unknown pattern: ' . htmlspecialchars($m[1]) . ' -->';
            },
            $html
        );
    }

    private function expandWpBlocks(string $html): string
    {
        return (string) preg_replace_callback(
            '/<!--\s*wp:pattern\/([a-z0-9-]+)\s*-->(.*?)<!--\s*\/wp:pattern\/\1\s*-->/is',
            function (array $m): string {
                $slug = $m[1];
                $inner = trim($m[2]);

                if ($inner !== '') {
                    return '<div class="gspot-pattern gspot-pattern--' . htmlspecialchars($slug) . '" data-gspot-pattern="' . htmlspecialchars($slug) . '">' . $inner . '</div>';
                }

                $pattern = $this->registry->get($slug);

                return $pattern ? $this->wrapPattern($pattern) : $m[0];
            },
            $html
        );
    }

    /** @param array<string, mixed> $pattern */
    private function wrapPattern(array $pattern): string
    {
        $slug = htmlspecialchars((string) ($pattern['slug'] ?? ''), ENT_QUOTES, 'UTF-8');
        $body = (string) ($pattern['html'] ?? '');

        return '<div class="gspot-pattern gspot-pattern--' . $slug . '" data-gspot-pattern="' . $slug . '">' . $body . '</div>';
    }
}
