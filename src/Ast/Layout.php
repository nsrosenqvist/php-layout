<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Represents a parsed layout definition.
 */
final readonly class Layout
{
    /**
     * @param array<string, SlotDefinition> $slots
     * @param array<string, Breakpoint> $breakpoints Named breakpoints for responsive behavior
     */
    public function __construct(
        public string $name,
        public ?string $extends,
        public ?Grid $grid,
        public array $slots,
        public array $breakpoints = [],
    ) {
    }
}
