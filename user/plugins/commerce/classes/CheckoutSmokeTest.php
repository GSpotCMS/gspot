<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

final class CheckoutSmokeTest
{
    public function __construct(
        private readonly Cart $cart,
        private readonly bool $stripeConfigured,
        private readonly int $flatShippingCents,
    ) {}

    /** @return array<string, mixed> */
    public function run(): array
    {
        $issues = [];
        $warnings = [];

        if ($this->cart->isEmpty()) {
            $issues[] = 'Cart is empty';
        }

        $modeError = $this->cart->validateCheckoutMode();
        if ($modeError !== null) {
            $issues[] = $modeError;
        }

        $currencyError = $this->cart->validateCheckoutCurrency();
        if ($currencyError !== null) {
            $issues[] = $currencyError;
        }

        if (!$this->stripeConfigured) {
            $issues[] = 'STRIPE_SECRET_KEY not configured';
        }

        $currency = $this->cart->checkoutCurrency();
        $lines = $this->cart->lines();
        foreach ($lines as $line) {
            $slug = (string) ($line['product']['slug'] ?? '');
            if (($line['product']['active'] ?? true) === false) {
                $warnings[] = "Product inactive: {$slug}";
            }
            $stock = $line['product']['stock'] ?? null;
            if ($stock !== null && (int) $stock < (int) $line['qty']) {
                $issues[] = "Insufficient stock for {$slug}";
            }
        }

        $flat = $this->flatShippingCents;
        $gift = $currency !== '' ? $this->cart->giftRedemptionCents($flat, $currency) : 0;
        $discount = $this->cart->discountCents();
        $taxable = $currency !== '' ? $this->cart->taxableCents($flat, $currency) : 0;

        return [
            'ready' => $issues === [],
            'checkout_mode' => $this->cart->checkoutMode(),
            'currency' => $currency !== '' ? strtoupper($currency) : null,
            'line_count' => count($lines),
            'subtotal_cents' => $this->cart->subtotalCents(),
            'discount_cents' => $discount,
            'gift_redemption_cents' => $gift,
            'taxable_cents' => $taxable,
            'shipping_cents' => $this->cart->shippingCents($flat),
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }
}
