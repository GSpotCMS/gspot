<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use GSpot\Security\SessionGuard;

final class Cart
{
    private const SESSION_KEY = 'gspot_commerce_cart';
    private const COUPON_KEY = 'gspot_commerce_coupon';

    public function __construct(
        private readonly ProductRegistry $products,
        private readonly ?CouponRegistry $coupons = null,
    ) {}

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
        $nextQty = ($items[$slug] ?? 0) + max(1, $qty);
        $available = $this->products->availableStock($slug);
        if ($available !== null) {
            $nextQty = min($nextQty, $available);
            if ($nextQty <= 0) {
                return;
            }
        }
        $items[$slug] = min(99, $nextQty);
        $_SESSION[self::SESSION_KEY] = $items;
    }

    public function update(string $slug, int $qty): void
    {
        SessionGuard::start();
        $items = $this->items();
        if ($qty <= 0) {
            unset($items[$slug]);
        } else {
            $available = $this->products->availableStock($slug);
            if ($available !== null) {
                $qty = min($qty, $available);
            }
            $items[$slug] = min(99, $qty);
        }
        $_SESSION[self::SESSION_KEY] = $items;
    }

    public function applyCoupon(string $code): ?string
    {
        if ($this->coupons === null) {
            return 'Coupons are not available';
        }

        $coupon = $this->coupons->resolveForCheckout($code);
        if ($coupon === null) {
            return 'Invalid or expired coupon code';
        }

        SessionGuard::start();
        $_SESSION[self::COUPON_KEY] = (string) ($coupon['code'] ?? '');

        return null;
    }

    public function removeCoupon(): void
    {
        SessionGuard::start();
        unset($_SESSION[self::COUPON_KEY]);
    }

    /** @return array<string, mixed>|null */
    public function appliedCoupon(): ?array
    {
        SessionGuard::start();
        $code = (string) ($_SESSION[self::COUPON_KEY] ?? '');
        if ($code === '' || $this->coupons === null) {
            return null;
        }

        return $this->coupons->resolveForCheckout($code);
    }

    public function discountCents(): int
    {
        $coupon = $this->appliedCoupon();
        if ($coupon === null) {
            return 0;
        }

        return (new DiscountCalculator())->discountCents($this->subtotalCents(), $coupon);
    }

    public function shippingCents(int $flatRateCents): int
    {
        if ($flatRateCents <= 0) {
            return 0;
        }

        foreach ($this->lines() as $line) {
            if (($line['product']['ship_required'] ?? true) === true) {
                return $flatRateCents;
            }
        }

        return 0;
    }

    public function totalCents(int $flatShippingCents = 0): int
    {
        return max(0, $this->subtotalCents() - $this->discountCents() + $this->shippingCents($flatShippingCents));
    }

    /** @return list<array{slug: string, qty: int}> */
    public function snapshotForOrder(): array
    {
        $out = [];
        foreach ($this->lines() as $line) {
            $slug = (string) ($line['product']['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $out[] = ['slug' => $slug, 'qty' => (int) $line['qty']];
        }

        return $out;
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
