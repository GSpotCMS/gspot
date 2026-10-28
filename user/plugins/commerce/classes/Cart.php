<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use GSpot\Security\SessionGuard;

final class Cart
{
    private const SESSION_KEY = 'gspot_commerce_cart';
    private const COUPON_KEY = 'gspot_commerce_coupon';
    private const TAX_COUNTRY_KEY = 'gspot_commerce_tax_country';
    private const REMINDER_EMAIL_KEY = 'gspot_commerce_reminder_email';
    private const GIFT_CARD_KEY = 'gspot_commerce_gift_card';

    public function __construct(
        private readonly ProductRegistry $products,
        private readonly ?CouponRegistry $coupons = null,
        private readonly ?GiftCardRegistry $giftCards = null,
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

    public function taxableCents(int $flatShippingCents, string $currency = 'aud'): int
    {
        return max(0, $this->subtotalCents() - $this->discountCents() + $this->shippingCents($flatShippingCents) - $this->giftRedemptionCents($flatShippingCents, $currency));
    }

    public function totalCents(int $flatShippingCents = 0, int $taxCents = 0, string $currency = 'aud'): int
    {
        return max(0, $this->taxableCents($flatShippingCents, $currency) + max(0, $taxCents));
    }

    public function checkoutCurrency(): string
    {
        $currency = null;
        foreach ($this->lines() as $line) {
            $cur = strtolower((string) ($line['product']['currency'] ?? 'aud'));
            if ($currency === null) {
                $currency = $cur;
            } elseif ($currency !== $cur) {
                return '';
            }
        }

        return $currency ?? 'aud';
    }

    public function validateCheckoutCurrency(): ?string
    {
        if ($this->checkoutCurrency() === '') {
            return 'Mixed currencies in cart. Checkout one currency at a time.';
        }

        return null;
    }

    public function applyGiftCard(string $code): ?string
    {
        if ($this->giftCards === null) {
            return 'Gift cards are not available';
        }

        $currency = $this->checkoutCurrency();
        if ($currency === '') {
            return 'Resolve mixed currencies before applying a gift card';
        }

        $card = $this->giftCards->resolveForCheckout($code, $currency);
        if ($card === null) {
            return 'Invalid gift card or wrong currency';
        }

        SessionGuard::start();
        $_SESSION[self::GIFT_CARD_KEY] = (string) ($card['code'] ?? '');

        return null;
    }

    public function removeGiftCard(): void
    {
        SessionGuard::start();
        unset($_SESSION[self::GIFT_CARD_KEY]);
    }

    /** @return array<string, mixed>|null */
    public function appliedGiftCard(?string $currency = null): ?array
    {
        SessionGuard::start();
        $code = (string) ($_SESSION[self::GIFT_CARD_KEY] ?? '');
        if ($code === '' || $this->giftCards === null) {
            return null;
        }

        $cur = $currency ?? $this->checkoutCurrency();
        if ($cur === '') {
            return null;
        }

        return $this->giftCards->resolveForCheckout($code, $cur);
    }

    public function giftRedemptionCents(int $flatShippingCents, string $currency): int
    {
        $card = $this->appliedGiftCard($currency);
        if ($card === null) {
            return 0;
        }

        $due = max(0, $this->subtotalCents() - $this->discountCents() + $this->shippingCents($flatShippingCents));

        return min((int) ($card['balance_cents'] ?? 0), $due);
    }

    public function setTaxCountry(string $countryCode): void
    {
        SessionGuard::start();
        $code = strtoupper(preg_replace('/[^A-Z]/', '', strtoupper($countryCode)) ?? '');
        $_SESSION[self::TAX_COUNTRY_KEY] = $code !== '' ? $code : 'AU';
    }

    public function taxCountry(): string
    {
        SessionGuard::start();
        $code = (string) ($_SESSION[self::TAX_COUNTRY_KEY] ?? '');

        return $code !== '' ? $code : 'AU';
    }

    public function setReminderEmail(string $email): void
    {
        SessionGuard::start();
        $_SESSION[self::REMINDER_EMAIL_KEY] = strtolower(trim($email));
    }

    public function reminderEmail(): string
    {
        SessionGuard::start();

        return (string) ($_SESSION[self::REMINDER_EMAIL_KEY] ?? '');
    }

    /** @return 'payment'|'subscription' */
    public function checkoutMode(): string
    {
        $hasSub = false;
        $hasOne = false;
        foreach ($this->lines() as $line) {
            if (($line['product']['billing_type'] ?? 'one_time') === 'subscription') {
                $hasSub = true;
            } else {
                $hasOne = true;
            }
        }

        return $hasSub && !$hasOne ? 'subscription' : 'payment';
    }

    public function validateCheckoutMode(): ?string
    {
        $hasSub = false;
        $hasOne = false;
        foreach ($this->lines() as $line) {
            if (($line['product']['billing_type'] ?? 'one_time') === 'subscription') {
                $hasSub = true;
                if (trim((string) ($line['product']['stripe_price_id'] ?? '')) === '') {
                    return 'Subscription product missing Stripe Price ID: ' . ($line['product']['slug'] ?? '');
                }
            } else {
                $hasOne = true;
            }
        }

        if ($hasSub && $hasOne) {
            return 'Mixed one-time and subscription items are not supported in one checkout.';
        }

        return null;
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

    public function clearCheckoutSession(): void
    {
        SessionGuard::start();
        unset($_SESSION[self::SESSION_KEY], $_SESSION[self::COUPON_KEY], $_SESSION[self::GIFT_CARD_KEY]);
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
