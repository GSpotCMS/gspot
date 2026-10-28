<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use Stripe\Refund;
use Stripe\Stripe;

final class StripeRefundService
{
    public function __construct(private readonly string $secretKey)
    {
        if ($this->secretKey !== '') {
            Stripe::setApiKey($this->secretKey);
        }
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    public function refundPaymentIntent(string $paymentIntentId, ?int $amountCents = null): Refund
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Stripe secret key not configured');
        }

        $paymentIntentId = preg_replace('/[^a-zA-Z0-9_]/', '', $paymentIntentId) ?? '';
        if ($paymentIntentId === '') {
            throw new \InvalidArgumentException('Invalid payment intent');
        }

        $params = ['payment_intent' => $paymentIntentId];
        if ($amountCents !== null && $amountCents > 0) {
            $params['amount'] = $amountCents;
        }

        return Refund::create($params);
    }
}
