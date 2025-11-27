<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Represents a cell in a grid row.
 */
final readonly class GridCell
{
    public function __construct(
        public string $name,
        public int $columnSpan = 1,
    ) {
    }
}
