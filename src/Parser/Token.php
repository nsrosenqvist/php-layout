<?php

declare(strict_types=1);

namespace PhpLayout\Parser;

/**
 * Represents a token from the lexer.
 */
final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public int $line,
        public int $column,
    ) {
    }
}
