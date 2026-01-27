<?php

declare(strict_types=1);

namespace PhpLayout\Transformer;

/**
 * Represents a grid structure transformed for a specific breakpoint.
 *
 * Operators:
 * - > / < (fold): Column becomes a new full-width row in the grid
 * - >> / << (nest): Column content merges INTO target slot (removed from grid areas)
 */
final readonly class TransformedGrid
{
    /**
     * @param string $breakpoint The breakpoint this transformation applies to
     * @param list<TransformedRow> $rows The transformed rows
     * @param list<int> $hiddenColumns Column indices that are hidden at this breakpoint
     * @param array<int, array{direction: string, target: string|null}> $nestedColumns Nested column info (>> / <<)
     * @param array<int, array{direction: string, target: string|null}> $foldedColumns Folded column info (> / <)
     * @param list<string> $hiddenSlotNames Slot names that are hidden at this breakpoint
     * @param list<string> $nestedSlotNames Slot names that are nested at this breakpoint (need display: none)
     */
    public function __construct(
        public string $breakpoint,
        public array $rows,
        public array $hiddenColumns = [],
        public array $nestedColumns = [],
        public array $foldedColumns = [],
        public array $hiddenSlotNames = [],
        public array $nestedSlotNames = [],
    ) {
    }

    /**
     * Generate grid-template-areas string for this transformed grid.
     *
     * - Nested cells (>> / <<): REMOVED from grid - content goes into target slot
     * - Folded cells (> / <): Become new full-width rows
     */
    public function generateTemplateAreas(): string
    {
        $lines = [];
        $maxColumns = $this->getColumnCount();

        foreach ($this->rows as $row) {
            $areas = [];

            foreach ($row->cells as $cell) {
                if ($cell->isNested()) {
                    // Nested cells are REMOVED from grid areas entirely
                    // Their content will be rendered inside the target slot
                    continue;
                }

                if ($cell->isFolded()) {
                    // Folded cells span full width as a new row
                    for ($i = 0; $i < $maxColumns; $i++) {
                        $areas[] = $cell->name;
                    }
                } else {
                    // Normal cells - use column span but don't exceed maxColumns
                    for ($i = 0; $i < $cell->columnSpan && count($areas) < $maxColumns; $i++) {
                        $areas[] = $cell->name;
                    }
                }
            }

            if ($areas !== []) {
                // Ensure consistent column count by padding if needed
                while (count($areas) < $maxColumns) {
                    $areas[] = $areas[count($areas) - 1];
                }
                $lines[] = '"' . implode(' ', $areas) . '"';
            }
        }

        return implode("\n    ", $lines);
    }

    /**
     * Get the number of columns in this transformed grid.
     *
     * This calculates the column count based on multi-cell rows only,
     * as single-cell rows (header/footer) are full-width and shouldn't
     * determine the grid column structure.
     */
    public function getColumnCount(): int
    {
        $maxColumns = 0;
        foreach ($this->rows as $row) {
            // Count visible cells (not nested or folded)
            $visibleCells = array_filter(
                $row->cells,
                static fn ($cell) => !$cell->isNested() && !$cell->isFolded(),
            );

            // Skip single-cell rows (full-width header/footer)
            if (count($visibleCells) <= 1) {
                continue;
            }

            $rowColumns = 0;
            foreach ($visibleCells as $cell) {
                $rowColumns += $cell->columnSpan;
            }
            $maxColumns = max($maxColumns, $rowColumns);
        }
        // Ensure at least 1 column
        return max(1, $maxColumns);
    }

    /**
     * Get all slot names visible in this transformed grid.
     *
     * @return list<string>
     */
    public function getVisibleSlotNames(): array
    {
        $names = [];
        foreach ($this->rows as $row) {
            foreach ($row->cells as $cell) {
                // Nested cells are not visible in the grid (they're inside other slots)
                if ($cell->isNested()) {
                    continue;
                }
                if (!in_array($cell->name, $names, true)) {
                    $names[] = $cell->name;
                }
            }
        }
        return $names;
    }

    /**
     * Get nested cell relationships for this breakpoint.
     *
     * @return array<string, array{target: string, direction: string}> Map of nested slot name => target info
     */
    public function getNestedRelationships(): array
    {
        $nested = [];
        foreach ($this->rows as $row) {
            foreach ($row->cells as $cell) {
                if ($cell->isNested() && $cell->target !== null) {
                    $nested[$cell->name] = [
                        'target' => $cell->target,
                        'direction' => $cell->direction ?? 'right',
                    ];
                }
            }
        }
        return $nested;
    }
}
