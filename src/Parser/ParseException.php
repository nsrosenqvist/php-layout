<?php

declare(strict_types=1);

namespace PhpLayout\Parser;

/**
 * Exception thrown when parsing fails.
 */
final class ParseException extends \RuntimeException
{
    public static function unexpectedToken(Token $token, string $expected): self
    {
        return new self(sprintf(
            'Unexpected token %s "%s" at line %d, column %d. Expected %s.',
            $token->type->value,
            $token->value,
            $token->line,
            $token->column,
            $expected,
        ));
    }

    public static function unexpectedEndOfInput(string $expected): self
    {
        return new self(sprintf(
            'Unexpected end of input. Expected %s.',
            $expected,
        ));
    }
}
