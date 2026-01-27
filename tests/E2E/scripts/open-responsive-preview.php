<?php

declare(strict_types=1);

/**
 * Opens the responsive preview HTML files in the default browser.
 * Works on macOS (open) and Linux (xdg-open).
 */

$previews = [
    'responsive' => __DIR__ . '/../../../output/responsive-dashboard.html',
    'holy-grail' => __DIR__ . '/../../../output/holy-grail.html',
    'blog' => __DIR__ . '/../../../output/responsive-blog.html',
    'progressive' => __DIR__ . '/../../../output/progressive-collapse.html',
    'dashboard' => __DIR__ . '/../../../output/dashboard.html',
    'centered' => __DIR__ . '/../../../output/centered.html',
];

$requested = $argv[1] ?? 'responsive';

if ($requested === 'all') {
    foreach ($previews as $name => $file) {
        if (file_exists($file)) {
            openInBrowser($file);
            echo "Opened: {$name}\n";
            usleep(500000); // Small delay between opens
        }
    }
    exit(0);
}

if ($requested === 'list') {
    echo "Available previews:\n";
    foreach ($previews as $name => $file) {
        $status = file_exists($file) ? '✓' : '✗';
        echo "  {$status} {$name}\n";
    }
    echo "\nUsage: php open-responsive-preview.php [name|all|list]\n";
    exit(0);
}

if (!isset($previews[$requested])) {
    echo "Unknown preview: {$requested}\n";
    echo 'Available: ' . implode(', ', array_keys($previews)) . "\n";
    exit(1);
}

$file = $previews[$requested];

if (!file_exists($file)) {
    echo "Preview file not found: {$file}\n";
    echo "Run 'composer test:e2e' first to generate the previews.\n";
    exit(1);
}

openInBrowser($file);

function openInBrowser(string $file): void
{
    $command = PHP_OS_FAMILY === 'Darwin' ? 'open' : 'xdg-open';
    $path = realpath($file);

    if ($path === false) {
        echo "Could not resolve path: {$file}\n";
        return;
    }

    echo "Opening {$path} in browser...\n";
    exec("{$command} " . escapeshellarg($path));
}
