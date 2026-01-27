<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Represents a grid structure parsed from ASCII box-drawing.
 */
final readonly class Grid
{
    /**
     * @param list<GridRow> $rows
     * @param list<ColumnBoundary> $columnBoundaries Column boundaries with responsive operators
     * @param list<RowBoundary> $rowBoundaries Row boundaries with responsive operators
     */
    public function __construct(
        public array $rows,
        public array $columnBoundaries = [],
        public array $rowBoundaries = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function getSlotNames(): array
    {
        $names = [];
        foreach ($this->rows as $row) {
            foreach ($row->cells as $cell) {
                if (!in_array($cell->name, $names, true)) {
                    $names[] = $cell->name;
                }
            }
        }
        return $names;
    }

    /**
     * Check if this grid has any responsive operators.
     */
    public function hasResponsiveOperators(): bool
    {
        foreach ($this->columnBoundaries as $boundary) {
            if ($boundary->hasOperators()) {
                return true;
            }
        }
        foreach ($this->rowBoundaries as $boundary) {
            if ($boundary->hasOperators()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all unique breakpoints referenced by operators in this grid.
     *
     * @return list<string>
     */
    public function getReferencedBreakpoints(): array
    {
        $breakpoints = [];
        foreach ($this->columnBoundaries as $boundary) {
            foreach ($boundary->operators as $op) {
                if (!in_array($op->breakpoint, $breakpoints, true)) {
                    $breakpoints[] = $op->breakpoint;
                }
            }
        }
        foreach ($this->rowBoundaries as $boundary) {
            foreach ($boundary->operators as $op) {
                if (!in_array($op->breakpoint, $breakpoints, true)) {
                    $breakpoints[] = $op->breakpoint;
                }
            }
        }
        return $breakpoints;
    }
}
