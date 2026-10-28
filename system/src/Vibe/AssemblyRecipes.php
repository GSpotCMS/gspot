<?php

declare(strict_types=1);

namespace GSpot\Vibe;

/**
 * MCP agent assembly recipes — documented tool sequences for "Find → Hit → Ship".
 */
final class AssemblyRecipes
{
    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $id): ?array
    {
        $recipes = self::all();

        return $recipes[$id] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'groundswell-commerce' => [
                'id' => 'groundswell-commerce',
                'title' => 'Groundswell landing + commerce CTA',
                'tagline' => 'Find the Spot. Hit it. Ship it.',
                'steps' => [
                    ['tool' => 'gspot_list_patterns', 'purpose' => 'Pick a hero/manifesto pattern slug'],
                    ['tool' => 'gspot_get_pattern', 'purpose' => 'Load pattern HTML for reference'],
                    ['tool' => 'gspot_edit_page', 'purpose' => 'Insert [pattern slug="manifesto-groundswell"] or hero-synthwave; set aura fields'],
                    ['tool' => 'gspot_apply_vibe', 'purpose' => 'Apply synthwave (or brand preset) to theme'],
                    ['tool' => 'gspot_list_products', 'purpose' => 'Choose product slug for CTA'],
                    ['tool' => 'gspot_commerce_checkout_smoke', 'purpose' => 'Validate cart + Stripe config'],
                    ['tool' => 'gspot_vibe_check', 'purpose' => 'Vibe Assurance gate (score >= 60)'],
                    ['tool' => 'gspot_cache_clear', 'purpose' => 'Clear caches before deploy'],
                ],
                'example_page_slug' => 'home',
                'example_pattern' => 'manifesto-groundswell',
                'example_product' => 'team-dc-mug',
            ],
        ];
    }
}
