<?php

declare(strict_types=1);

namespace PhpLayout\Transformer;

use PhpLayout\Ast\ColumnBoundary;
use PhpLayout\Ast\Grid;
use PhpLayout\Ast\GridCell;
use PhpLayout\Ast\GridRow;
use PhpLayout\Ast\ResponsiveOperator;
use PhpLayout\Ast\ResponsiveOperatorType;

/**
 * Transforms a grid for a specific breakpoint by applying responsive operators.
 *
 * The transformer produces a new Grid representing how the layout should
 * appear at a given breakpoint. This is then used by CssGenerator to emit
 * appropriate media queries.
 */
final class ResponsiveGridTransformer
{
    /**
     * Transform a grid for a specific breakpoint.
     *
     * @param list<string> $breakpointsToApply All breakpoints that should apply (for cumulative transforms)
     * @return TransformedGrid The grid structure at this breakpoint
     */
    public function transform(Grid $grid, string $breakpoint, array $breakpointsToApply = []): TransformedGrid
    {
        // If no cumulative breakpoints specified, just use the single breakpoint
        if ($breakpointsToApply === []) {
            $breakpointsToApply = [$breakpoint];
        }

        // Collect operators from ALL applicable breakpoints
        // Use associative array with column index as key to allow overrides
        /** @var array<int, 'hidden'|array{type: 'nested'|'folded', direction: string, target: string|null}> $columnStates */
        $columnStates = [];

        foreach ($breakpointsToApply as $bp) {
            $columnOps = $this->getColumnOperatorsForBreakpoint($grid, $bp);

            foreach ($columnOps as $columnIndex => $op) {
                // Smaller breakpoints override larger ones by simply setting the new state
                $columnStates[$columnIndex] = match ($op->type) {
                    ResponsiveOperatorType::Hide => 'hidden',
                    ResponsiveOperatorType::NestRight => [
                        'type' => 'nested',
                        'direction' => 'right',
                        'target' => $op->target,
                    ],
                    ResponsiveOperatorType::NestLeft => [
                        'type' => 'nested',
                        'direction' => 'left',
                        'target' => $op->target,
                    ],
                    ResponsiveOperatorType::FoldDown => [
                        'type' => 'folded',
                        'direction' => 'down',
                        'target' => $op->target,
                    ],
                    ResponsiveOperatorType::FoldUp => [
                        'type' => 'folded',
                        'direction' => 'up',
                        'target' => $op->target,
                    ],
                };
            }
        }

        // Separate the states into their respective arrays
        $hiddenColumns = [];
        $nestedColumns = [];
        $foldedColumns = [];

        foreach ($columnStates as $columnIndex => $state) {
            if ($state === 'hidden') {
                $hiddenColumns[] = $columnIndex;
            } elseif ($state['type'] === 'nested') {
                $nestedColumns[$columnIndex] = [
                    'direction' => $state['direction'],
                    'target' => $state['target'],
                ];
            } elseif ($state['type'] === 'folded') {
                $foldedColumns[$columnIndex] = [
                    'direction' => $state['direction'],
                    'target' => $state['target'],
                ];
            }
        }

        // Collect hidden and nested slot names from all rows
        $hiddenSlotNames = $this->collectHiddenSlotNames($grid, $hiddenColumns);
        $nestedSlotNames = $this->collectNestedSlotNames($grid, $nestedColumns);

        // Build the transformed grid structure
        $transformedRows = $this->buildTransformedRows(
            $grid,
            $hiddenColumns,
            $nestedColumns,
            $foldedColumns,
        );

        return new TransformedGrid(
            $breakpoint,
            $transformedRows,
            $hiddenColumns,
            $nestedColumns,
            $foldedColumns,
            $hiddenSlotNames,
            $nestedSlotNames,
        );
    }

    /**
     * Get column operators for a specific breakpoint, indexed by column index.
     *
     * @return array<int, ResponsiveOperator>
     */
    private function getColumnOperatorsForBreakpoint(Grid $grid, string $breakpoint): array
    {
        $ops = [];
        foreach ($grid->columnBoundaries as $index => $boundary) {
            $boundaryOps = $boundary->getOperatorsForBreakpoint($breakpoint);
            if ($boundaryOps !== []) {
                // Use the first operator (validation ensures no conflicts at same breakpoint)
                $ops[$index] = $boundaryOps[0];
            }
        }
        return $ops;
    }

