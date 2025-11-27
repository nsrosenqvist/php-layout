<?php

declare(strict_types=1);

namespace PhpLayout\Generator;

use PhpLayout\Ast\Grid;
use PhpLayout\Ast\GridRow;
use PhpLayout\Ast\ResolvedLayout;
use PhpLayout\Ast\ResolvedSlot;

/**
 * Generates CSS Grid styles from resolved layouts.
 */
final class CssGenerator
{
    /**
     * Generate CSS for a resolved layout.
     */
    public function generate(ResolvedLayout $layout, string $containerClass = 'layout'): string
    {
        $css = [];

        // Generate root grid if present
        if ($layout->grid !== null) {
            $css[] = $this->generateGridCss($layout->grid, $layout->slots, '.' . $containerClass);
        }

        // Generate CSS for slots with nested grids
        foreach ($layout->slots as $slot) {
            if ($slot->grid !== null && $slot->hasChildren()) {
                $css[] = $this->generateGridCss(
                    $slot->grid,
                    $slot->children,
                    '.' . $containerClass . '__' . $slot->name,
                );
            }
        }

        return implode("\n\n", $css);
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

        return $slots[$name]->properties['width'] ?? '1fr';
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

        return $slots[$name]->properties['height'] ?? 'auto';
    }
}
