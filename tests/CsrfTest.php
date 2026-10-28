<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\Security\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['gspot_csrf']);
    }

    public function testTokenStableAndValidates(): void
    {
        $a = Csrf::token();
        $b = Csrf::token();
        $this->assertSame($a, $b);
        $this->assertTrue(Csrf::validate($a));
        $this->assertFalse(Csrf::validate('not-the-token'));
    }

    public function testFieldContainsHiddenInput(): void
    {
        $field = Csrf::field();
        $this->assertStringContainsString('name="_csrf"', $field);
        $this->assertStringContainsString(Csrf::token(), $field);
    }
}
