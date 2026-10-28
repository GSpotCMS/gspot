<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use Symfony\Component\Yaml\Yaml;

final class GiftCardRegistry
{
    public function __construct(private readonly string $giftCardsDir) {}

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        if (!is_dir($this->giftCardsDir)) {
            return [];
        }

        $out = [];
        foreach (glob($this->giftCardsDir . '/*.yaml') ?: [] as $file) {
            $data = Yaml::parseFile($file);
            if (is_array($data)) {
                $out[] = $data;
            }
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

        $file = PathGuard::resolveUnder($this->giftCardsDir, $code . '.yaml');
        if ($file === null || !is_file($file)) {
            return null;
        }

        $data = Yaml::parseFile($file);

        return is_array($data) ? $data : null;
    }

    /** @return array<string, mixed>|null */
    public function resolveForCheckout(string $code, string $currency): ?array
    {
        $card = $this->get($code);
        if ($card === null || ($card['active'] ?? true) === false) {
            return null;
        }

        $balance = (int) ($card['balance_cents'] ?? 0);
        if ($balance <= 0) {
            return null;
        }

        $cardCurrency = strtolower((string) ($card['currency'] ?? 'aud'));
        if ($cardCurrency !== strtolower($currency)) {
            return null;
        }

        return $card;
    }

    public function redeem(string $code, int $amountCents): bool
    {
        $card = $this->get($code);
        if ($card === null) {
            return false;
        }

        $balance = (int) ($card['balance_cents'] ?? 0);
        if ($amountCents <= 0 || $amountCents > $balance) {
            return false;
        }

        $card['balance_cents'] = $balance - $amountCents;
        $this->save($code, $card);

        return true;
    }

    /** @param array<string, mixed> $meta */
    public function save(string $code, array $meta): void
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            throw new \InvalidArgumentException('Invalid gift card code');
        }

        if (!is_dir($this->giftCardsDir)) {
            mkdir($this->giftCardsDir, 0755, true);
        }

        $file = PathGuard::resolveUnder($this->giftCardsDir, $code . '.yaml');
        if ($file === null) {
            throw new \InvalidArgumentException('Invalid gift card path');
        }

        $record = [
            'code' => $code,
            'balance_cents' => max(0, (int) ($meta['balance_cents'] ?? 0)),
            'currency' => strtolower((string) ($meta['currency'] ?? 'aud')),
            'active' => (bool) ($meta['active'] ?? true),
            'updated' => date('c'),
        ];

        file_put_contents($file, Yaml::dump($record, 2));
    }

    public function normalizeCode(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', strtoupper(trim($code))) ?? '');

        return substr($code, 0, 32);
    }
}
