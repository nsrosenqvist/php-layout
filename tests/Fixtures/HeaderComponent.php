<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Fixtures;

use PhpLayout\Component\ContextAwareComponentInterface;
use PhpLayout\Render\RenderContext;

/**
 * Context-aware header component that uses page data for testing.
 */
final class HeaderComponent implements ContextAwareComponentInterface
{
    public function render(array $properties, string $content = ''): string
    {
        // Fallback for non-context rendering
        return '<header>Default Header</header>';
    }

    public function renderWithContext(RenderContext $context, string $content = ''): string
    {
        $data = $context->getData();
        $title = $data instanceof PageData ? $data->title : 'Untitled';
        $bg = $context->getProperty('background', '#333');

        return '<header style="background: ' . $bg . '"><h1>' . htmlspecialchars($title) . '</h1></header>';
    }
}
