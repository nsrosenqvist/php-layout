<?php

declare(strict_types=1);

namespace PhpLayout\Generator;

use PhpLayout\Ast\Grid;
use PhpLayout\Ast\GridRow;
use PhpLayout\Ast\ResolvedLayout;
use PhpLayout\Ast\ResolvedSlot;
use PhpLayout\Transformer\ResponsiveGridTransformer;
use PhpLayout\Transformer\TransformedGrid;

/**
 * Generates CSS Grid styles from resolved layouts.
 */
final class CssGenerator
{
    private ResponsiveGridTransformer $transformer;

    public function __construct()
    {
        $this->transformer = new ResponsiveGridTransformer();
    }

    /**
     * Generate CSS for a resolved layout.
     */
    public function generate(ResolvedLayout $layout, string $containerClass = 'layout'): string
    {
        $css = [];

        // Generate root grid if present
        if ($layout->grid !== null) {
            $css[] = $this->generateGridCss($layout->grid, $layout->slots, '.' . $containerClass);

            // Generate responsive CSS if breakpoints are defined
            if ($layout->breakpoints !== [] && $layout->grid->hasResponsiveOperators()) {
                $css[] = $this->generateResponsiveCss(
                    $layout->grid,
                    $layout->slots,
                    $layout,
                    '.' . $containerClass,
                );

                // Generate CSS for nested content wrappers
                $nestingCss = $this->generateNestingCss($layout, $containerClass);
                if ($nestingCss !== '') {
                    $css[] = $nestingCss;
                }
            }
        }

        // Generate CSS for slots with nested grids
        foreach ($layout->slots as $slot) {
            if ($slot->grid !== null && $slot->hasChildren()) {
                $css[] = $this->generateGridCss(
                    $slot->grid,
                    $slot->children,
                    '.' . $containerClass . '__' . $slot->name,
                );

                // Nested grids can have their own responsive operators
                // Note: they do NOT inherit parent breakpoints
                if ($slot->grid->hasResponsiveOperators()) {
                    $nestedBreakpoints = $this->getBreakpointsForGrid($slot->grid, $layout);
                    if ($nestedBreakpoints !== []) {
                        $css[] = $this->generateResponsiveCssForNestedGrid(
                            $slot->grid,
                            $slot->children,
                            $nestedBreakpoints,
                            '.' . $containerClass . '__' . $slot->name,
                        );
                    }
                }
            }
        }

        return implode("\n\n", $css);
    }

