<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use Stripe\Checkout\Session;
use Stripe\Stripe;

final class StripeCheckout
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $successUrl,
        private readonly string $cancelUrl,
    ) {
        if ($this->secretKey !== '') {
            Stripe::setApiKey($this->secretKey);
        }
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    /**
     * @param list<array{product: array<string, mixed>, qty: int}> $lines
     * @param array{coupon?: string, cart?: string} $metadata
     */
    public function createSession(array $lines, string $defaultCurrency, array $metadata = []): Session
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Stripe secret key not configured');
        }

        $lineItems = [];
        foreach ($lines as $line) {
            $product = $line['product'];
            $qty = $line['qty'];
            $priceId = trim((string) ($product['stripe_price_id'] ?? ''));

            if ($priceId !== '') {
                $lineItems[] = [
                    'price' => $priceId,
                    'quantity' => $qty,
                ];
                continue;
            }

            $currency = strtolower((string) ($product['currency'] ?? $defaultCurrency));
            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => (int) ($product['price_cents'] ?? 0),
                    'product_data' => [
                        'name' => (string) ($product['title'] ?? 'Product'),
                        'description' => substr((string) ($product['description'] ?? ''), 0, 500),
                    ],
                ],
                'quantity' => $qty,
            ];
        }

        $meta = [];
        if (($metadata['coupon'] ?? '') !== '') {
            $meta['gspot_coupon'] = substr((string) $metadata['coupon'], 0, 32);
        }
        if (($metadata['cart'] ?? '') !== '') {
            $meta['gspot_cart'] = substr((string) $metadata['cart'], 0, 500);
        }

        $payload = [
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
            'allow_promotion_codes' => true,
        ];
        if ($meta !== []) {
            $payload['metadata'] = $meta;
        }

        return Session::create($payload);
    }
}
