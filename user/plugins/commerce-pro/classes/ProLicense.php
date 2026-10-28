<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\CommercePro;

use GSpot\Application;

final class ProLicense
{
    public static function isLicensed(?Application $app = null): bool
    {
        if ($app !== null && !$app->plugins()->isLoaded('commerce-pro')) {
            return false;
        }

        $key = self::readKey();
        if ($key === '') {
            return false;
        }

        return str_starts_with(strtoupper($key), 'GSPOT-PRO-') && strlen($key) >= 20;
    }

    public static function readKey(): string
    {
        $env = (string) (getenv('COMMERCE_PRO_LICENSE') ?: ($_ENV['COMMERCE_PRO_LICENSE'] ?? ''));
        if (trim($env) !== '') {
            return trim($env);
        }

        $file = dirname(__DIR__) . '/license.key';
        if (is_readable($file)) {
            return trim((string) file_get_contents($file));
        }

        return '';
    }
}
