<?php

declare(strict_types=1);

namespace GSpot\Security;

/**
 * Hardened PHP session bootstrap for admin + CSRF.
 */
final class SessionGuard
{
    public const SESSION_NAME = 'gspot_admin';

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE || php_sapi_name() === 'cli') {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');

        if (self::shouldUseSecureCookies()) {
            ini_set('session.cookie_secure', '1');
        }

        session_name(self::SESSION_NAME);
        session_start();
    }

    public static function shouldUseSecureCookies(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

        return strtolower((string) $forwarded) === 'https';
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
