<?php

declare(strict_types=1);

namespace PhpLayout\Parser;

/**
 * Tokenizes .lyt layout files.
 */
final class Lexer
{
    private string $input;
    private int $position = 0;
    private int $line = 1;
    private int $column = 1;
    private int $length;

    public function __construct(string $input)
    {
        $this->input = $input;
        $this->length = strlen($input);
    }

    /**
     * @return list<Token>
     */
    public function tokenize(): array
    {
        $tokens = [];

        while (!$this->isAtEnd()) {
            $token = $this->nextToken();
            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        $tokens[] = new Token(TokenType::Eof, '', $this->line, $this->column);

        return $tokens;
    }

    private function nextToken(): ?Token
    {
        $this->skipWhitespaceExceptNewline();

        if ($this->isAtEnd()) {
            return null;
        }

        $char = $this->current();

        // Newline
        if ($char === "\n") {
            $token = new Token(TokenType::Newline, "\n", $this->line, $this->column);
            $this->advance();
            $this->line++;
            $this->column = 1;
            return $token;
        }

        // Skip comments
        if ($char === '#') {
            $this->skipLine();
            return null;
        }

        // @layout
        if ($char === '@') {
            return $this->readAtKeyword();
        }

        // Braces
        if ($char === '{') {
            $token = new Token(TokenType::BraceOpen, '{', $this->line, $this->column);
            $this->advance();
            return $token;
        }

        if ($char === '}') {
            $token = new Token(TokenType::BraceClose, '}', $this->line, $this->column);
            $this->advance();
            return $token;
        }

        // Brackets
        if ($char === '[') {
            return $this->readSlotReference();
        }

        // Grid lines (start with + or |)
        if ($char === '+' || $char === '|') {
            return $this->readGridLine();
        }

        // Container marker (...)
        if ($char === '.' && $this->lookAhead(1) === '.' && $this->lookAhead(2) === '.') {
            $token = new Token(TokenType::Container, '...', $this->line, $this->column);
            $this->advance();
            $this->advance();
            $this->advance();
            return $token;
        }

        // Property (key: value) or identifier
        if ($this->isIdentifierStart($char)) {
            return $this->readIdentifierOrProperty();
        }

        // Skip unknown characters
        $this->advance();
        return null;
    }

    private function readAtKeyword(): Token
    {
        $startColumn = $this->column;
        $this->advance(); // skip @

        $keyword = $this->readIdentifierString();

        if ($keyword === 'layout') {
            return new Token(TokenType::Layout, '@layout', $this->line, $startColumn);
        }

        if ($keyword === 'breakpoints') {
            return new Token(TokenType::Breakpoints, '@breakpoints', $this->line, $startColumn);
        }

        return new Token(TokenType::Identifier, '@' . $keyword, $this->line, $startColumn);
    }

    private function readSlotReference(): Token
    {
        $startColumn = $this->column;
        $this->advance(); // skip [

        $name = '';
        while (!$this->isAtEnd() && $this->current() !== ']' && $this->current() !== "\n") {
            $name .= $this->current();
            $this->advance();
        }

        if (!$this->isAtEnd() && $this->current() === ']') {
            $this->advance(); // skip ]
        }

        return new Token(TokenType::BracketOpen, '[' . trim($name) . ']', $this->line, $startColumn);
    }

    private function readGridLine(): Token
    {
        $startColumn = $this->column;
        $line = '';

        while (!$this->isAtEnd() && $this->current() !== "\n") {
            $line .= $this->current();
            $this->advance();
        }

        return new Token(TokenType::GridLine, rtrim($line), $this->line, $startColumn);
    }

    private function readIdentifierOrProperty(): Token
    {
        $startColumn = $this->column;
        $identifier = $this->readIdentifierString();

        $this->skipWhitespaceExceptNewline();

        // Check if this is a property (followed by :)
        if (!$this->isAtEnd() && $this->current() === ':') {
            $this->advance(); // skip :
            $this->skipWhitespaceExceptNewline();

            $value = '';
            while (!$this->isAtEnd() && $this->current() !== "\n") {
                $value .= $this->current();
                $this->advance();
            }

            return new Token(TokenType::Property, $identifier . ':' . trim($value), $this->line, $startColumn);
        }

        // Check for 'extends' keyword
        if ($identifier === 'extends') {
            return new Token(TokenType::Extends, 'extends', $this->line, $startColumn);
        }

        return new Token(TokenType::Identifier, $identifier, $this->line, $startColumn);
    }

    private function readIdentifierString(): string
    {
        $identifier = '';
        while (!$this->isAtEnd() && $this->isIdentifierChar($this->current())) {
            $identifier .= $this->current();
            $this->advance();
        }
        return $identifier;
    }

    private function skipWhitespaceExceptNewline(): void
    {
        while (!$this->isAtEnd()) {
            $char = $this->current();
            if ($char === ' ' || $char === "\t" || $char === "\r") {
                $this->advance();
            } else {
                break;
            }
        }
    }

    private function skipLine(): void
    {
        while (!$this->isAtEnd() && $this->current() !== "\n") {
            $this->advance();
        }
    }

    private function isIdentifierStart(string $char): bool
    {
        return ctype_alpha($char) || $char === '_';
    }

    private function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-';
    }

    private function current(): string
    {
        return $this->input[$this->position];
    }

    private function lookAhead(int $offset): ?string
    {
        $pos = $this->position + $offset;
        if ($pos >= $this->length) {
            return null;
        }
        return $this->input[$pos];
    }

    private function advance(): void
    {
        $this->position++;
        $this->column++;
    }

    private function isAtEnd(): bool
    {
        return $this->position >= $this->length;
    }
}
