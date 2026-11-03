<?php

declare(strict_types=1);

/**
 * MediTrack — OPcache Preloading Script (PHP 8.2 / 8.3)
 *
 * Pre-compiles and loads all core framework classes, repositories, services,
 * and middleware into shared memory on server startup, eliminating file resolution
 * and compilation overhead on every HTTP request.
 */

if (!function_exists('opcache_compile_file')) {
    return;
}

$baseDir = __DIR__;

// Helper to recursively compile all PHP files in given directories
$preloadDirectories = [
    $baseDir . '/src/Core',
    $baseDir . '/src/Enums',
    $baseDir . '/src/Policies',
    $baseDir . '/src/Validators',
    $baseDir . '/src/Middleware',
    $baseDir . '/src/Repositories',
    $baseDir . '/src/Services',
];

require_once $baseDir . '/vendor/autoload.php';
require_once $baseDir . '/bootstrap/helpers.php';

foreach ($preloadDirectories as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            try {
                @opcache_compile_file($file->getPathname());
            } catch (\Throwable $e) {
                // Ignore any files that cannot be standalone preloaded
            }
        }
    }
}

