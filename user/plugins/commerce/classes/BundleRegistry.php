<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use Symfony\Component\Yaml\Yaml;

final class BundleRegistry
{
    public function __construct(
        private readonly string $bundlesDir,
        private readonly ProductRegistry $products,
    ) {}

    /** @return list<array<string, mixed>> */
    public function all(bool $activeOnly = true): array
    {
        if (!is_dir($this->bundlesDir)) {
            return [];
        }

        $out = [];
        foreach (glob($this->bundlesDir . '/*.yaml') ?: [] as $file) {
            $data = Yaml::parseFile($file);
            if (!is_array($data)) {
                continue;
            }
            if ($activeOnly && ($data['active'] ?? true) === false) {
                continue;
            }
            $data['slug'] = $data['slug'] ?? basename($file, '.yaml');
            $out[] = $data;
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

        $file = PathGuard::resolveUnder($this->bundlesDir, $slug . '.yaml');
        if ($file === null || !is_file($file)) {
            return null;
        }

        $data = Yaml::parseFile($file);

        return is_array($data) ? $data : null;
    }

    /** @return list<array{slug: string, qty: int}> */
    public function lineItems(string $slug): array
    {
        $bundle = $this->get($slug);
        if ($bundle === null) {
            return [];
        }

        $out = [];
        foreach ($bundle['items'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $itemSlug = PathGuard::sanitizeSlug((string) ($row['slug'] ?? ''));
            $qty = max(1, (int) ($row['qty'] ?? 1));
            if ($itemSlug === '' || $this->products->get($itemSlug) === null) {
                continue;
            }
            $out[] = ['slug' => $itemSlug, 'qty' => $qty];
        }

        return $out;
    }
}
