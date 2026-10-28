<?php

declare(strict_types=1);

namespace GSpot\Security;

final class Csrf
{
    private const SESSION_KEY = 'gspot_csrf';

    public static function ensureSession(): void
    {
        SessionGuard::start();
    }

    public static function token(): string
    {
        self::ensureSession();
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $submitted): bool
    {
        self::ensureSession();
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }
        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals((string) $_SESSION[self::SESSION_KEY], $submitted);
    }

    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }
}