    /**
     * Build transformed row structures.
     *
     * Operators:
     * - > / < (fold): Column becomes a new full-width row
     * - >> / << (nest): Column content merges INTO target slot (removed from grid)
     *
     * @param list<int> $hiddenColumns
     * @param array<int, array{direction: string, target: string|null}> $nestedColumns Nest into target (subgrid)
     * @param array<int, array{direction: string, target: string|null}> $foldedColumns Fold to new row
     * @return list<TransformedRow>
     */
    private function buildTransformedRows(
        Grid $grid,
        array $hiddenColumns,
        array $nestedColumns,
        array $foldedColumns,
    ): array {
        $result = [];

        foreach ($grid->rows as $rowIndex => $row) {
            $transformedCells = [];
            $foldedCells = []; // Cells that become new rows (> / <)

            // Map column boundary index to cell
            $cellColumnMap = $this->buildCellColumnMap($row, $grid->columnBoundaries);

            foreach ($row->cells as $cellIndex => $cell) {
                $columnIndex = $cellColumnMap[$cellIndex] ?? null;

                // Get the range of columns this cell spans
                $startColumn = $columnIndex ?? 0;

                // Determine total columns for checking if this is a full-width cell
                $totalColumns = count($grid->columnBoundaries) - 1;
                $isFullWidth = $cell->columnSpan >= $totalColumns;

                // Full-width cells (header/footer) are not affected by column operators
                if ($isFullWidth) {
                    $transformedCells[] = new TransformedCell(
                        $cell->name,
                        $cell->columnSpan,
                        null,
                        'normal',
                    );
                    continue;
                }

                // Check if this cell should be hidden (starts at a hidden column)
                if ($columnIndex !== null && in_array($columnIndex, $hiddenColumns, true)) {
                    continue; // Skip hidden cells
                }

                // Check if this cell should be nested (>> / <<)
                // Nested cells are REMOVED from the grid - their content goes into the target
                if ($columnIndex !== null && isset($nestedColumns[$columnIndex])) {
                    $nest = $nestedColumns[$columnIndex];
                    $target = $nest['target'];
                    $direction = $nest['direction'];

                    // Cell is nested - mark for CSS to handle subgrid relationship
                    $transformedCells[] = new TransformedCell(
                        $cell->name,
                        $cell->columnSpan,
                        $target ?? $this->getAdjacentCellName($row, $cellIndex, $direction),
                        'nest',
                        $direction,
                    );
                    continue;
                }

                // Check if this cell should be folded (> / <)
                // Folded cells become new full-width rows
                if ($columnIndex !== null && isset($foldedColumns[$columnIndex])) {
                    $fold = $foldedColumns[$columnIndex];
                    $target = $fold['target'];

                    // Cell becomes a new row
                    $foldedCells[] = new TransformedCell(
                        $cell->name,
                        $cell->columnSpan,
                        $target,
                        'fold',
                    );
                    continue;
                }

                // Calculate adjusted span for spanning cells (reduce span for hidden columns)
                $adjustedSpan = $this->calculateAdjustedSpan(
                    $cell,
                    $startColumn,
                    $hiddenColumns,
                    $nestedColumns,
                    $foldedColumns,
                );

                // Only add the cell if it has any visible columns remaining
                if ($adjustedSpan > 0) {
                    $transformedCells[] = new TransformedCell(
                        $cell->name,
                        $adjustedSpan,
                        null,
                        'normal',
                    );
                }
            }

            if ($transformedCells !== []) {
                $result[] = new TransformedRow($transformedCells);
            }

            // Add folded cells as new rows after this row (> / <)
            foreach ($foldedCells as $foldedCell) {
                $result[] = new TransformedRow([$foldedCell]);
            }
        }

        return $result;
    }

    /**
     * Map cell index to the column boundary index (the left boundary of the cell).
     *
     * Boundary 0 is the start of the first column (nav).
     * Boundary 1 is the start of the second column (content), etc.
     *
     * @param list<ColumnBoundary> $boundaries
     * @return array<int, int>
     */
    private function buildCellColumnMap(GridRow $row, array $boundaries): array
    {
        $map = [];
        $boundaryIndex = 0;

        foreach ($row->cells as $cellIndex => $cell) {
            // The cell starts at the current boundary index
            $map[$cellIndex] = $boundaryIndex;
            // Move past this cell's span
            $boundaryIndex += $cell->columnSpan;
        }

        return $map;
    }

