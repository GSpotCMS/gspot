<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

final class DiscountCalculator
{
    /** @param array<string, mixed> $coupon */
    public function discountCents(int $subtotalCents, array $coupon): int
    {
        if ($subtotalCents <= 0) {
            return 0;
        }

        $type = strtolower((string) ($coupon['type'] ?? 'percent'));
        $amount = (int) ($coupon['amount'] ?? 0);
        if ($amount <= 0) {
            return 0;
        }

        if ($type === 'fixed') {
            return min($subtotalCents, $amount);
        }

        $pct = min(100, max(0, $amount));

        return (int) min($subtotalCents, round($subtotalCents * $pct / 100));
    }

    /**
     * Scale line unit prices so Stripe line total matches subtotal minus discount.
     *
     * @param list<array{product: array<string, mixed>, qty: int, line_cents: int}> $lines
     * @return list<array{product: array<string, mixed>, qty: int, line_cents: int}>
     */
    public function applyToLines(array $lines, int $discountCents): array
    {
        if ($discountCents <= 0 || $lines === []) {
            return $lines;
        }

        $subtotal = 0;
        foreach ($lines as $line) {
            $subtotal += $line['line_cents'];
        }

        if ($discountCents >= $subtotal) {
            throw new \InvalidArgumentException('Discount exceeds cart total');
        }

        $target = $subtotal - $discountCents;
        $adjusted = [];
        $running = 0;
        $count = count($lines);

        foreach ($lines as $i => $line) {
            $qty = max(1, (int) $line['qty']);
            $isLast = $i === $count - 1;

            if ($isLast) {
                $lineTotal = max(1, $target - $running);
            } else {
                $share = $line['line_cents'] / $subtotal;
                $lineTotal = max(1, (int) round($target * $share));
                $running += $lineTotal;
            }

            $unit = max(1, (int) round($lineTotal / $qty));
            $product = $line['product'];
            $product['price_cents'] = $unit;

            $adjusted[] = [
                'product' => $product,
                'qty' => $qty,
                'line_cents' => $unit * $qty,
            ];
        }

        return $adjusted;
    }
}
