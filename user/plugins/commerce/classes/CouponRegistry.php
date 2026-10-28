<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use Symfony\Component\Yaml\Yaml;

final class CouponRegistry
{
    public function __construct(private readonly string $couponsDir) {}

    /** @return list<array<string, mixed>> */
    public function all(bool $activeOnly = false): array
    {
        if (!is_dir($this->couponsDir)) {
            return [];
        }

        $out = [];
        foreach (glob($this->couponsDir . '/*.yaml') ?: [] as $file) {
            $data = Yaml::parseFile($file);
            if (!is_array($data)) {
                continue;
            }
            if ($activeOnly && ($data['active'] ?? true) === false) {
                continue;
            }
            $out[] = $data;
        }

        usort($out, fn ($a, $b) => strcmp($a['code'] ?? '', $b['code'] ?? ''));

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function get(string $code): ?array
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            return null;
        }

        $file = PathGuard::resolveUnder($this->couponsDir, $code . '.yaml');
        if ($file === null || !is_file($file)) {
            return null;
        }

        $data = Yaml::parseFile($file);

        return is_array($data) ? $data : null;
    }

    /** @return array<string, mixed>|null Validated coupon ready for checkout */
    public function resolveForCheckout(string $code): ?array
    {
        $coupon = $this->get($code);
        if ($coupon === null || ($coupon['active'] ?? true) === false) {
            return null;
        }

        $expires = (string) ($coupon['expires'] ?? '');
        if ($expires !== '' && strtotime($expires) !== false && time() > strtotime($expires)) {
            return null;
        }

        $maxUses = $coupon['max_uses'] ?? null;
        $uses = (int) ($coupon['uses'] ?? 0);
        if ($maxUses !== null && $maxUses !== '' && $uses >= (int) $maxUses) {
            return null;
        }

        return $coupon;
    }

    /** @param array<string, mixed> $meta */
    public function save(string $code, array $meta): void
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            throw new \InvalidArgumentException('Invalid coupon code');
        }

        if (!is_dir($this->couponsDir)) {
            mkdir($this->couponsDir, 0755, true);
        }

        $file = PathGuard::resolveUnder($this->couponsDir, $code . '.yaml');
        if ($file === null) {
            throw new \InvalidArgumentException('Invalid coupon path');
        }

        $type = strtolower((string) ($meta['type'] ?? 'percent'));
        if (!in_array($type, ['percent', 'fixed'], true)) {
            $type = 'percent';
        }

        $record = [
            'code' => $code,
            'type' => $type,
            'amount' => (int) ($meta['amount'] ?? 0),
            'active' => (bool) ($meta['active'] ?? true),
            'max_uses' => $meta['max_uses'] ?? null,
            'uses' => (int) ($meta['uses'] ?? 0),
            'expires' => (string) ($meta['expires'] ?? ''),
            'updated' => date('c'),
        ];

        file_put_contents($file, Yaml::dump($record, 2));
    }

    public function incrementUses(string $code): void
    {
        $coupon = $this->get($code);
        if ($coupon === null) {
            return;
        }

        $coupon['uses'] = (int) ($coupon['uses'] ?? 0) + 1;
        $this->save($code, $coupon);
    }

    public function delete(string $code): bool
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            return false;
        }

        $file = PathGuard::resolveUnder($this->couponsDir, $code . '.yaml');
        if ($file === null || !is_file($file)) {
            return false;
        }

        unlink($file);

        return true;
    }

    public function normalizeCode(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', strtoupper(trim($code))) ?? '');

        return substr($code, 0, 32);
    }
}
