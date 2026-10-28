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
        private readonly ?BuyerReceiptMailer $buyerMailer = null,
        private readonly ?AbandonedCartRegistry $abandoned = null,
        private readonly ?GiftCardRegistry $giftCards = null,
        private readonly ?SubscriptionRegistry $subscriptions = null,
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

        match ($event->type) {
            'checkout.session.completed' => $this->recordCheckoutSession($event),
            'customer.subscription.updated', 'customer.subscription.deleted' => $this->recordSubscriptionLifecycle($event),
            'invoice.paid', 'invoice.payment_failed' => $this->recordInvoiceLifecycle($event),
            'charge.refunded' => $this->recordChargeRefunded($event),
            default => null,
        };
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
        $taxCountry = (string) ($session->metadata->gspot_tax_country ?? '');
        $reminderEmail = (string) ($session->metadata->gspot_reminder_email ?? '');
        $giftCode = (string) ($session->metadata->gspot_gift_card ?? '');
        $giftCents = (int) ($session->metadata->gspot_gift_cents ?? 0);
        $checkoutCurrency = strtolower((string) ($session->currency ?? 'aud'));

        $order = [
            'status' => 'paid',
            'email' => (string) ($session->customer_details->email ?? $session->customer_email ?? ''),
            'amount_total' => (int) ($session->amount_total ?? 0),
            'currency' => $checkoutCurrency,
            'payment_status' => (string) ($session->payment_status ?? ''),
            'line_items' => $lines,
            'coupon' => $coupon,
            'gift_card' => $giftCode,
            'gift_redeemed_cents' => $giftCents,
            'tax_country' => $taxCountry,
            'cart_snapshot' => $cartJson,
            'billing_mode' => (string) ($session->mode ?? 'payment'),
            'stripe_payment_intent' => (string) ($session->payment_intent ?? ''),
            'stripe_customer_id' => (string) ($session->customer ?? ''),
            'refund_status' => 'none',
            'created' => date('c', (int) ($session->created ?? time())),
        ];

        $subscriptionId = (string) ($session->subscription ?? '');
        if ($subscriptionId !== '') {
            $order['stripe_subscription_id'] = $subscriptionId;
        }

        if ($this->digital !== null && $cartJson !== '') {
            $order['downloads'] = $this->digital->grantForCart($id, $cartJson);
        }

        $this->orders->save($id, $order);
        $this->fulfillInventory($cartJson);
        if ($coupon !== '' && $this->coupons !== null) {
            $this->coupons->incrementUses($coupon);
        }
        if ($giftCode !== '' && $giftCents > 0 && $this->giftCards !== null) {
            $this->giftCards->redeem($giftCode, $giftCents);
        }

        if ($subscriptionId !== '' && $this->subscriptions !== null) {
            $this->subscriptions->upsert($subscriptionId, 'active', [
                'email' => (string) ($order['email'] ?? ''),
                'checkout_session' => $id,
            ]);
        }

        $this->notifier?->notify($id, $order);
        $this->buyerMailer?->send($id, $order);

        $clearEmail = $reminderEmail !== '' ? $reminderEmail : (string) ($order['email'] ?? '');
        if ($clearEmail !== '' && $this->abandoned !== null) {
            $this->abandoned->deleteByEmail($clearEmail);
        }
    }

    private function recordSubscriptionLifecycle(Event $event): void
    {
        if ($this->subscriptions === null) {
            return;
        }

        $object = $event->data->object;
        $id = (string) ($object->id ?? '');
        if ($id === '') {
            return;
        }

        $status = (string) ($object->status ?? 'unknown');
        if ($event->type === 'customer.subscription.deleted') {
            $status = 'canceled';
        }

        $this->subscriptions->upsert($id, $status, [
            'email' => (string) ($object->customer_email ?? ''),
        ]);
    }

    private function recordInvoiceLifecycle(Event $event): void
    {
        if ($this->subscriptions === null) {
            return;
        }

        $invoice = $event->data->object;
        $subscriptionId = (string) ($invoice->subscription ?? '');
        if ($subscriptionId === '') {
            return;
        }

        $invoiceStatus = $event->type === 'invoice.paid' ? 'paid' : 'payment_failed';
        $this->subscriptions->upsert($subscriptionId, 'active', [
            'last_invoice_status' => $invoiceStatus,
            'email' => (string) ($invoice->customer_email ?? ''),
        ]);
    }

    private function recordChargeRefunded(Event $event): void
    {
        $charge = $event->data->object;
        $paymentIntent = (string) ($charge->payment_intent ?? '');
        if ($paymentIntent === '') {
            return;
        }

        $order = $this->orders->findByPaymentIntent($paymentIntent);
        if ($order === null) {
            return;
        }

        $refunded = (int) ($charge->amount_refunded ?? 0);
        $total = (int) ($order['amount_total'] ?? 0);
        $status = $refunded >= $total && $total > 0 ? 'full' : 'partial';
        $this->orders->markRefunded((string) ($order['id'] ?? ''), $refunded, $status);
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
