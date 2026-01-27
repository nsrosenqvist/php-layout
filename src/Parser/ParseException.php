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

    /**
     * @param list<string> $operators
     */
    public static function conflictingOperators(string $breakpoint, array $operators, string $context): self
    {
        return new self(sprintf(
            'Conflicting responsive operators at breakpoint "%s" on %s: %s. Only one structural operator allowed per breakpoint.',
            $breakpoint,
            $context,
            implode(', ', $operators),
        ));
    }

    public static function duplicateOperator(string $breakpoint, string $operator, string $context): self
    {
        return new self(sprintf(
            'Duplicate operator "%s" at breakpoint "%s" on %s.',
            $operator,
            $breakpoint,
            $context,
        ));
    }

    public static function undefinedBreakpoint(string $breakpoint, string $context): self
    {
        return new self(sprintf(
            'Undefined breakpoint "%s" used in %s. Define it in @breakpoints block.',
            $breakpoint,
            $context,
        ));
    }
}
