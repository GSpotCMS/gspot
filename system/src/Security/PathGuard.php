<?php

declare(strict_types=1);

namespace GSpot\Security;

/**
 * Keep file operations under user/ and reject traversal in names.
 */
final class PathGuard
{
    public static function sanitizeSlug(string $slug): string
    {
        if (str_contains($slug, '..') || str_contains($slug, '/') || str_contains($slug, '\\')) {
            return '';
        }
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return substr($slug, 0, 64);
    }

    /** Plugin/theme directory names (kebab-case). */
    public static function sanitizeName(string $name): string
    {
        if (str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\')) {
            return '';
        }
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9-]+/', '-', $name) ?? '';
        $name = trim($name, '-');

        return substr($name, 0, 48);
    }

    /** Single-segment filenames for media and backups. */
    public static function sanitizeFilename(string $filename): string
    {
        if (str_contains($filename, '..')) {
            return '';
        }
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filename) ?? '';

        return substr($filename, 0, 128);
    }

    /**
     * Resolve a path that must live under $baseDir. Returns null if unsafe or missing parent.
     */
    public static function resolveUnder(string $baseDir, string ...$segments): ?string
    {
        $base = realpath($baseDir);
        if ($base === false) {
            if (!is_dir($baseDir)) {
                return null;
            }
            $base = realpath($baseDir) ?: $baseDir;
        }

        $path = $base;
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, "\0")) {
                return null;
            }
            if (str_contains($segment, '/') || str_contains($segment, '\\')) {
                return null;
            }
            $path = $path . DIRECTORY_SEPARATOR . $segment;
        }

        $resolved = realpath($path);
        if ($resolved !== false) {
            $path = $resolved;
        }

        $baseNorm = rtrim(str_replace('\\', '/', $base), '/');
        $pathNorm = str_replace('\\', '/', $path);

        if (!str_starts_with($pathNorm, $baseNorm . '/') && $pathNorm !== $baseNorm) {
            return null;
        }

        return $path;
    }
}
