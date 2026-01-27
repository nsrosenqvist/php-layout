<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Represents a column boundary in the grid with its position and responsive operators.
 */
final readonly class ColumnBoundary
{
    /**
     * @param int $position The character position in the line
     * @param list<ResponsiveOperator> $operators Responsive operators attached to this boundary
     */
    public function __construct(
        public int $position,
        public array $operators = [],
    ) {
    }

    /**
     * Check if this boundary has any responsive operators.
     */
    public function hasOperators(): bool
    {
        return $this->operators !== [];
    }

    /**
     * Get operators for a specific breakpoint.
     *
     * @return list<ResponsiveOperator>
     */
    public function getOperatorsForBreakpoint(string $breakpoint): array
    {
        return array_values(array_filter(
            $this->operators,
            static fn (ResponsiveOperator $op): bool => $op->breakpoint === $breakpoint,
        ));
    }
}
