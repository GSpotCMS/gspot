<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\CommercePro;

use GSpot\Security\PathGuard;
use Symfony\Component\Yaml\Yaml;

final class VendorRegistry
{
    public function __construct(private readonly string $vendorsDir) {}

    /** @return list<array<string, mixed>> */
    public function all(bool $activeOnly = true): array
    {
        if (!is_dir($this->vendorsDir)) {
            return [];
        }

        $out = [];
        foreach (glob($this->vendorsDir . '/*.yaml') ?: [] as $file) {
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

        usort($out, fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function get(string $slug): ?array
    {
        $slug = PathGuard::sanitizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $file = PathGuard::resolveUnder($this->vendorsDir, $slug . '.yaml');
        if ($file === null || !is_file($file)) {
            return null;
        }

        $data = Yaml::parseFile($file);

        return is_array($data) ? $data : null;
    }
}