    /**
     * Get the name of an adjacent cell based on direction.
     *
     * @param string $direction 'right' or 'left'
     */
    private function getAdjacentCellName(GridRow $row, int $cellIndex, string $direction): ?string
    {
        if ($direction === 'right') {
            // >> nests INTO the cell to the LEFT (previous cell)
            if ($cellIndex > 0 && isset($row->cells[$cellIndex - 1])) {
                return $row->cells[$cellIndex - 1]->name;
            }
        } else {
            // << nests INTO the cell to the RIGHT (next cell)
            if (isset($row->cells[$cellIndex + 1])) {
                return $row->cells[$cellIndex + 1]->name;
            }
        }
        return null;
    }

    /**
     * Calculate adjusted column span after removing hidden/nested/folded columns.
     *
     * @param list<int> $hiddenColumns
     * @param array<int, array{direction: string, target: string|null}> $nestedColumns
     * @param array<int, array{direction: string, target: string|null}> $foldedColumns
     */
    private function calculateAdjustedSpan(
        GridCell $cell,
        int $startColumn,
        array $hiddenColumns,
        array $nestedColumns,
        array $foldedColumns,
    ): int {
        $adjustedSpan = 0;
        $endColumn = $startColumn + $cell->columnSpan;

        for ($col = $startColumn; $col < $endColumn; $col++) {
            // Skip columns that are hidden, nested, or folded
            if (in_array($col, $hiddenColumns, true)) {
                continue;
            }
            if (isset($nestedColumns[$col])) {
                continue;
            }
            if (isset($foldedColumns[$col])) {
                continue;
            }
            $adjustedSpan++;
        }

        return $adjustedSpan;
    }

    /**
     * Collect slot names from hidden columns.
     *
     * Only cells that START at a hidden column boundary are marked as hidden.
     * Full-width cells (spanning all columns) are excluded as they represent
     * header/footer rows that shouldn't be affected by column operators.
     *
     * @param list<int> $hiddenColumns
     * @return list<string>
     */
    private function collectHiddenSlotNames(Grid $grid, array $hiddenColumns): array
    {
        if ($hiddenColumns === []) {
            return [];
        }

        $totalColumns = count($grid->columnBoundaries) - 1;
        $hiddenSlotNames = [];

        foreach ($grid->rows as $row) {
            $cellColumnMap = $this->buildCellColumnMap($row, $grid->columnBoundaries);

            foreach ($row->cells as $cellIndex => $cell) {
                // Skip full-width cells (header/footer rows)
                if ($cell->columnSpan >= $totalColumns) {
                    continue;
                }

                $startColumn = $cellColumnMap[$cellIndex] ?? null;
                if ($startColumn === null) {
                    continue;
                }

                // Cell is hidden if it STARTS at a hidden column
                if (in_array($startColumn, $hiddenColumns, true)) {
                    if (!in_array($cell->name, $hiddenSlotNames, true)) {
                        $hiddenSlotNames[] = $cell->name;
                    }
                }
            }
        }

        return $hiddenSlotNames;
    }

    /**
     * Collect slot names from nested columns.
     *
     * Only cells that START at a nested column boundary are marked as nested.
     * Full-width cells (spanning all columns) are excluded as they represent
     * header/footer rows that shouldn't be affected by column operators.
     *
     * @param array<int, array{direction: string, target: string|null}> $nestedColumns
     * @return list<string>
     */
    private function collectNestedSlotNames(Grid $grid, array $nestedColumns): array
    {
        if ($nestedColumns === []) {
            return [];
        }

        $totalColumns = count($grid->columnBoundaries) - 1;
        $nestedSlotNames = [];

        foreach ($grid->rows as $row) {
            $cellColumnMap = $this->buildCellColumnMap($row, $grid->columnBoundaries);

            foreach ($row->cells as $cellIndex => $cell) {
                // Skip full-width cells (header/footer rows)
                if ($cell->columnSpan >= $totalColumns) {
                    continue;
                }

                $startColumn = $cellColumnMap[$cellIndex] ?? null;
                if ($startColumn === null) {
                    continue;
                }

                // Cell is nested if it STARTS at a nested column
                if (isset($nestedColumns[$startColumn])) {
                    if (!in_array($cell->name, $nestedSlotNames, true)) {
                        $nestedSlotNames[] = $cell->name;
                    }
                }
            }
        }

        return $nestedSlotNames;
    }
}
