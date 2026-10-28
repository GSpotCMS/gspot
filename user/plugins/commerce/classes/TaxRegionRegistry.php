<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use Symfony\Component\Yaml\Yaml;

final class TaxRegionRegistry
{
    /** @var array<string, mixed>|null */
    private ?array $config = null;

    public function __construct(private readonly string $regionsFile) {}

    public function defaultCountry(): string
    {
        $cfg = $this->load();

        return strtoupper((string) ($cfg['default_country'] ?? 'AU'));
    }

    public function rateFor(string $countryCode): float
    {
        $country = strtoupper(preg_replace('/[^A-Z]/', '', strtoupper($countryCode)) ?? '');
        if ($country === '') {
            $country = $this->defaultCountry();
        }

        $cfg = $this->load();
        $regions = $cfg['regions'] ?? [];
        if (is_array($regions) && array_key_exists($country, $regions)) {
            return max(0.0, (float) $regions[$country]);
        }

        return max(0.0, (float) ($cfg['default_rate'] ?? 0));
    }

    /** @return list<array{code: string, rate: float}> */
    public function countryOptions(): array
    {
        $cfg = $this->load();
        $regions = $cfg['regions'] ?? [];
        if (!is_array($regions)) {
            return [];
        }

        $out = [];
        foreach ($regions as $code => $rate) {
            $out[] = ['code' => strtoupper((string) $code), 'rate' => (float) $rate];
        }

        usort($out, fn ($a, $b) => strcmp($a['code'], $b['code']));

        return $out;
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        if (!is_file($this->regionsFile)) {
            $this->config = ['default_rate' => 0, 'regions' => []];

            return $this->config;
        }

        $parsed = Yaml::parseFile($this->regionsFile);
        $this->config = is_array($parsed) ? $parsed : ['default_rate' => 0, 'regions' => []];

        return $this->config;
    }
}
