<?php

declare(strict_types=1);

/**
 * Opens the preview HTML file in the default browser.
 * Works on macOS (open) and Linux (xdg-open).
 */

$file = __DIR__ . '/../../../output/dashboard.html';

if (!file_exists($file)) {
    echo "Preview file not found. Run 'composer test:e2e' first.\n";
    exit(1);
}

$command = PHP_OS_FAMILY === 'Darwin' ? 'open' : 'xdg-open';
$path = realpath($file);

if ($path === false) {
    echo "Could not resolve path: {$file}\n";
    exit(1);
}

echo "Opening {$path} in browser...\n";
exec("{$command} " . escapeshellarg($path));
