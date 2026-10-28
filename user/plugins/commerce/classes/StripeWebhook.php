<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Stripe;
use Stripe\Webhook;

final class StripeWebhook
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
        private readonly OrderRegistry $orders,
        private readonly ?ProductRegistry $products = null,
        private readonly ?CouponRegistry $coupons = null,
        private readonly ?OrderReceiptNotifier $notifier = null,
        private readonly ?DigitalFulfillment $digital = null,
    ) {
        if ($this->secretKey !== '') {
            Stripe::setApiKey($this->secretKey);
        }
    }

    public function handle(string $payload, ?string $signatureHeader): void
    {
        if ($this->webhookSecret === '') {
            throw new \RuntimeException('STRIPE_WEBHOOK_SECRET not configured');
        }

        $event = Webhook::constructEvent($payload, (string) $signatureHeader, $this->webhookSecret);

        if ($event->type === 'checkout.session.completed') {
            $this->recordCheckoutSession($event);
        }
    }

    private function recordCheckoutSession(Event $event): void
    {
        /** @var Session $session */
        $session = $event->data->object;
        $id = (string) ($session->id ?? '');
        if ($id === '') {
            return;
        }

        $lines = [];
        if ($this->secretKey !== '' && isset($session->id)) {
            $full = Session::retrieve($session->id, ['expand' => ['line_items']]);
            foreach ($full->line_items->data ?? [] as $item) {
                $lines[] = [
                    'description' => (string) ($item->description ?? ''),
                    'quantity' => (int) ($item->quantity ?? 1),
                    'amount_subtotal' => (int) ($item->amount_subtotal ?? 0),
                ];
            }
        }

        $coupon = (string) ($session->metadata->gspot_coupon ?? '');
        $cartJson = (string) ($session->metadata->gspot_cart ?? '');

        $order = [
            'status' => 'paid',
            'email' => (string) ($session->customer_details->email ?? $session->customer_email ?? ''),
            'amount_total' => (int) ($session->amount_total ?? 0),
            'currency' => strtolower((string) ($session->currency ?? 'aud')),
            'payment_status' => (string) ($session->payment_status ?? ''),
            'line_items' => $lines,
            'coupon' => $coupon,
            'cart_snapshot' => $cartJson,
            'created' => date('c', (int) ($session->created ?? time())),
        ];

        if ($this->digital !== null && $cartJson !== '') {
            $order['downloads'] = $this->digital->grantForCart($id, $cartJson);
        }

        $this->orders->save($id, $order);
        $this->fulfillInventory($cartJson);
        if ($coupon !== '' && $this->coupons !== null) {
            $this->coupons->incrementUses($coupon);
        }
        $this->notifier?->notify($id, $order);
    }

    private function fulfillInventory(string $cartJson): void
    {
        if ($this->products === null || $cartJson === '') {
            return;
        }

        $rows = json_decode($cartJson, true);
        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $slug = (string) ($row['slug'] ?? '');
            $qty = (int) ($row['qty'] ?? 0);
            if ($slug === '' || $qty <= 0) {
                continue;
            }
            $this->products->adjustStock($slug, -$qty);
        }
    }
}
