<?php

declare(strict_types=1);

namespace PhpLayout\Generator;

use PhpLayout\Ast\Breakpoint;
use PhpLayout\Ast\Grid;
use PhpLayout\Ast\ResolvedLayout;
use PhpLayout\Ast\ResolvedSlot;
use PhpLayout\Component\ComponentRegistry;
use PhpLayout\Transformer\ResponsiveGridTransformer;

/**
 * Generates HTML structure from resolved layouts.
 */
final class HtmlGenerator
{
    private ResponsiveGridTransformer $transformer;

    public function __construct()
    {
        $this->transformer = new ResponsiveGridTransformer();
    }

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
            // Compute nesting relationships across all breakpoints
            $nestingInfo = $this->computeNestingInfo($layout->grid, $layout->breakpoints);

            $html[] = $this->generateGridHtml(
                $layout->grid,
                $layout->slots,
                $components,
                $containerClass,
                $nestingInfo,
            );
        }

        return implode("\n", $html);
    }

    /**
     * Compute nesting relationships for all breakpoints.
     *
     * Returns a structure that maps:
     * - Which slots nest INTO which targets at which breakpoints
     * - The direction (left = prepend, right = append)
     *
     * @param array<string, Breakpoint> $breakpoints
     * @return array{
     *     byTarget: array<string, list<array{source: string, breakpoint: string, direction: string}>>,
     *     bySource: array<string, list<array{target: string, breakpoint: string, direction: string}>>
     * }
     */
    private function computeNestingInfo(Grid $grid, array $breakpoints): array
    {
        $byTarget = [];
        $bySource = [];

        // Sort breakpoints descending (desktop-first)
        $sortedBreakpoints = $this->sortBreakpointsDescending($breakpoints);
        $breakpointNames = array_keys($sortedBreakpoints);

        foreach ($sortedBreakpoints as $name => $breakpoint) {
            // Build cumulative breakpoints list
            $breakpointsToApply = [];
            foreach ($breakpointNames as $bpName) {
                $breakpointsToApply[] = $bpName;
                if ($bpName === $name) {
                    break;
                }
            }

            $transformed = $this->transformer->transform($grid, $name, $breakpointsToApply);
            $relationships = $transformed->getNestedRelationships();

            foreach ($relationships as $source => $info) {
                $target = $info['target'];
                $direction = $info['direction'];

                // Track by target (for rendering nested content inside targets)
                if (!isset($byTarget[$target])) {
                    $byTarget[$target] = [];
                }

                // Only add if not already added for a larger breakpoint
                $alreadyNested = false;
                foreach ($byTarget[$target] as $existing) {
                    if ($existing['source'] === $source) {
                        $alreadyNested = true;
                        break;
                    }
                }

                if (!$alreadyNested) {
                    $byTarget[$target][] = [
                        'source' => $source,
                        'breakpoint' => $name,
                        'direction' => $direction,
                    ];
                }

                // Track by source (for knowing which slots are nested)
                if (!isset($bySource[$source])) {
                    $bySource[$source] = [];
                }
                $bySource[$source][] = [
                    'target' => $target,
                    'breakpoint' => $name,
                    'direction' => $direction,
                ];
            }
        }

        return ['byTarget' => $byTarget, 'bySource' => $bySource];
    }

    /**
     * Sort breakpoints by value descending (desktop-first).
     *
     * @param array<string, Breakpoint> $breakpoints
     * @return array<string, Breakpoint>
     */
    private function sortBreakpointsDescending(array $breakpoints): array
    {
        $sorted = $breakpoints;
        uasort($sorted, static function ($a, $b): int {
            $aVal = (int) filter_var($a->value, FILTER_SANITIZE_NUMBER_INT);
            $bVal = (int) filter_var($b->value, FILTER_SANITIZE_NUMBER_INT);
            return $bVal <=> $aVal;
        });
        return $sorted;
    }

    /**
     * Generate HTML for a grid.
     *
     * @param array<string, ResolvedSlot> $slots
     * @param array{
     *     byTarget: array<string, list<array{source: string, breakpoint: string, direction: string}>>,
     *     bySource: array<string, list<array{target: string, breakpoint: string, direction: string}>>
     * } $nestingInfo
     */
    private function generateGridHtml(
        Grid $grid,
        array $slots,
        ComponentRegistry $components,
        string $containerClass,
        array $nestingInfo,
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

            // Check if this slot receives nested content
            $receivesNestedContent = isset($nestingInfo['byTarget'][$name]) && $nestingInfo['byTarget'][$name] !== [];

            $lines[] = '  <div class="' . $this->escape($elementClass) . '">';

            // Get content that nests into this slot (prepended - direction: left)
            $prependedContent = $this->getNestedContent(
                $name,
                'left',
                $slots,
                $components,
                $containerClass,
                $nestingInfo,
            );

            // Add prepended nested content
            foreach ($prependedContent as $nestedHtml) {
                $lines[] = $nestedHtml;
            }

            // Render slot's own content - wrap in --own div if receiving nested content
            if ($receivesNestedContent) {
                $ownClass = $elementClass . '--own';
                $lines[] = '    <div class="' . $this->escape($ownClass) . '">';
            }

            if ($slot !== null) {
                $slotContent = $this->renderSlotContent($slot, $name, $components, $containerClass, $nestingInfo);
                if ($slotContent !== null) {
                    $indent = $receivesNestedContent ? '  ' : '';
                    $lines[] = $indent . $slotContent;
                }
            } else {
                $slotContent = $this->renderDefaultContent($name, $components);
                if ($slotContent !== null) {
                    $indent = $receivesNestedContent ? '      ' : '    ';
                    $lines[] = $indent . $slotContent;
                }
            }

            if ($receivesNestedContent) {
                $lines[] = '    </div>';
            }

            // Get content that nests into this slot (appended - direction: right)
            $appendedContent = $this->getNestedContent(
                $name,
                'right',
                $slots,
                $components,
                $containerClass,
                $nestingInfo,
            );

            // Add appended nested content
            foreach ($appendedContent as $nestedHtml) {
                $lines[] = $nestedHtml;
            }

            $lines[] = '  </div>';
        }

        if ($includeWrapper) {
            $lines[] = '</div>';
        }

        return implode("\n", $lines);
    }

    /**
     * Get nested content that should appear inside a target slot.
     *
     * @param array<string, ResolvedSlot> $slots
     * @param array{
     *     byTarget: array<string, list<array{source: string, breakpoint: string, direction: string}>>,
     *     bySource: array<string, list<array{target: string, breakpoint: string, direction: string}>>
     * } $nestingInfo
     * @return list<string>
     */
    private function getNestedContent(
        string $targetName,
        string $direction,
        array $slots,
        ComponentRegistry $components,
        string $containerClass,
        array $nestingInfo,
    ): array {
        $content = [];
        $nestedIntoTarget = $nestingInfo['byTarget'][$targetName] ?? [];

        foreach ($nestedIntoTarget as $nested) {
            if ($nested['direction'] !== $direction) {
                continue;
            }

            $sourceName = $nested['source'];
            $breakpoint = $nested['breakpoint'];
            $sourceSlot = $slots[$sourceName] ?? null;

            // Render the nested slot's content wrapped in a breakpoint-specific container
            $wrapperClass = $containerClass . '__' . $targetName . '--nested-' . $sourceName . '-' . $breakpoint;

            $nestedContent = '';
            if ($sourceSlot !== null) {
                if ($sourceSlot->hasComponent()) {
                    $componentName = $sourceSlot->getComponent();
                    \assert($componentName !== null);
                    $nestedContent = $components->render($componentName, $sourceSlot->properties, '', $sourceName);
                } elseif ($components->has($sourceName)) {
                    $nestedContent = $components->render($sourceName, $sourceSlot->properties, '', $sourceName);
                } elseif ($components->hasDefaultComponent()) {
                    $defaultComponent = $components->getDefaultComponent();
                    \assert($defaultComponent !== null);
                    $nestedContent = $components->render($defaultComponent, $sourceSlot->properties, '', $sourceName);
                }
            } elseif ($components->has($sourceName)) {
                $nestedContent = $components->render($sourceName, [], '', $sourceName);
            }

            if ($nestedContent !== '') {
                $content[] = '    <div class="' . $this->escape($wrapperClass) . '">' . $nestedContent . '</div>';
            }
        }

        return $content;
    }

    /**
     * Render a slot's own content.
     *
     * @param array{
     *     byTarget: array<string, list<array{source: string, breakpoint: string, direction: string}>>,
     *     bySource: array<string, list<array{target: string, breakpoint: string, direction: string}>>
     * } $nestingInfo
     */
    private function renderSlotContent(
        ResolvedSlot $slot,
        string $name,
        ComponentRegistry $components,
        string $containerClass,
        array $nestingInfo,
    ): ?string {
        $elementClass = $containerClass . '__' . $name;

        // If slot has nested grid, recurse
        if ($slot->grid !== null && $slot->hasChildren()) {
            $nestedHtml = $this->generateGridHtml(
                $slot->grid,
                $slot->children,
                $components,
                $elementClass,
                $nestingInfo,
                false,
            );
            return $this->indent($nestedHtml, 4);
        }

        if ($slot->hasComponent()) {
            $componentName = $slot->getComponent();
            \assert($componentName !== null);
            return '    ' . $components->render($componentName, $slot->properties, '', $name);
        }

        if ($slot->isContainer) {
            return '    <!-- slot: ' . $name . ' -->';
        }

        if ($components->has($name)) {
            return '    ' . $components->render($name, $slot->properties, '', $name);
        }

        if ($components->hasDefaultComponent()) {
            $defaultComponent = $components->getDefaultComponent();
            \assert($defaultComponent !== null);
            return '    ' . $components->render($defaultComponent, $slot->properties, '', $name);
        }

        return null;
    }

    /**
     * Render default content for a slot without definition.
     */
    private function renderDefaultContent(string $name, ComponentRegistry $components): ?string
    {
        if ($components->has($name)) {
            return $components->render($name, [], '', $name);
        }

        if ($components->hasDefaultComponent()) {
            $defaultComponent = $components->getDefaultComponent();
            \assert($defaultComponent !== null);
            return $components->render($defaultComponent, [], '', $name);
        }

        return null;
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
