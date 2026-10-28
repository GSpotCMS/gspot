<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use Symfony\Component\Yaml\Yaml;

final class ProductRegistry
{
    public function __construct(private readonly string $productsDir) {}

    /** @return list<array<string, mixed>> */
    public function all(bool $activeOnly = true): array
    {
        if (!is_dir($this->productsDir)) {
            return [];
        }

        $out = [];
        foreach (glob($this->productsDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $meta = $this->readMeta($dir);
            if ($meta === null) {
                continue;
            }
            if ($activeOnly && ($meta['active'] ?? true) === false) {
                continue;
            }
            $out[] = $meta;
        }

        usort($out, fn ($a, $b) => strcmp($a['title'] ?? '', $b['title'] ?? ''));

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function get(string $slug): ?array
    {
        $slug = PathGuard::sanitizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $dir = PathGuard::resolveUnder($this->productsDir, $slug);
        if ($dir === null || !is_dir($dir)) {
            return null;
        }

        return $this->readMeta($dir);
    }

    /** @param array<string, mixed> $meta */
    public function save(string $slug, array $meta): void
    {
        $slug = PathGuard::sanitizeSlug($slug);
        if ($slug === '') {
            throw new \InvalidArgumentException('Invalid product slug');
        }

        $dir = PathGuard::resolveUnder($this->productsDir, $slug);
        if ($dir === null) {
            throw new \InvalidArgumentException('Invalid product path');
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $product = [
            'slug' => $slug,
            'title' => (string) ($meta['title'] ?? $slug),
            'description' => (string) ($meta['description'] ?? ''),
            'price_cents' => (int) ($meta['price_cents'] ?? 0),
            'currency' => strtolower((string) ($meta['currency'] ?? 'aud')),
            'image' => (string) ($meta['image'] ?? ''),
            'active' => (bool) ($meta['active'] ?? true),
            'stripe_price_id' => (string) ($meta['stripe_price_id'] ?? ''),
            'stock' => $this->normalizeStock($meta['stock'] ?? null),
            'updated' => date('c'),
        ];

        file_put_contents($dir . '/product.yaml', Yaml::dump($product, 2));
    }

    public function availableStock(string $slug): ?int
    {
        $product = $this->get($slug);
        if ($product === null) {
            return null;
        }

        if (!array_key_exists('stock', $product) || $product['stock'] === null) {
            return null;
        }

        return max(0, (int) $product['stock']);
    }

    public function adjustStock(string $slug, int $delta): bool
    {
        $product = $this->get($slug);
        if ($product === null) {
            return false;
        }

        if (!array_key_exists('stock', $product) || $product['stock'] === null) {
            return true;
        }

        $stock = (int) $product['stock'];
        $next = $stock + $delta;
        if ($next < 0) {
            return false;
        }

        $product['stock'] = $next;
        $this->save($slug, $product);

        return true;
    }

    public function delete(string $slug): bool
    {
        $slug = PathGuard::sanitizeSlug($slug);
        if ($slug === '') {
            return false;
        }

        $dir = PathGuard::resolveUnder($this->productsDir, $slug);
        if ($dir === null || !is_dir($dir)) {
            return false;
        }

        unlink($dir . '/product.yaml');
        @rmdir($dir);

        return true;
    }

    /** @return array<string, mixed>|null */
    private function readMeta(string $dir): ?array
    {
        $file = $dir . '/product.yaml';
        if (!is_file($file)) {
            return null;
        }

        $meta = Yaml::parseFile($file);
        if (!is_array($meta)) {
            return null;
        }

        $meta['slug'] = $meta['slug'] ?? basename($dir);

        return $meta;
    }

    private function normalizeStock(mixed $stock): ?int
    {
        if ($stock === null || $stock === '') {
            return null;
        }

        return max(0, (int) $stock);
    }
}
