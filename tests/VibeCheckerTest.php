<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\Vibe\VibeChecker;
use PHPUnit\Framework\TestCase;

final class VibeCheckerTest extends TestCase
{
    public function testCorporateCopyLowersScore(): void
    {
        $result = (new VibeChecker())->analyze(
            "# Quarterly synergy\n\nWe leverage stakeholders for best-in-class outcomes.",
            ['vibe' => 'synthwave', 'bpm' => 140, 'energy' => 'high'],
        );

        $this->assertLessThan(70, $result['score']);
        $this->assertFalse($result['pass']);
        $this->assertNotEmpty($result['flags']);
    }

    public function testGroundswellCopyPasses(): void
    {
        $result = (new VibeChecker())->analyze(
            "# Find the Spot\n\nNeon groundswell. Ship it before the drop.\n\n[mud-pattern slug=\"hero-synthwave\"]",
            ['vibe' => 'synthwave', 'bpm' => 174, 'energy' => 'high'],
        );

        $this->assertGreaterThanOrEqual(60, $result['score']);
        $this->assertTrue($result['pass']);
    }
}
