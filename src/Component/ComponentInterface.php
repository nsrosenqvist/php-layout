<?php

declare(strict_types=1);

namespace PhpLayout\Component;

/**
 * Interface for components that can be rendered within layouts.
 */
interface ComponentInterface
{
    /**
     * Render the component with the given properties and content.
     *
     * @param array<string, string> $properties
     */
    public function render(array $properties, string $content = ''): string;
}
