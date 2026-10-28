<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;

final class CommerceCheckoutAnalyzer
{
    public function __construct(
        private readonly ProductRegistry $products,
        private readonly bool $stripeConfigured,
    ) {}

    /**
     * @param list<array{slug: string, qty: int}> $rows
     * @return array<string, mixed>
     */
    public function analyze(array $rows): array
    {
        $issues = [];
        $warnings = [];
        $currency = null;
        $hasSub = false;
        $hasOne = false;

        foreach ($rows as $row) {
            $slug = PathGuard::sanitizeSlug((string) ($row['slug'] ?? ''));
            $qty = max(1, (int) ($row['qty'] ?? 1));
            if ($slug === '') {
                $issues[] = 'Empty slug in cart row';
                continue;
            }

            $product = $this->products->get($slug);
            if ($product === null) {
                $issues[] = "Unknown product: {$slug}";
                continue;
            }

            $cur = strtolower((string) ($product['currency'] ?? 'aud'));
            if ($currency === null) {
                $currency = $cur;
            } elseif ($currency !== $cur) {
                $issues[] = 'Mixed currencies in cart';
            }

            if (($product['billing_type'] ?? 'one_time') === 'subscription') {
                $hasSub = true;
                if (trim((string) ($product['stripe_price_id'] ?? '')) === '') {
                    $issues[] = "Subscription missing Price ID: {$slug}";
                }
            } else {
                $hasOne = true;
            }

            if (($product['active'] ?? true) === false) {
                $warnings[] = "Inactive product: {$slug}";
            }

            $stock = $product['stock'] ?? null;
            if ($stock !== null && (int) $stock < $qty) {
                $issues[] = "Insufficient stock for {$slug}";
            }
        }

        if ($rows === []) {
            $issues[] = 'Cart is empty';
        }

        if ($hasSub && $hasOne) {
            $issues[] = 'Mixed one-time and subscription items';
        }

        if (!$this->stripeConfigured) {
            $issues[] = 'STRIPE_SECRET_KEY not configured';
        }

        return [
            'ready' => $issues === [],
            'checkout_mode' => $hasSub && !$hasOne ? 'subscription' : 'payment',
            'currency' => $currency !== null ? strtoupper($currency) : null,
            'line_count' => count($rows),
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }
}
