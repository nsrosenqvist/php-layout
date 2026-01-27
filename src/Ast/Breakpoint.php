<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Represents a named breakpoint with its max-width value.
 *
 * Breakpoints define responsive thresholds for grid transformations.
 * Following desktop-first approach, the value represents max-width.
 */
final readonly class Breakpoint
{
    /**
     * @param string $name Breakpoint identifier (e.g., "sm", "md", "lg")
     * @param string $value CSS max-width value (e.g., "300px", "600px")
     */
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }
}
