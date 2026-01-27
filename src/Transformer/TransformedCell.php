<?php

declare(strict_types=1);

namespace PhpLayout\Transformer;

/**
 * Represents a cell in a transformed grid.
 *
 * Cell types:
 * - 'normal': Regular cell, appears in the grid
 * - 'nest': Cell content merges INTO target slot (>> / <<), removed from grid
 * - 'fold': Cell becomes a new full-width row (> / <)
 */
final readonly class TransformedCell
{
    /**
     * @param string $name The slot name
     * @param int $columnSpan How many columns this cell spans
     * @param string|null $target For nested/folded cells, the target slot name
     * @param string $type The transformation type: 'normal', 'nest', 'fold'
     * @param string|null $direction For nested cells: 'left' (prepend) or 'right' (append)
     */
    public function __construct(
        public string $name,
        public int $columnSpan,
        public ?string $target,
        public string $type,
        public ?string $direction = null,
    ) {
    }

    /**
     * Check if this cell was transformed (nested or folded).
     */
    public function isTransformed(): bool
    {
        return $this->type !== 'normal';
    }

    /**
     * Check if this cell is nested into another (>> / <<).
     * Nested cells are removed from the CSS grid and their content
     * is rendered inside the target slot.
     */
    public function isNested(): bool
    {
        return $this->type === 'nest';
    }

    /**
     * Check if this cell is folded to a new row (> / <).
     * Folded cells become full-width rows in the CSS grid.
     */
    public function isFolded(): bool
    {
        return $this->type === 'fold';
    }
}
