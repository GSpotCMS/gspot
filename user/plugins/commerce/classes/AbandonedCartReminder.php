<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

final class AbandonedCartReminder
{
    public function __construct(
        private readonly AbandonedCartRegistry $registry,
        private readonly ProductRegistry $products,
        private readonly string $shopUrl,
        private readonly string $siteTitle,
    ) {}

    /** @return array{sent: int, skipped: int} */
    public function run(int $idleMinutes = 60): array
    {
        $sent = 0;
        $skipped = 0;

        foreach ($this->registry->dueForReminder($idleMinutes) as $row) {
            $email = (string) ($row['email'] ?? '');
            if ($email === '') {
                $skipped++;
                continue;
            }

            $body = $this->buildMessage($row);
            $subject = $this->siteTitle . ' — still thinking it over?';
            $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
            if (@mail($email, $subject, $body, $headers)) {
                $this->registry->markReminded($email);
                $sent++;
            } else {
                $skipped++;
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /** @param array<string, mixed> $row */
    private function buildMessage(array $row): string
    {
        $lines = '';
        foreach ($row['cart'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug = (string) ($item['slug'] ?? '');
            $qty = (int) ($item['qty'] ?? 1);
            $product = $this->products->get($slug);
            $title = (string) ($product['title'] ?? $slug);
            $lines .= "  - {$title} x{$qty}\n";
        }

        return <<<TEXT
You left a few things in your cart at {$this->siteTitle}:

{$lines}
Pick up where you left off:
{$this->shopUrl}/commerce/cart

Find the Spot. Hit it. Ship it.
TEXT;
    }
}
