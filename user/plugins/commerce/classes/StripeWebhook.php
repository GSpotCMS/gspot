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

        $this->orders->save($id, [
            'status' => 'paid',
            'email' => (string) ($session->customer_details->email ?? $session->customer_email ?? ''),
            'amount_total' => (int) ($session->amount_total ?? 0),
            'currency' => strtolower((string) ($session->currency ?? 'aud')),
            'payment_status' => (string) ($session->payment_status ?? ''),
            'line_items' => $lines,
            'created' => date('c', (int) ($session->created ?? time())),
        ]);
    }
}
