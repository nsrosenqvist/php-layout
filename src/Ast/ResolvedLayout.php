<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Represents a fully resolved layout with all inheritance applied.
 */
final readonly class ResolvedLayout
{
    /**
     * @param array<string, ResolvedSlot> $slots
     * @param array<string, Breakpoint> $breakpoints Named breakpoints for responsive behavior
     */
    public function __construct(
        public string $name,
        public ?Grid $grid,
        public array $slots,
        public array $breakpoints = [],
    ) {
    }
}
