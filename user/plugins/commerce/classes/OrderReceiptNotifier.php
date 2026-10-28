<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

final class OrderReceiptNotifier
{
    public function __construct(
        private readonly string $receiptsDir,
        private readonly string $notifyEmail = '',
        private readonly string $siteTitle = 'GSpot Shop',
    ) {}

    /** @param array<string, mixed> $order */
    public function notify(string $orderId, array $order): void
    {
        if (!is_dir($this->receiptsDir)) {
            mkdir($this->receiptsDir, 0755, true);
        }

        $body = $this->formatBody($orderId, $order);
        $file = $this->receiptsDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $orderId) . '.txt';
        file_put_contents($file, $body);

        $to = trim($this->notifyEmail);
        if ($to === '') {
            return;
        }

        $customer = (string) ($order['email'] ?? '');
        $subject = $this->siteTitle . ' — order ' . substr($orderId, 0, 24);
        $headers = 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        if ($customer !== '' && filter_var($customer, FILTER_VALIDATE_EMAIL)) {
            $headers .= 'Reply-To: ' . $customer . "\r\n";
        }

        @mail($to, $subject, $body, $headers);
    }

    /** @param array<string, mixed> $order */
    private function formatBody(string $orderId, array $order): string
    {
        $cur = strtoupper((string) ($order['currency'] ?? 'aud'));
        $total = number_format(((int) ($order['amount_total'] ?? 0)) / 100, 2);
        $email = (string) ($order['email'] ?? '—');
        $coupon = (string) ($order['coupon'] ?? '');
        $lines = '';

        foreach ($order['line_items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $desc = (string) ($item['description'] ?? 'Item');
            $qty = (int) ($item['quantity'] ?? 1);
            $sub = number_format(((int) ($item['amount_subtotal'] ?? 0)) / 100, 2);
            $lines .= "  - {$desc} x{$qty} — {$cur} {$sub}\n";
        }

        $couponLine = $coupon !== '' ? "Coupon: {$coupon}\n" : '';

        return <<<TEXT
GSpot Commerce receipt
Order: {$orderId}
Customer: {$email}
Total: {$cur} {$total}
{$couponLine}
Items:
{$lines}
Filed at: {$order['created']}
TEXT;
    }
}
