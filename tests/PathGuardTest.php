<?php

declare(strict_types=1);

namespace GSpot\Tests;

use GSpot\Security\PathGuard;
use PHPUnit\Framework\TestCase;

final class PathGuardTest extends TestCase
{
    public function testSanitizeSlugStripsTraversal(): void
    {
        $this->assertSame('', PathGuard::sanitizeSlug('../../evil'));
        $this->assertSame('about-us', PathGuard::sanitizeSlug(' About Us! '));
        $this->assertSame('evil-page', PathGuard::sanitizeSlug('evil-page'));
        $this->assertSame('', PathGuard::sanitizeSlug('../../../'));
    }

    public function testSanitizeNameForPlugins(): void
    {
        $this->assertSame('my-plugin', PathGuard::sanitizeName('My_Plugin'));
        $this->assertSame('', PathGuard::sanitizeName('../hack'));
    }

    public function testSanitizeFilenameBasenameOnly(): void
    {
        $this->assertSame('', PathGuard::sanitizeFilename('../../etc/passwd'));
        $this->assertSame('photo.png', PathGuard::sanitizeFilename('photo.png'));
        $this->assertSame('backup-2026.zip', PathGuard::sanitizeFilename('backup-2026.zip'));
    }

    public function testResolveUnderRejectsEscape(): void
    {
        $base = sys_get_temp_dir() . '/gspot-pathguard-' . uniqid('', true);
        mkdir($base . '/user/plugins', 0755, true);

        $ok = PathGuard::resolveUnder($base . '/user/plugins', 'forumz');
        $this->assertNotNull($ok);

        $bad = PathGuard::resolveUnder($base . '/user/plugins', '..', 'config');
        $this->assertNull($bad);

        $this->removeDir($base);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
