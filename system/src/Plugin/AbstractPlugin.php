<?php

declare(strict_types=1);

namespace GSpot\Plugin;

use GSpot\Application;

abstract class AbstractPlugin
{
    protected Application $app;
    protected array $config;

    public function __construct(Application $app, array $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    public function name(): string
    {
        return $this->config['name'] ?? 'unknown';
    }

    public function version(): string
    {
        return $this->config['version'] ?? '0.0.0';
    }

    public function config(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? $default;
    }
}
