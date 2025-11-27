<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Represents a row in a grid.
 */
final readonly class GridRow
{
    /**
     * @param list<GridCell> $cells
     */
    public function __construct(
        public array $cells,
    ) {
    }
}
