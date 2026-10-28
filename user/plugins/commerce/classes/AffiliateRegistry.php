<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use Symfony\Component\Yaml\Yaml;

final class AffiliateRegistry
{
    public function __construct(private readonly string $affiliatesDir) {}

    /** @return array<string, mixed>|null */
    public function get(string $code): ?array
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            return null;
        }

        $file = PathGuard::resolveUnder($this->affiliatesDir, $code . '.yaml');
        if ($file === null || !is_file($file)) {
            return null;
        }

        $data = Yaml::parseFile($file);

        return is_array($data) ? $data : null;
    }

    /** @return array<string, mixed>|null */
    public function resolveActive(string $code): ?array
    {
        $affiliate = $this->get($code);
        if ($affiliate === null || ($affiliate['active'] ?? true) === false) {
            return null;
        }

        return $affiliate;
    }

    public function commissionCents(int $orderTotalCents, array $affiliate): int
    {
        $pct = min(100, max(0, (float) ($affiliate['commission_percent'] ?? 0)));

        return (int) round($orderTotalCents * $pct / 100);
    }

    public function recordSale(string $code, int $orderTotalCents, int $commissionCents): void
    {
        $affiliate = $this->get($code);
        if ($affiliate === null) {
            return;
        }

        $affiliate['orders'] = (int) ($affiliate['orders'] ?? 0) + 1;
        $affiliate['revenue_cents'] = (int) ($affiliate['revenue_cents'] ?? 0) + $orderTotalCents;
        $affiliate['commission_owed_cents'] = (int) ($affiliate['commission_owed_cents'] ?? 0) + $commissionCents;
        $affiliate['updated'] = date('c');
        $this->save($code, $affiliate);
    }

    /** @param array<string, mixed> $meta */
    public function save(string $code, array $meta): void
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            throw new \InvalidArgumentException('Invalid affiliate code');
        }

        if (!is_dir($this->affiliatesDir)) {
            mkdir($this->affiliatesDir, 0755, true);
        }

        $file = PathGuard::resolveUnder($this->affiliatesDir, $code . '.yaml');
        if ($file === null) {
            throw new \InvalidArgumentException('Invalid affiliate path');
        }

        $record = [
            'code' => $code,
            'name' => (string) ($meta['name'] ?? $code),
            'commission_percent' => (float) ($meta['commission_percent'] ?? 10),
            'active' => (bool) ($meta['active'] ?? true),
            'orders' => (int) ($meta['orders'] ?? 0),
            'revenue_cents' => (int) ($meta['revenue_cents'] ?? 0),
            'commission_owed_cents' => (int) ($meta['commission_owed_cents'] ?? 0),
            'updated' => date('c'),
        ];

        file_put_contents($file, Yaml::dump($record, 2));
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        if (!is_dir($this->affiliatesDir)) {
            return [];
        }

        $out = [];
        foreach (glob($this->affiliatesDir . '/*.yaml') ?: [] as $file) {
            $data = Yaml::parseFile($file);
            if (is_array($data)) {
                $out[] = $data;
            }
        }

        usort($out, fn ($a, $b) => strcmp($a['code'] ?? '', $b['code'] ?? ''));

        return $out;
    }

    public function normalizeCode(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', strtoupper(trim($code))) ?? '');

        return substr($code, 0, 32);
    }
}
