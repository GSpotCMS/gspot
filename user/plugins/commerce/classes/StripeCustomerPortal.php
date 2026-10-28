<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use Stripe\BillingPortal\Session;
use Stripe\Stripe;

final class StripeCustomerPortal
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $returnUrl,
    ) {
        if ($this->secretKey !== '') {
            Stripe::setApiKey($this->secretKey);
        }
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    public function createSession(string $customerId): Session
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Stripe secret key not configured');
        }

        $customerId = preg_replace('/[^a-zA-Z0-9_]/', '', $customerId) ?? '';
        if ($customerId === '') {
            throw new \InvalidArgumentException('Invalid Stripe customer id');
        }

        return Session::create([
            'customer' => $customerId,
            'return_url' => $this->returnUrl,
        ]);
    }
}
