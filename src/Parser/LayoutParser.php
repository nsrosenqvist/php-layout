<?php

declare(strict_types=1);

namespace PhpLayout\Parser;

use PhpLayout\Ast\Grid;
use PhpLayout\Ast\Layout;
use PhpLayout\Ast\SlotDefinition;

/**
 * Parses tokens into Layout AST objects.
 */
final class LayoutParser
{
    /** @var list<Token> */
    private array $tokens;
    private int $position = 0;
    private GridParser $gridParser;

    public function __construct()
    {
        $this->gridParser = new GridParser();
    }

    /**
     * Parse a layout file and return all layout definitions.
     *
     * @return list<Layout>
     */
    public function parse(string $input): array
    {
        $lexer = new Lexer($input);
        $this->tokens = $lexer->tokenize();
        $this->position = 0;

        $layouts = [];

        while (!$this->isAtEnd()) {
            $this->skipNewlines();

            if ($this->isAtEnd()) {
                break;
            }

            if ($this->check(TokenType::Layout)) {
                $layouts[] = $this->parseLayout();
            } else {
                $this->advance();
            }
        }

        return $layouts;
    }

    private function parseLayout(): Layout
    {
        $this->consume(TokenType::Layout, '@layout');
        $this->skipNewlines();

        $name = $this->consume(TokenType::Identifier, 'layout name')->value;
        $this->skipNewlines();

        $extends = null;
        if ($this->check(TokenType::Extends)) {
            $this->advance();
            $this->skipNewlines();
            $extends = $this->consume(TokenType::Identifier, 'parent layout name')->value;
            $this->skipNewlines();
        }

        $this->consume(TokenType::BraceOpen, '{');
        $this->skipNewlines();

        $grid = null;
        $slots = [];

        // Parse layout body
        while (!$this->check(TokenType::BraceClose) && !$this->isAtEnd()) {
            $this->skipNewlines();

            if ($this->check(TokenType::BraceClose)) {
                break;
            }

            // Grid at top level
            if ($this->check(TokenType::GridLine)) {
                $grid = $this->parseGrid();
                continue;
            }

            // Slot definition [name]
            if ($this->check(TokenType::BracketOpen)) {
                $slot = $this->parseSlotDefinition();
                $slots[$slot->name] = $slot;
                continue;
            }

            // Skip unknown tokens
            $this->advance();
        }

        $this->consume(TokenType::BraceClose, '}');

        return new Layout($name, $extends, $grid, $slots);
    }

    private function parseSlotDefinition(): SlotDefinition
    {
        $slotToken = $this->consume(TokenType::BracketOpen, '[slot]');
        // Extract name from [name]
        $name = trim($slotToken->value, '[]');
        $this->skipNewlines();

        $properties = [];
        $nestedGrid = null;
        $isContainer = false;

        // Check for nested grid definition { ... }
        if ($this->check(TokenType::BraceOpen)) {
            $this->advance();
            $this->skipNewlines();
            $nestedGrid = $this->parseGrid();
            $this->skipNewlines();
            $this->consume(TokenType::BraceClose, '}');
            $this->skipNewlines();
        }

        // Parse properties until next slot or end
        while (!$this->isAtEnd() && !$this->check(TokenType::BracketOpen) && !$this->check(TokenType::BraceClose)) {
            $this->skipNewlines();

            if ($this->check(TokenType::BracketOpen) || $this->check(TokenType::BraceClose)) {
                break;
            }

            if ($this->check(TokenType::Container)) {
                $isContainer = true;
                $this->advance();
                continue;
            }

            if ($this->check(TokenType::Property)) {
                $prop = $this->advance();
                [$key, $value] = explode(':', $prop->value, 2);
                $properties[trim($key)] = trim($value);
                continue;
            }

            if ($this->check(TokenType::Newline)) {
                $this->advance();
                continue;
            }

            // Stop at grid lines (they belong to a nested definition)
            if ($this->check(TokenType::GridLine)) {
                break;
            }

            $this->advance();
        }

        return new SlotDefinition($name, $properties, $nestedGrid, $isContainer);
    }

    private function parseGrid(): Grid
    {
        $lines = [];

        while ($this->check(TokenType::GridLine)) {
            $lines[] = $this->advance()->value;
            $this->skipNewlines();
        }

        return $this->gridParser->parse($lines);
    }

    private function consume(TokenType $type, string $expected): Token
    {
        if ($this->isAtEnd()) {
            throw ParseException::unexpectedEndOfInput($expected);
        }

        if (!$this->check($type)) {
            throw ParseException::unexpectedToken($this->current(), $expected);
        }

        return $this->advance();
    }

    private function check(TokenType $type): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }
        return $this->current()->type === $type;
    }

    private function advance(): Token
    {
        $token = $this->current();
        $this->position++;
        return $token;
    }

    private function current(): Token
    {
        return $this->tokens[$this->position];
    }

    private function isAtEnd(): bool
    {
        return $this->position >= count($this->tokens) ||
               $this->tokens[$this->position]->type === TokenType::Eof;
    }

    private function skipNewlines(): void
    {
        while ($this->check(TokenType::Newline)) {
            $this->advance();
        }
    }
}
