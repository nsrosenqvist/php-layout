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
     */
    public function __construct(
        public string $name,
        public ?Grid $grid,
        public array $slots,
    ) {
    }
}
