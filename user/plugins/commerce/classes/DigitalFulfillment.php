<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;

final class DigitalFulfillment
{
    public function __construct(
        private readonly ProductRegistry $products,
        private readonly DownloadGrantRegistry $grants,
        private readonly string $siteBaseUrl,
        private readonly int $ttlDays,
        private readonly int $maxHits,
    ) {}

    /**
     * @return list<array{token: string, slug: string, title: string, url: string}>
     */
    public function grantForCart(string $orderId, string $cartJson): array
    {
        $rows = json_decode($cartJson, true);
        if (!is_array($rows)) {
            return [];
        }

        $base = rtrim($this->siteBaseUrl, '/');
        $out = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $slug = PathGuard::sanitizeSlug((string) ($row['slug'] ?? ''));
            if ($slug === '' || $this->products->resolveDownloadPath($slug) === null) {
                continue;
            }

            $product = $this->products->get($slug);
            $title = (string) ($product['title'] ?? $slug);
            $token = $this->grants->create($orderId, $slug, $this->maxHits, $this->ttlDays);
            $out[] = [
                'token' => $token,
                'slug' => $slug,
                'title' => $title,
                'url' => $base . '/commerce/download/' . $token,
            ];
        }

        return $out;
    }
}
