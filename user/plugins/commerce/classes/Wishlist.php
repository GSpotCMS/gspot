<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use GSpot\Security\SessionGuard;

final class Wishlist
{
    private const SESSION_KEY = 'gspot_commerce_wishlist';

    public function __construct(private readonly ProductRegistry $products) {}

    /** @return list<string> */
    public function slugs(): array
    {
        SessionGuard::start();
        $items = $_SESSION[self::SESSION_KEY] ?? [];

        return is_array($items) ? array_values(array_unique(array_map('strval', $items))) : [];
    }

    public function add(string $slug): void
    {
        $slug = PathGuard::sanitizeSlug($slug);
        if ($slug === '' || $this->products->get($slug) === null) {
            return;
        }

        SessionGuard::start();
        $items = $this->slugs();
        if (!in_array($slug, $items, true)) {
            $items[] = $slug;
        }
        $_SESSION[self::SESSION_KEY] = $items;
    }

    public function remove(string $slug): void
    {
        SessionGuard::start();
        $slug = PathGuard::sanitizeSlug($slug);
        $_SESSION[self::SESSION_KEY] = array_values(array_filter(
            $this->slugs(),
            fn ($s) => $s !== $slug,
        ));
    }

    /** @return list<array<string, mixed>> */
    public function products(): array
    {
        $out = [];
        foreach ($this->slugs() as $slug) {
            $product = $this->products->get($slug);
            if ($product !== null) {
                $out[] = $product;
            }
        }

        return $out;
    }

    public function clear(): void
    {
        SessionGuard::start();
        unset($_SESSION[self::SESSION_KEY]);
    }
}
