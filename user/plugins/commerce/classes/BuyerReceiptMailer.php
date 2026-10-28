<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

final class BuyerReceiptMailer
{
    public function __construct(
        private readonly string $siteTitle,
        private readonly bool $enabled = true,
    ) {}

    /** @param array<string, mixed> $order */
    public function send(string $orderId, array $order): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $to = (string) ($order['email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = $this->siteTitle . ' — your order receipt';
        $body = $this->formatBody($orderId, $order);
        $headers = "Content-Type: text/plain; charset=UTF-8\r\n";

        return @mail($to, $subject, $body, $headers);
    }

    /** @param array<string, mixed> $order */
    private function formatBody(string $orderId, array $order): string
    {
        $cur = strtoupper((string) ($order['currency'] ?? 'aud'));
        $total = number_format(((int) ($order['amount_total'] ?? 0)) / 100, 2);
        $tax = (string) ($order['tax_country'] ?? '');
        $taxLine = $tax !== '' ? "Tax region: {$tax}\n" : '';

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

        $downloads = '';
        foreach ($order['downloads'] ?? [] as $dl) {
            if (!is_array($dl)) {
                continue;
            }
            $downloads .= '  - ' . ($dl['title'] ?? 'File') . ': ' . ($dl['url'] ?? '') . "\n";
        }
        $downloadBlock = $downloads !== '' ? "Downloads:\n{$downloads}\n" : '';

        return <<<TEXT
Thanks for your order!

Order: {$orderId}
Total: {$cur} {$total}
{$taxLine}
Items:
{$lines}
{$downloadBlock}
— {$this->siteTitle}
TEXT;
    }
}
