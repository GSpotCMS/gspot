<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\Security\LoginThrottle;
use PHPUnit\Framework\TestCase;

final class LoginThrottleTest extends TestCase
{
    private string $store;

    protected function setUp(): void
    {
        $this->store = sys_get_temp_dir() . '/gspot-throttle-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->store)) {
            unlink($this->store);
        }
    }

    public function testLocksAfterMaxAttempts(): void
    {
        $throttle = new LoginThrottle($this->store, 3, 60);

        $this->assertFalse($throttle->isLocked('127.0.0.1', 'admin'));
        $throttle->recordFailure('127.0.0.1', 'admin');
        $throttle->recordFailure('127.0.0.1', 'admin');
        $this->assertFalse($throttle->isLocked('127.0.0.1', 'admin'));

        $throttle->recordFailure('127.0.0.1', 'admin');
        $this->assertTrue($throttle->isLocked('127.0.0.1', 'admin'));
        $this->assertGreaterThan(0, $throttle->secondsRemaining('127.0.0.1', 'admin'));
    }

    public function testClearRemovesLock(): void
    {
        $throttle = new LoginThrottle($this->store, 1, 60);
        $throttle->recordFailure('10.0.0.1', 'damo');
        $this->assertTrue($throttle->isLocked('10.0.0.1', 'damo'));

        $throttle->clear('10.0.0.1', 'damo');
        $this->assertFalse($throttle->isLocked('10.0.0.1', 'damo'));
    }
}
