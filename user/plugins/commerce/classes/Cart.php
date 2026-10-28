<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use GSpot\Security\SessionGuard;

final class Cart
{
    private const SESSION_KEY = 'gspot_commerce_cart';

    public function __construct(private readonly ProductRegistry $products) {}

    /** @return array<string, int> slug => qty */
    public function items(): array
    {
        SessionGuard::start();

        $items = $_SESSION[self::SESSION_KEY] ?? [];

        return is_array($items) ? $items : [];
    }

    public function add(string $slug, int $qty = 1): void
    {
        $slug = PathGuard::sanitizeSlug($slug);
        if ($slug === '' || $this->products->get($slug) === null) {
            return;
        }

        SessionGuard::start();
        $items = $this->items();
        $items[$slug] = min(99, ($items[$slug] ?? 0) + max(1, $qty));
        $_SESSION[self::SESSION_KEY] = $items;
    }

    public function update(string $slug, int $qty): void
    {
        SessionGuard::start();
        $items = $this->items();
        if ($qty <= 0) {
            unset($items[$slug]);
        } else {
            $items[$slug] = min(99, $qty);
        }
        $_SESSION[self::SESSION_KEY] = $items;
    }

    public function clear(): void
    {
        SessionGuard::start();
        unset($_SESSION[self::SESSION_KEY]);
    }

    /** @return list<array{product: array<string, mixed>, qty: int, line_cents: int}> */
    public function lines(): array
    {
        $lines = [];
        foreach ($this->items() as $slug => $qty) {
            $product = $this->products->get($slug);
            if ($product === null) {
                continue;
            }
            $price = (int) ($product['price_cents'] ?? 0);
            $lines[] = [
                'product' => $product,
                'qty' => $qty,
                'line_cents' => $price * $qty,
            ];
        }

        return $lines;
    }

    public function subtotalCents(): int
    {
        $total = 0;
        foreach ($this->lines() as $line) {
            $total += $line['line_cents'];
        }

        return $total;
    }

    public function isEmpty(): bool
    {
        return $this->lines() === [];
    }
}
