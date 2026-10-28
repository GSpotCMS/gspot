<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use Symfony\Component\Yaml\Yaml;

final class DownloadGrantRegistry
{
    public function __construct(private readonly string $grantsDir) {}

    public function create(string $orderId, string $slug, int $maxHits, int $ttlDays): string
    {
        if (!is_dir($this->grantsDir)) {
            mkdir($this->grantsDir, 0755, true);
        }

        $token = bin2hex(random_bytes(16));
        $file = PathGuard::resolveUnder($this->grantsDir, $token . '.yaml');
        if ($file === null) {
            throw new \InvalidArgumentException('Invalid grant path');
        }

        $expires = $ttlDays > 0 ? date('c', time() + $ttlDays * 86400) : '';

        file_put_contents($file, Yaml::dump([
            'token' => $token,
            'order_id' => $this->sanitizeId($orderId),
            'slug' => PathGuard::sanitizeSlug($slug),
            'hits' => 0,
            'max_hits' => max(1, $maxHits),
            'expires' => $expires,
            'created' => date('c'),
        ], 2));

        return $token;
    }

    /** @return array<string, mixed>|null */
    public function get(string $token): ?array
    {
        $token = $this->sanitizeToken($token);
        if ($token === '') {
            return null;
        }

        $file = PathGuard::resolveUnder($this->grantsDir, $token . '.yaml');
        if ($file === null || !is_file($file)) {
            return null;
        }

        $data = Yaml::parseFile($file);

        return is_array($data) ? $data : null;
    }

    /** @return array<string, mixed>|null */
    public function redeem(string $token): ?array
    {
        $grant = $this->get($token);
        if ($grant === null) {
            return null;
        }

        $expires = (string) ($grant['expires'] ?? '');
        if ($expires !== '' && strtotime($expires) !== false && time() > strtotime($expires)) {
            return null;
        }

        $hits = (int) ($grant['hits'] ?? 0);
        $max = (int) ($grant['max_hits'] ?? 1);
        if ($hits >= $max) {
            return null;
        }

        $grant['hits'] = $hits + 1;
        $file = PathGuard::resolveUnder($this->grantsDir, $token . '.yaml');
        if ($file !== null) {
            file_put_contents($file, Yaml::dump($grant, 2));
        }

        return $grant;
    }

    private function sanitizeToken(string $token): string
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token)) ?? '';

        return substr($token, 0, 64);
    }

    private function sanitizeId(string $id): string
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id) ?? '';

        return substr($id, 0, 64);
    }
}
