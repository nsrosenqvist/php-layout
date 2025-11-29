<?php

declare(strict_types=1);

namespace PhpLayout\Component;

use PhpLayout\Render\RenderContext;

/**
 * Interface for components that need access to the render context.
 *
 * Implement this interface when your component needs to access typed page data
 * (e.g., title, metadata) in addition to slot properties.
 *
 * Components implementing only ComponentInterface will continue to work but
 * won't receive the render context.
 */
interface ContextAwareComponentInterface extends ComponentInterface
{
    /**
     * Render the component with full render context.
     *
     * @param RenderContext<object> $context The render context with typed data and slot properties
     * @param string $content Optional inner content
     * @return string The rendered HTML
     */
    public function renderWithContext(RenderContext $context, string $content = ''): string;
}
