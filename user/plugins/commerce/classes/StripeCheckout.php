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
     * @param array{coupon?: string, cart?: string, tax_country?: string, reminder_email?: string} $metadata
     */
    public function createSession(
        array $lines,
        string $defaultCurrency,
        array $metadata = [],
        int $shippingCents = 0,
        int $taxCents = 0,
        string $mode = 'payment',
        bool $automaticTax = false,
        bool $invoiceCheckout = false,
    ): Session {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Stripe secret key not configured');
        }

        if ($mode === 'subscription') {
            return $this->createSubscriptionSession($lines, $defaultCurrency, $metadata);
        }

        $lineItems = $this->buildPaymentLineItems($lines, $defaultCurrency);
        $this->appendFeeLine($lineItems, $lines, $defaultCurrency, $shippingCents, 'Shipping', 'Flat-rate shipping');
        if (!$automaticTax) {
            $this->appendFeeLine($lineItems, $lines, $defaultCurrency, $taxCents, 'Tax', 'Regional tax');
        }

        return Session::create($this->payload($lineItems, 'payment', $metadata, $automaticTax, $invoiceCheckout));
    }

    /**
     * @param list<array{product: array<string, mixed>, qty: int}> $lines
     * @param array{coupon?: string, cart?: string, tax_country?: string, reminder_email?: string} $metadata
     */
    private function createSubscriptionSession(array $lines, string $defaultCurrency, array $metadata): Session
    {
        $lineItems = [];
        foreach ($lines as $line) {
            $priceId = trim((string) ($line['product']['stripe_price_id'] ?? ''));
            if ($priceId === '') {
                throw new \RuntimeException('Subscription checkout requires Stripe Price ID');
            }
            $lineItems[] = [
                'price' => $priceId,
                'quantity' => max(1, (int) $line['qty']),
            ];
        }

        $payload = $this->payload($lineItems, 'subscription', $metadata, false, false);
        unset($payload['allow_promotion_codes']);

        return Session::create($payload);
    }

    /**
     * @param list<array{product: array<string, mixed>, qty: int}> $lines
     * @return list<array<string, mixed>>
     */
    private function buildPaymentLineItems(array $lines, string $defaultCurrency): array
    {
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

        return $lineItems;
    }

    /**
     * @param list<array<string, mixed>> $lineItems
     * @param list<array{product: array<string, mixed>, qty: int}> $lines
     */
    private function appendFeeLine(
        array &$lineItems,
        array $lines,
        string $defaultCurrency,
        int $amountCents,
        string $name,
        string $description,
    ): void {
        if ($amountCents <= 0 || $lineItems === []) {
            return;
        }

        $currency = strtolower($defaultCurrency);
        if (isset($lineItems[0]['price'])) {
            $currency = strtolower((string) ($lines[0]['product']['currency'] ?? $defaultCurrency));
        }

        $lineItems[] = [
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => $amountCents,
                'product_data' => [
                    'name' => $name,
                    'description' => $description,
                ],
            ],
            'quantity' => 1,
        ];
    }

    /**
     * @param list<array<string, mixed>> $lineItems
     * @param array{coupon?: string, cart?: string, tax_country?: string, reminder_email?: string, gift_card?: string, gift_cents?: int} $metadata
     * @return array<string, mixed>
     */
    private function payload(array $lineItems, string $mode, array $metadata, bool $automaticTax, bool $invoiceCheckout): array
    {
        $meta = [];
        if (($metadata['coupon'] ?? '') !== '') {
            $meta['gspot_coupon'] = substr((string) $metadata['coupon'], 0, 32);
        }
        if (($metadata['cart'] ?? '') !== '') {
            $meta['gspot_cart'] = substr((string) $metadata['cart'], 0, 500);
        }
        if (($metadata['tax_country'] ?? '') !== '') {
            $meta['gspot_tax_country'] = substr((string) $metadata['tax_country'], 0, 8);
        }
        if (($metadata['reminder_email'] ?? '') !== '') {
            $meta['gspot_reminder_email'] = substr((string) $metadata['reminder_email'], 0, 120);
        }
        if (($metadata['gift_card'] ?? '') !== '') {
            $meta['gspot_gift_card'] = substr((string) $metadata['gift_card'], 0, 32);
            $meta['gspot_gift_cents'] = (string) max(0, (int) ($metadata['gift_cents'] ?? 0));
        }
        if (($metadata['affiliate'] ?? '') !== '') {
            $meta['gspot_affiliate'] = substr((string) $metadata['affiliate'], 0, 32);
        }
        if (($metadata['b2b_company'] ?? '') !== '') {
            $meta['gspot_b2b_company'] = substr((string) $metadata['b2b_company'], 0, 120);
        }
        if (!empty($metadata['b2b_invoice'])) {
            $meta['gspot_b2b_invoice'] = '1';
        }

        $payload = [
            'mode' => $mode,
            'line_items' => $lineItems,
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
            'allow_promotion_codes' => true,
        ];
        if ($meta !== []) {
            $payload['metadata'] = $meta;
        }
        if ($automaticTax) {
            $payload['automatic_tax'] = ['enabled' => true];
            $payload['billing_address_collection'] = 'required';
        }
        if ($invoiceCheckout && $mode === 'payment') {
            $payload['invoice_creation'] = ['enabled' => true];
            $payload['customer_creation'] = 'always';
        }

        return $payload;
    }
}