    /**
     * Generate CSS for nested content wrappers.
     *
     * This generates:
     * 1. Default styles hiding all nested wrappers
     * 2. Media queries showing nested wrappers at their respective breakpoints
     * 3. Visual styles copied from source slots (background, padding, etc.)
     * 4. --own wrappers for target slots that receive nested content (with their padding/margin)
     */
    private function generateNestingCss(ResolvedLayout $layout, string $containerClass): string
    {
        if ($layout->grid === null) {
            return '';
        }

        $css = [];
        /** @var array<string, string> $wrapperToSource Maps wrapper class to source slot name */
        $wrapperToSource = [];
        /** @var array<string, bool> $targetsReceivingNested Target slot names that receive nested content */
        $targetsReceivingNested = [];
        $hiddenByDefault = [];
        $showAtBreakpoint = [];
        /** @var array<string, list<string>> $hideAtBreakpoint Wrappers to explicitly hide at smaller breakpoints */
        $hideAtBreakpoint = [];
        /** @var array<string, list<string>> $previouslyNestedBySource Source slots that were nested at larger breakpoints */
        $previouslyNestedBySource = [];

        // Collect nesting info for all breakpoints
        $sortedBreakpoints = $this->sortBreakpointsDescending($layout->breakpoints);
        $breakpointNames = array_keys($sortedBreakpoints);

        foreach ($sortedBreakpoints as $name => $breakpoint) {
            $breakpointsToApply = [];
            foreach ($breakpointNames as $bpName) {
                $breakpointsToApply[] = $bpName;
                if ($bpName === $name) {
                    break;
                }
            }

            $transformed = $this->transformer->transform($layout->grid, $name, $breakpointsToApply);
            $relationships = $transformed->getNestedRelationships();
            $hiddenSlots = $transformed->hiddenSlotNames;

            // Check if any previously nested sources are now hidden - their wrappers need to be hidden
            foreach ($hiddenSlots as $hiddenSlot) {
                if (isset($previouslyNestedBySource[$hiddenSlot])) {
                    foreach ($previouslyNestedBySource[$hiddenSlot] as $wrapperClass) {
                        if (!isset($hideAtBreakpoint[$breakpoint->value])) {
                            $hideAtBreakpoint[$breakpoint->value] = [];
                        }
                        if (!in_array($wrapperClass, $hideAtBreakpoint[$breakpoint->value], true)) {
                            $hideAtBreakpoint[$breakpoint->value][] = $wrapperClass;
                        }
                    }
                }
            }

            foreach ($relationships as $source => $info) {
                $target = $info['target'];
                $wrapperClass = '.' . $containerClass . '__' . $target . '--nested-' . $source . '-' . $name;

                // Track that this target receives nested content
                $targetsReceivingNested[$target] = true;

                // Track wrapper to source mapping
                if (!isset($wrapperToSource[$wrapperClass])) {
                    $wrapperToSource[$wrapperClass] = $source;
                }

                // Track that this class should be hidden by default
                if (!in_array($wrapperClass, $hiddenByDefault, true)) {
                    $hiddenByDefault[] = $wrapperClass;
                }

                // Track that this class should be shown at this breakpoint
                if (!isset($showAtBreakpoint[$breakpoint->value])) {
                    $showAtBreakpoint[$breakpoint->value] = [];
                }
                if (!in_array($wrapperClass, $showAtBreakpoint[$breakpoint->value], true)) {
                    $showAtBreakpoint[$breakpoint->value][] = $wrapperClass;
                }

                // Track this wrapper by source for later hiding if source becomes hidden
                if (!isset($previouslyNestedBySource[$source])) {
                    $previouslyNestedBySource[$source] = [];
                }
                if (!in_array($wrapperClass, $previouslyNestedBySource[$source], true)) {
                    $previouslyNestedBySource[$source][] = $wrapperClass;
                }
            }
        }

        // Generate --own wrapper CSS for targets that receive nested content
        // The --own wrapper gets the padding/margin that would normally be on the parent
        foreach (array_keys($targetsReceivingNested) as $targetName) {
            $targetSlot = $layout->slots[$targetName] ?? null;
            if ($targetSlot !== null) {
                $ownStyles = $this->extractSpacingStyles($targetSlot->properties);
                if ($ownStyles !== '') {
                    $ownClass = '.' . $containerClass . '__' . $targetName . '--own';
                    $css[] = $ownClass . ' {';
                    $css[] = '  ' . $ownStyles;
                    $css[] = '}';
                }
            }
        }

        // Generate CSS to hide all nested wrappers by default, with source slot styling
        foreach ($wrapperToSource as $wrapperClass => $sourceName) {
            $sourceSlot = $layout->slots[$sourceName] ?? null;
            $visualStyles = $sourceSlot !== null
                ? $this->extractVisualStyles($sourceSlot->properties)
                : '';

            $css[] = $wrapperClass . ' {';
            $css[] = '  display: none;';
            if ($visualStyles !== '') {
                $css[] = '  ' . $visualStyles;
            }
            $css[] = '}';
        }

        // Generate media queries to show nested wrappers at their breakpoints
        foreach ($showAtBreakpoint as $maxWidth => $wrapperClasses) {
            $css[] = '';
            $css[] = '@media (max-width: ' . $maxWidth . ') {';
            foreach ($wrapperClasses as $wrapperClass) {
                $css[] = '  ' . $wrapperClass . ' {';
                $css[] = '    display: block;';
                $css[] = '  }';
            }
            $css[] = '}';
        }

        // Generate media queries to hide wrappers when their source becomes hidden at smaller breakpoints
        foreach ($hideAtBreakpoint as $maxWidth => $wrapperClasses) {
            $css[] = '';
            $css[] = '@media (max-width: ' . $maxWidth . ') {';
            foreach ($wrapperClasses as $wrapperClass) {
                $css[] = '  ' . $wrapperClass . ' {';
                $css[] = '    display: none;';
                $css[] = '  }';
            }
            $css[] = '}';
        }

        return implode("\n", $css);
    }

