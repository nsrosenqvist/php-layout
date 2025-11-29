<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Fixtures;

use PhpLayout\Component\ComponentInterface;

/**
 * Simple non-context-aware component for testing fallback behavior.
 */
final class SimpleComponent implements ComponentInterface
{
    public function render(array $properties, string $content = ''): string
    {
        $bg = $properties['background'] ?? '#fff';

        return '<div style="background: ' . $bg . '">Simple</div>';
    }
}
