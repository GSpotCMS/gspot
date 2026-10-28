<?php

declare(strict_types=1);

namespace GSpot\Cache;

class CacheManager
{
    private string $cachePath;

    public function __construct(string $cachePath)
    {
        $this->cachePath = $cachePath;

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->path($key);
        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));

        if (isset($data['expires']) && $data['expires'] < time()) {
            unlink($file);
            return $default;
        }

        return $data['value'] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $file = $this->path($key);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time(),
        ];

        file_put_contents($file, serialize($data));
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function forget(string $key): void
    {
        $file = $this->path($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function clear(): void
    {
        $this->deleteDirectory($this->cachePath);
        mkdir($this->cachePath, 0755, true);
    }

    private function path(string $key): string
    {
        $hash = md5($key);
        return $this->cachePath . '/' . substr($hash, 0, 2) . '/' . $hash . '.cache';
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
