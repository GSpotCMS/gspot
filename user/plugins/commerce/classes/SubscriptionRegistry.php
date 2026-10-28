<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Security\PathGuard;
use Symfony\Component\Yaml\Yaml;

final class SubscriptionRegistry
{
    public function __construct(private readonly string $subscriptionsDir) {}

    /** @param array<string, mixed> $context */
    public function upsert(string $subscriptionId, string $status, array $context = []): void
    {
        $id = $this->sanitizeId($subscriptionId);
        if ($id === '') {
            return;
        }

        if (!is_dir($this->subscriptionsDir)) {
            mkdir($this->subscriptionsDir, 0755, true);
        }

        $file = PathGuard::resolveUnder($this->subscriptionsDir, $id . '.yaml');
        if ($file === null) {
            return;
        }

        $existing = is_file($file) ? Yaml::parseFile($file) : [];
        $record = is_array($existing) ? $existing : [];

        $record['id'] = $id;
        $record['status'] = $status;
        $record['email'] = (string) ($context['email'] ?? $record['email'] ?? '');
        $record['checkout_session'] = (string) ($context['checkout_session'] ?? $record['checkout_session'] ?? '');
        $record['updated'] = date('c');
        if (!isset($record['created'])) {
            $record['created'] = date('c');
        }
        if (($context['last_invoice_status'] ?? '') !== '') {
            $record['last_invoice_status'] = (string) $context['last_invoice_status'];
        }

        file_put_contents($file, Yaml::dump($record, 3));
    }

    /** @return list<array<string, mixed>> */
    public function all(int $limit = 50): array
    {
        if (!is_dir($this->subscriptionsDir)) {
            return [];
        }

        $files = glob($this->subscriptionsDir . '/*.yaml') ?: [];
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

    private function sanitizeId(string $id): string
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id) ?? '';

        return substr($id, 0, 64);
    }
}