    /**
     * Extract spacing CSS properties (padding, margin) from slot properties.
     *
     * @param array<string, string> $properties
     */
    private function extractSpacingStyles(array $properties): string
    {
        $spacingProps = [
            'padding',
            'padding-top',
            'padding-right',
            'padding-bottom',
            'padding-left',
            'margin',
            'margin-top',
            'margin-right',
            'margin-bottom',
            'margin-left',
        ];

        $styles = [];
        foreach ($spacingProps as $prop) {
            if (isset($properties[$prop])) {
                $styles[] = $prop . ': ' . $properties[$prop] . ';';
            }
        }

        return implode("\n  ", $styles);
    }

    /**
     * Extract visual CSS properties from slot properties.
     * Note: padding and margin are NOT included here as they go to the --own wrapper.
     *
     * @param array<string, string> $properties
     */
    private function extractVisualStyles(array $properties): string
    {
        $visualProps = [
            'background',
            'border',
            'border-radius',
            'border-left',
            'border-right',
            'border-top',
            'border-bottom',
            'color',
            'font-size',
            'font-weight',
        ];

        $styles = [];
        foreach ($visualProps as $prop) {
            if (isset($properties[$prop])) {
                $styles[] = $prop . ': ' . $properties[$prop] . ';';
            }
        }

        // Include padding in visual styles for nested wrappers
        // (they need their own padding, not inherited from target)
        if (isset($properties['padding'])) {
            $styles[] = 'padding: ' . $properties['padding'] . ';';
        }

        return implode("\n  ", $styles);
    }

    /**
     * Generate responsive CSS with media queries for each breakpoint.
     *
     * @param array<string, ResolvedSlot> $slots
     */
    private function generateResponsiveCss(
        Grid $grid,
        array $slots,
        ResolvedLayout $layout,
        string $selector,
    ): string {
        $mediaQueries = [];

        // Sort breakpoints by value (largest first for desktop-first approach)
        $sortedBreakpoints = $this->sortBreakpointsDescending($layout->breakpoints);
        $breakpointNames = array_keys($sortedBreakpoints);

        foreach ($sortedBreakpoints as $name => $breakpoint) {
            // For desktop-first, each smaller breakpoint should include all larger breakpoints' operators
            // Build the list of breakpoints that apply at this size (this one + all larger ones processed so far)
            $breakpointsToApply = [];
            foreach ($breakpointNames as $bpName) {
                $breakpointsToApply[] = $bpName;
                if ($bpName === $name) {
                    break;
                }
            }

            $transformed = $this->transformer->transform($grid, $name, $breakpointsToApply);

            // Only generate if there are actual transformations
            if ($this->hasTransformations($transformed)) {
                $mediaQueries[] = $this->generateMediaQuery(
                    $breakpoint->value,
                    $transformed,
                    $slots,
                    $selector,
                );
            }
        }

        return implode("\n\n", $mediaQueries);
    }

    /**
     * Generate responsive CSS for a nested grid.
     *
     * @param array<string, ResolvedSlot> $slots
     * @param array<string, \PhpLayout\Ast\Breakpoint> $breakpoints
     */
    private function generateResponsiveCssForNestedGrid(
        Grid $grid,
        array $slots,
        array $breakpoints,
        string $selector,
    ): string {
        $mediaQueries = [];

        $sortedBreakpoints = $this->sortBreakpointsDescending($breakpoints);
        $breakpointNames = array_keys($sortedBreakpoints);

        foreach ($sortedBreakpoints as $name => $breakpoint) {
            // For desktop-first, each smaller breakpoint should include all larger breakpoints' operators
            $breakpointsToApply = [];
            foreach ($breakpointNames as $bpName) {
                $breakpointsToApply[] = $bpName;
                if ($bpName === $name) {
                    break;
                }
            }

            $transformed = $this->transformer->transform($grid, $name, $breakpointsToApply);

            if ($this->hasTransformations($transformed)) {
                $mediaQueries[] = $this->generateMediaQuery(
                    $breakpoint->value,
                    $transformed,
                    $slots,
                    $selector,
                );
            }
        }

        return implode("\n\n", $mediaQueries);
    }

