<?php

declare(strict_types=1);

namespace PhpLayout\Parser;

/**
 * Token types for the layout language.
 */
enum TokenType: string
{
    case Layout = 'LAYOUT';             // @layout
    case Breakpoints = 'BREAKPOINTS';   // @breakpoints
    case Extends = 'EXTENDS';           // extends
    case Identifier = 'IDENTIFIER';     // layout name, slot name
    case BraceOpen = 'BRACE_OPEN';      // {
    case BraceClose = 'BRACE_CLOSE';    // }
    case BracketOpen = 'BRACKET_OPEN';  // [
    case BracketClose = 'BRACKET_CLOSE'; // ]
    case GridLine = 'GRID_LINE';        // +---+ or |...|
    case Property = 'PROPERTY';         // key: value
    case Container = 'CONTAINER';       // ...
    case Newline = 'NEWLINE';
    case Eof = 'EOF';
}
