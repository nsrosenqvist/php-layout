<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Represents a row boundary (horizontal separator) with responsive operators.
 */
final readonly class RowBoundary
{
    /**
     * @param int $lineIndex The line index of this row separator
     * @param list<ResponsiveOperator> $operators Responsive operators attached to this boundary
     */
    public function __construct(
        public int $lineIndex,
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
