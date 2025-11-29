<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Fixtures;

use PhpLayout\Component\ContextAwareComponentInterface;
use PhpLayout\Render\RenderContext;

/**
 * Component that verifies it receives the slot name for testing.
 */
final class SlotNameComponent implements ContextAwareComponentInterface
{
    public function render(array $properties, string $content = ''): string
    {
        return '<div>Non-context render</div>';
    }

    public function renderWithContext(RenderContext $context, string $content = ''): string
    {
        return '<div data-slot="' . htmlspecialchars($context->getSlotName()) . '">Slot: ' . $context->getSlotName() . '</div>';
    }
}
