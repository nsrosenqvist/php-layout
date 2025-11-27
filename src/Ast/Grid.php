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
     */
    public function __construct(
        public array $rows,
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
}
