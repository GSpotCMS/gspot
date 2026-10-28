<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use Symfony\Component\Yaml\Yaml;

final class AbandonedCartRegistry
{
    public function __construct(private readonly string $abandonedDir) {}

    /** @param list<array{slug: string, qty: int}> $cart */
    public function upsert(string $email, array $cart): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $cart === []) {
            return;
        }

        if (!is_dir($this->abandonedDir)) {
            mkdir($this->abandonedDir, 0755, true);
        }

        $id = $this->idForEmail($email);
        $file = PathGuard::resolveUnder($this->abandonedDir, $id . '.yaml');
        if ($file === null) {
            return;
        }

        $existing = is_file($file) ? Yaml::parseFile($file) : [];
        $reminded = is_array($existing) ? ($existing['reminded_at'] ?? '') : '';

        file_put_contents($file, Yaml::dump([
            'email' => $email,
            'cart' => $cart,
            'updated_at' => date('c'),
            'reminded_at' => $reminded,
        ], 2));
    }

    public function deleteByEmail(string $email): void
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }

        $file = PathGuard::resolveUnder($this->abandonedDir, $this->idForEmail($email) . '.yaml');
        if ($file !== null && is_file($file)) {
            unlink($file);
        }
    }

    /** @return list<array<string, mixed>> */
    public function dueForReminder(int $idleMinutes, int $limit = 20): array
    {
        if (!is_dir($this->abandonedDir)) {
            return [];
        }

        $cutoff = time() - max(5, $idleMinutes) * 60;
        $out = [];

        foreach (glob($this->abandonedDir . '/*.yaml') ?: [] as $file) {
            $data = Yaml::parseFile($file);
            if (!is_array($data)) {
                continue;
            }
            if ((string) ($data['reminded_at'] ?? '') !== '') {
                continue;
            }
            $updated = strtotime((string) ($data['updated_at'] ?? ''));
            if ($updated === false || $updated > $cutoff) {
                continue;
            }
            $data['_file'] = $file;
            $out[] = $data;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    public function markReminded(string $email): void
    {
        $email = strtolower(trim($email));
        $file = PathGuard::resolveUnder($this->abandonedDir, $this->idForEmail($email) . '.yaml');
        if ($file === null || !is_file($file)) {
            return;
        }

        $data = Yaml::parseFile($file);
        if (!is_array($data)) {
            return;
        }

        $data['reminded_at'] = date('c');
        file_put_contents($file, Yaml::dump($data, 2));
    }

    private function idForEmail(string $email): string
    {
        return substr(hash('sha256', strtolower($email)), 0, 32);
    }
}
