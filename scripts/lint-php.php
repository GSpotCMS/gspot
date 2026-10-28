<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dirs = [$root . '/system/src', $root . '/tests'];

$failed = false;

foreach ($dirs as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        passthru('php -l ' . escapeshellarg($path), $code);
        if ($code !== 0) {
            $failed = true;
        }
    }
}

exit($failed ? 1 : 0);
