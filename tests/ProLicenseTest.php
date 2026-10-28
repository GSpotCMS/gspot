<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\UserPlugins\CommercePro\ProLicense;
use PHPUnit\Framework\TestCase;

final class ProLicenseTest extends TestCase
{
    private ?string $prior = null;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/user/plugins/commerce-pro/classes/ProLicense.php';
        $this->prior = getenv('COMMERCE_PRO_LICENSE') ?: null;
    }

    protected function tearDown(): void
    {
        if ($this->prior === null) {
            putenv('COMMERCE_PRO_LICENSE');
            unset($_ENV['COMMERCE_PRO_LICENSE']);
        } else {
            putenv('COMMERCE_PRO_LICENSE=' . $this->prior);
            $_ENV['COMMERCE_PRO_LICENSE'] = $this->prior;
        }
    }

    public function testValidKeyPrefix(): void
    {
        putenv('COMMERCE_PRO_LICENSE=GSPOT-PRO-TEST-DEV-KEY-001');
        $_ENV['COMMERCE_PRO_LICENSE'] = 'GSPOT-PRO-TEST-DEV-KEY-001';

        $this->assertTrue(ProLicense::isLicensed());
    }

    public function testInvalidKeyRejected(): void
    {
        putenv('COMMERCE_PRO_LICENSE=not-a-pro-key');
        $_ENV['COMMERCE_PRO_LICENSE'] = 'not-a-pro-key';

        $this->assertFalse(ProLicense::isLicensed());
    }
}
