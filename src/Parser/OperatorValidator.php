<?php

declare(strict_types=1);

namespace PhpLayout\Parser;

use PhpLayout\Ast\ColumnBoundary;
use PhpLayout\Ast\ResponsiveOperator;
use PhpLayout\Ast\ResponsiveOperatorType;
use PhpLayout\Ast\RowBoundary;

/**
 * Validates responsive operators for conflicts and semantic errors.
 */
final class OperatorValidator
{
    /**
     * Validate operators on column boundaries.
     *
     * @param list<ColumnBoundary> $boundaries
     * @throws ParseException If validation fails
     */
    public function validateColumnBoundaries(array $boundaries): void
    {
        foreach ($boundaries as $index => $boundary) {
            $this->validateOperators($boundary->operators, "column boundary $index");
        }
    }

    /**
     * Validate operators on row boundaries.
     *
     * @param list<RowBoundary> $boundaries
     * @throws ParseException If validation fails
     */
    public function validateRowBoundaries(array $boundaries): void
    {
        foreach ($boundaries as $index => $boundary) {
            $this->validateOperators($boundary->operators, "row boundary $index");
        }
    }

    /**
     * Validate a list of operators for conflicts.
     *
     * @param list<ResponsiveOperator> $operators
     * @throws ParseException If validation fails
     */
    private function validateOperators(array $operators, string $context): void
    {
        if ($operators === []) {
            return;
        }

        // Group operators by breakpoint
        $byBreakpoint = [];
        foreach ($operators as $op) {
            $byBreakpoint[$op->breakpoint][] = $op;
        }

        // Check each breakpoint for conflicts
        foreach ($byBreakpoint as $breakpoint => $ops) {
            $this->validateBreakpointOperators($ops, $breakpoint, $context);
        }
    }

    /**
     * Validate operators at a single breakpoint.
     *
     * @param list<ResponsiveOperator> $operators
     * @throws ParseException If validation fails
     */
    private function validateBreakpointOperators(array $operators, string $breakpoint, string $context): void
    {
        if (count($operators) <= 1) {
            return;
        }

        // Check for conflicting structural operators
        $structuralOps = [];
        $hideCount = 0;

        foreach ($operators as $op) {
            if ($op->type === ResponsiveOperatorType::Hide) {
                $hideCount++;
            } else {
                $structuralOps[] = $op;
            }
        }

        // Can't have multiple structural operators at the same breakpoint
        if (count($structuralOps) > 1) {
            $types = array_map(static fn (ResponsiveOperator $op): string => $op->type->value, $structuralOps);
            throw ParseException::conflictingOperators(
                $breakpoint,
                $types,
                $context,
            );
        }

        // Can't have both a structural operator and hide at the same breakpoint
        if (count($structuralOps) > 0 && $hideCount > 0) {
            throw ParseException::conflictingOperators(
                $breakpoint,
                ['structural operator', '!'],
                $context,
            );
        }

        // Can't have multiple hide operators at the same breakpoint (redundant)
        if ($hideCount > 1) {
            throw ParseException::duplicateOperator($breakpoint, '!', $context);
        }
    }
}
