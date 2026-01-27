<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Represents a responsive operator attached to a grid separator.
 *
 * Operators describe how the grid transforms at a specific breakpoint.
 * Multiple operators can be combined (e.g., ">>sm!md" - fold at sm, hide at md).
 */
final readonly class ResponsiveOperator
{
    /**
     * @param ResponsiveOperatorType $type The operation to perform
     * @param string $breakpoint The breakpoint name (e.g., "sm", "md")
     * @param string|null $target Optional target slot name for reordering (e.g., "content")
     */
    public function __construct(
        public ResponsiveOperatorType $type,
        public string $breakpoint,
        public ?string $target = null,
    ) {
    }
}
