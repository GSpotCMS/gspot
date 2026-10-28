<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use Symfony\Component\Yaml\Yaml;

final class OrderRegistry
{
    public function __construct(private readonly string $ordersDir) {}

    /** @param array<string, mixed> $order */
    public function save(string $id, array $order): void
    {
        $id = $this->sanitizeId($id);
        if ($id === '') {
            throw new \InvalidArgumentException('Invalid order id');
        }

        if (!is_dir($this->ordersDir)) {
            mkdir($this->ordersDir, 0755, true);
        }

        $file = PathGuard::resolveUnder($this->ordersDir, $id . '.yaml');
        if ($file === null) {
            throw new \InvalidArgumentException('Invalid order path');
        }

        $order['id'] = $id;
        $order['updated'] = date('c');

        file_put_contents($file, Yaml::dump($order, 3));
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        $id = $this->sanitizeId($id);
        if ($id === '') {
            return null;
        }

        $file = PathGuard::resolveUnder($this->ordersDir, $id . '.yaml');
        if ($file === null || !is_file($file)) {
            return null;
        }

        $data = Yaml::parseFile($file);

        return is_array($data) ? $data : null;
    }

    /** @return list<array<string, mixed>> */
    public function all(int $limit = 50): array
    {
        if (!is_dir($this->ordersDir)) {
            return [];
        }

        $files = glob($this->ordersDir . '/*.yaml') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $out = [];
        foreach (array_slice($files, 0, $limit) as $file) {
            $data = Yaml::parseFile($file);
            if (is_array($data)) {
                $out[] = $data;
            }
        }

        return $out;
    }

    public function count(): int
    {
        if (!is_dir($this->ordersDir)) {
            return 0;
        }

        return count(glob($this->ordersDir . '/*.yaml') ?: []);
    }

    private function sanitizeId(string $id): string
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id) ?? '';

        return substr($id, 0, 64);
    }
}
