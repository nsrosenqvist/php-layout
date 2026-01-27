<?php

declare(strict_types=1);

namespace PhpLayout\Transformer;

/**
 * Represents a row in a transformed grid.
 */
final readonly class TransformedRow
{
    /**
     * @param list<TransformedCell> $cells
     */
    public function __construct(
        public array $cells,
    ) {
    }
}
