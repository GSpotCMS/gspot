<?php

declare(strict_types=1);

namespace GSpot\Security;

/**
 * File-backed login attempt limits (per IP + username).
 */
final class LoginThrottle
{
    /** @var array<string, array{count: int, locked_until: int}> */
    private array $state = [];

    public function __construct(
        private readonly string $storePath,
        private readonly int $maxAttempts = 5,
        private readonly int $lockoutSeconds = 900,
    ) {
    }

    public function isLocked(string $ip, string $username): bool
    {
        return $this->secondsRemaining($ip, $username) > 0;
    }

    public function secondsRemaining(string $ip, string $username): int
    {
        $entry = $this->load()[$this->bucketKey($ip, $username)] ?? null;
        if ($entry === null) {
            return 0;
        }

        $now = time();
        if ($entry['locked_until'] > $now) {
            return $entry['locked_until'] - $now;
        }

        return 0;
    }

    public function recordFailure(string $ip, string $username): void
    {
        $data = $this->load();
        $key = $this->bucketKey($ip, $username);
        $now = time();
        $entry = $data[$key] ?? ['count' => 0, 'locked_until' => 0];

        if ($entry['locked_until'] > $now) {
            return;
        }

        if ($entry['locked_until'] > 0 && $entry['locked_until'] <= $now) {
            $entry['count'] = 0;
            $entry['locked_until'] = 0;
        }

        $entry['count']++;
        if ($entry['count'] >= $this->maxAttempts) {
            $entry['locked_until'] = $now + $this->lockoutSeconds;
            $entry['count'] = 0;
        }

        $data[$key] = $entry;
        $this->save($data);
    }

    public function clear(string $ip, string $username): void
    {
        $data = $this->load();
        unset($data[$this->bucketKey($ip, $username)]);
        $this->save($data);
    }

    private function bucketKey(string $ip, string $username): string
    {
        return hash('sha256', strtolower(trim($username)) . '|' . trim($ip));
    }

    /** @return array<string, array{count: int, locked_until: int}> */
    private function load(): array
    {
        if ($this->state !== []) {
            return $this->state;
        }

        if (!is_file($this->storePath)) {
            return [];
        }

        $raw = file_get_contents($this->storePath);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $this->state = $decoded;

        return $this->state;
    }

    /** @param array<string, array{count: int, locked_until: int}> $data */
    private function save(array $data): void
    {
        $this->state = $data;
        $dir = dirname($this->storePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $fp = fopen($this->storePath, 'c+');
        if ($fp === false) {
            return;
        }

        try {
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, $json);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
    }
}
