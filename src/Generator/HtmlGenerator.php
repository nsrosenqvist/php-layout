<?php

declare(strict_types=1);

namespace PhpLayout\Generator;

use PhpLayout\Ast\Grid;
use PhpLayout\Ast\ResolvedLayout;
use PhpLayout\Ast\ResolvedSlot;
use PhpLayout\Component\ComponentRegistry;

/**
 * Generates HTML structure from resolved layouts.
 */
final class HtmlGenerator
{
    /**
     * Generate HTML using a ComponentRegistry.
     */
    public function generate(
        ResolvedLayout $layout,
        ComponentRegistry $components,
        string $containerClass = 'layout',
    ): string {
        $html = [];

        if ($layout->grid !== null) {
            $html[] = $this->generateGridHtml(
                $layout->grid,
                $layout->slots,
                $components,
                $containerClass,
            );
        }

        return implode("\n", $html);
    }

    /**
     * Generate HTML for a grid.
     *
     * @param array<string, ResolvedSlot> $slots
     */
    private function generateGridHtml(
        Grid $grid,
        array $slots,
        ComponentRegistry $components,
        string $containerClass,
        bool $includeWrapper = true,
    ): string {
        $lines = [];

        if ($includeWrapper) {
            $lines[] = '<div class="' . $this->escape($containerClass) . '">';
        }

        // Get unique slot names from grid
        $slotNames = $grid->getSlotNames();

        foreach ($slotNames as $name) {
            $slot = $slots[$name] ?? null;
            $elementClass = $containerClass . '__' . $name;

            $lines[] = '  <div class="' . $this->escape($elementClass) . '">';

            if ($slot !== null) {
                // If slot has nested grid, recurse
                if ($slot->grid !== null && $slot->hasChildren()) {
                    $nestedHtml = $this->generateGridHtml(
                        $slot->grid,
                        $slot->children,
                        $components,
                        $elementClass,
                        false, // Don't include wrapper, parent already created it
                    );
                    $lines[] = $this->indent($nestedHtml, 4);
                } elseif ($slot->hasComponent()) {
                    $componentName = $slot->getComponent();
                    \assert($componentName !== null);
                    $content = $components->render($componentName, $slot->properties);
                    $lines[] = '    ' . $content;
                } elseif ($slot->isContainer) {
                    $lines[] = '    <!-- slot: ' . $name . ' -->';
                } else {
                    // Try to render by slot name
                    if ($components->has($name)) {
                        $content = $components->render($name, $slot->properties);
                        $lines[] = '    ' . $content;
                    }
                }
            } else {
                // No slot definition, try component by name
                if ($components->has($name)) {
                    $content = $components->render($name);
                    $lines[] = '    ' . $content;
                }
            }

            $lines[] = '  </div>';
        }

        if ($includeWrapper) {
            $lines[] = '</div>';
        }

        return implode("\n", $lines);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function indent(string $text, int $spaces): string
    {
        $indent = str_repeat(' ', $spaces);
        $lines = explode("\n", $text);
        return implode("\n", array_map(fn ($line) => $line !== '' ? $indent . $line : $line, $lines));
    }
}