    /**
     * Get breakpoints referenced by a grid's operators from the layout.
     *
     * @return array<string, \PhpLayout\Ast\Breakpoint>
     */
    private function getBreakpointsForGrid(Grid $grid, ResolvedLayout $layout): array
    {
        $referenced = $grid->getReferencedBreakpoints();
        $result = [];

        foreach ($referenced as $name) {
            if (isset($layout->breakpoints[$name])) {
                $result[$name] = $layout->breakpoints[$name];
            }
        }

        return $result;
    }

    /**
     * Sort breakpoints by value descending (desktop-first).
     *
     * @param array<string, \PhpLayout\Ast\Breakpoint> $breakpoints
     * @return array<string, \PhpLayout\Ast\Breakpoint>
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
     * Check if a transformed grid has actual transformations to apply.
     */
    private function hasTransformations(TransformedGrid $transformed): bool
    {
        return $transformed->hiddenColumns !== []
            || $transformed->nestedColumns !== []
            || $transformed->foldedColumns !== [];
    }

    /**
     * Generate a single media query block.
     *
     * @param array<string, ResolvedSlot> $slots
     */
    private function generateMediaQuery(
        string $maxWidth,
        TransformedGrid $transformed,
        array $slots,
        string $selector,
    ): string {
        $lines = [];
        $lines[] = '@media (max-width: ' . $maxWidth . ') {';

        // Generate transformed grid
        $lines[] = '  ' . $selector . ' {';
        $lines[] = '    grid-template-areas:';

        $areas = $transformed->generateTemplateAreas();
        $areaLines = explode("\n", trim($areas));
        foreach ($areaLines as $i => $area) {
            $suffix = $i === count($areaLines) - 1 ? ';' : '';
            $lines[] = '      ' . trim($area) . $suffix;
        }

        // Generate column count
        $columnCount = $transformed->getColumnCount();
        if ($columnCount > 0) {
            $columns = array_fill(0, $columnCount, '1fr');
            $lines[] = '    grid-template-columns: ' . implode(' ', $columns) . ';';
        }

        // Generate row template - use auto for all rows in responsive layout
        $rowCount = count($transformed->rows);
        if ($rowCount > 0) {
            $rows = array_fill(0, $rowCount, 'auto');
            $lines[] = '    grid-template-rows: ' . implode(' ', $rows) . ';';
        }

        $lines[] = '  }';

        // Add display: none for hidden and nested columns
        // Hidden columns use the ! operator, nested columns use >> or <<
        $slotsToHide = array_unique([
            ...$this->getHiddenSlotNames($transformed),
            ...$this->getNestedSlotNames($transformed),
        ]);
        foreach ($slotsToHide as $slotName) {
            $lines[] = '';
            $lines[] = '  ' . $selector . '__' . $slotName . ' {';
            $lines[] = '    display: none;';
            $lines[] = '  }';
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Get slot names that are hidden in the transformed grid.
     *
     * @return list<string>
     */
    private function getHiddenSlotNames(TransformedGrid $transformed): array
    {
        return $transformed->hiddenSlotNames;
    }

    /**
     * Get slot names that are nested in the transformed grid.
     *
     * @return list<string>
     */
    private function getNestedSlotNames(TransformedGrid $transformed): array
    {
        return $transformed->nestedSlotNames;
    }

    /**
     * Generate CSS for a single grid.
     *
     * @param array<string, ResolvedSlot> $slots
     */
    private function generateGridCss(Grid $grid, array $slots, string $selector): string
    {
        $lines = [];
        $lines[] = $selector . ' {';
        $lines[] = '  display: grid;';

        // Generate grid-template-areas
        $areas = $this->generateTemplateAreas($grid);
        $lines[] = '  grid-template-areas:';
        foreach ($areas as $i => $area) {
            $suffix = $i === count($areas) - 1 ? ';' : '';
            $lines[] = '    "' . $area . '"' . $suffix;
        }

        // Generate grid-template-columns from first row
        $columns = $this->generateTemplateColumns($grid, $slots);
        if ($columns !== null) {
            $lines[] = '  grid-template-columns: ' . $columns . ';';
        }

        // Generate grid-template-rows
        $rows = $this->generateTemplateRows($grid, $slots);
        if ($rows !== null) {
            $lines[] = '  grid-template-rows: ' . $rows . ';';
        }

        $lines[] = '}';

        // Generate area assignments for children
        $areaNames = $grid->getSlotNames();
        foreach ($areaNames as $name) {
            $lines[] = '';
            $lines[] = $selector . '__' . $name . ' {';
            $lines[] = '  grid-area: ' . $name . ';';
            $lines[] = '}';
        }

        return implode("\n", $lines);
    }

    /**
     * Generate grid-template-areas strings.
     *
     * @return list<string>
     */
    private function generateTemplateAreas(Grid $grid): array
    {
        // First, determine the total number of columns needed
        $totalColumns = $this->getTotalColumns($grid);
        $areas = [];

        foreach ($grid->rows as $row) {
            $rowAreas = [];
            foreach ($row->cells as $cell) {
                // Repeat cell name for column span
                for ($i = 0; $i < $cell->columnSpan; $i++) {
                    $rowAreas[] = $cell->name;
                }
            }

            // Ensure all rows have the same number of columns
            while (count($rowAreas) < $totalColumns) {
                // Extend the last cell to fill remaining columns
                $lastArea = $rowAreas !== [] ? $rowAreas[count($rowAreas) - 1] : '.';
                $rowAreas[] = $lastArea;
            }

            $areas[] = implode(' ', $rowAreas);
        }

        return $areas;
    }

    /**
     * Get the total number of columns in the grid.
     */
    private function getTotalColumns(Grid $grid): int
    {
        $maxColumns = 0;
        foreach ($grid->rows as $row) {
            $columns = 0;
            foreach ($row->cells as $cell) {
                $columns += $cell->columnSpan;
            }
            $maxColumns = max($maxColumns, $columns);
        }
        return $maxColumns;
    }

    /**
     * Generate grid-template-columns value.
     *
     * @param array<string, ResolvedSlot> $slots
     */
    private function generateTemplateColumns(Grid $grid, array $slots): ?string
    {
        if ($grid->rows === []) {
            return null;
        }

        // Find the row that defines the column structure (most individual cells without spans)
        $bestRow = $this->findRowWithMostColumns($grid);
        if ($bestRow === null) {
            return null;
        }

        $columns = [];
        foreach ($bestRow->cells as $cell) {
            $width = $this->getSlotWidth($cell->name, $slots);
            $columns[] = $width;
        }

        return implode(' ', $columns);
    }

    /**
     * Find the row that best defines the column structure.
     * This is the row with the most individual cells (least spanning).
     */
    private function findRowWithMostColumns(Grid $grid): ?GridRow
    {
        $bestRow = null;
        $maxCells = 0;

        foreach ($grid->rows as $row) {
            $cellCount = count($row->cells);
            if ($cellCount > $maxCells) {
                $maxCells = $cellCount;
                $bestRow = $row;
            }
        }

        return $bestRow;
    }

    /**
     * Generate grid-template-rows value.
     *
     * @param array<string, ResolvedSlot> $slots
     */
    private function generateTemplateRows(Grid $grid, array $slots): ?string
    {
        if ($grid->rows === []) {
            return null;
        }

        $rows = [];

        foreach ($grid->rows as $row) {
            // Use height from first cell in row, or auto
            $height = 'auto';
            if ($row->cells !== []) {
                $firstCell = $row->cells[0];
                $height = $this->getSlotHeight($firstCell->name, $slots);
            }
            $rows[] = $height;
        }

        return implode(' ', $rows);
    }

    /**
     * Get width for a slot.
     *
     * @param array<string, ResolvedSlot> $slots
     */
    private function getSlotWidth(string $name, array $slots): string
    {
        if (!isset($slots[$name])) {
            return '1fr';
        }

        return $slots[$name]->properties['grid-width'] ?? '1fr';
    }

    /**
     * Get height for a slot.
     *
     * @param array<string, ResolvedSlot> $slots
     */
    private function getSlotHeight(string $name, array $slots): string
    {
        if (!isset($slots[$name])) {
            return 'auto';
        }

        return $slots[$name]->properties['grid-height'] ?? 'auto';
    }
}
