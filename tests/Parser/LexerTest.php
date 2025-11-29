<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Parser;

use PhpLayout\Parser\Lexer;
use PhpLayout\Parser\TokenType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    #[Test]
    public function itTokenizesEmptyInput(): void
    {
        $lexer = new Lexer('');
        $tokens = $lexer->tokenize();

        self::assertCount(1, $tokens);
        self::assertSame(TokenType::Eof, $tokens[0]->type);
    }

    #[Test]
    public function itTokenizesLayoutDeclaration(): void
    {
        $lexer = new Lexer('@layout base {');
        $tokens = $lexer->tokenize();

        self::assertSame(TokenType::Layout, $tokens[0]->type);
        self::assertSame('@layout', $tokens[0]->value);
        self::assertSame(TokenType::Identifier, $tokens[1]->type);
        self::assertSame('base', $tokens[1]->value);
        self::assertSame(TokenType::BraceOpen, $tokens[2]->type);
    }

    #[Test]
    public function itTokenizesExtends(): void
    {
        $lexer = new Lexer('@layout page extends base {');
        $tokens = $lexer->tokenize();

        self::assertSame(TokenType::Layout, $tokens[0]->type);
        self::assertSame(TokenType::Identifier, $tokens[1]->type);
        self::assertSame('page', $tokens[1]->value);
        self::assertSame(TokenType::Extends, $tokens[2]->type);
        self::assertSame(TokenType::Identifier, $tokens[3]->type);
        self::assertSame('base', $tokens[3]->value);
    }

    #[Test]
    public function itTokenizesSlotReference(): void
    {
        $lexer = new Lexer('[header]');
        $tokens = $lexer->tokenize();

        self::assertSame(TokenType::BracketOpen, $tokens[0]->type);
        self::assertSame('[header]', $tokens[0]->value);
    }

    #[Test]
    public function itTokenizesGridLines(): void
    {
        $input = <<<'GRID'
+----------+-----------+
|  header  |  header   |
+----------+-----------+
GRID;

        $lexer = new Lexer($input);
        $tokens = $lexer->tokenize();

        $gridTokens = array_filter($tokens, fn ($t) => $t->type === TokenType::GridLine);
        self::assertCount(3, $gridTokens);
    }

    #[Test]
    public function itTokenizesProperties(): void
    {
        $input = <<<'PROPS'
component: Logo
width: 120px
PROPS;

        $lexer = new Lexer($input);
        $tokens = $lexer->tokenize();

        $propTokens = array_filter($tokens, fn ($t) => $t->type === TokenType::Property);
        $propTokens = array_values($propTokens);

        self::assertCount(2, $propTokens);
        self::assertSame('component:Logo', $propTokens[0]->value);
        self::assertSame('width:120px', $propTokens[1]->value);
    }

    #[Test]
    public function itTokenizesContainerMarker(): void
    {
        $lexer = new Lexer('...');
        $tokens = $lexer->tokenize();

        self::assertSame(TokenType::Container, $tokens[0]->type);
        self::assertSame('...', $tokens[0]->value);
    }

    #[Test]
    public function itSkipsComments(): void
    {
        $input = <<<'INPUT'
@layout base {
  # This is a comment
  [header]
}
INPUT;

        $lexer = new Lexer($input);
        $tokens = $lexer->tokenize();

        $types = array_map(fn ($t) => $t->type, $tokens);
        self::assertNotContains(TokenType::Identifier, array_filter(
            $tokens,
            fn ($t) => str_contains($t->value, 'comment')
        ));
    }

    #[Test]
    public function itTokenizesCompleteLayout(): void
    {
        $input = <<<'LAYOUT'
@layout page extends base {
  [head] {
    +----------+------------------+
    |  logo    |       nav        |
    +----------+------------------+
  }

  [logo]
    component: Logo
    width: 120px

  [nav]
    component: MainNav

  [content]
    ...
}
LAYOUT;

        $lexer = new Lexer($input);
        $tokens = $lexer->tokenize();

        // Verify key tokens exist
        $types = array_map(fn ($t) => $t->type, $tokens);

        self::assertContains(TokenType::Layout, $types);
        self::assertContains(TokenType::Extends, $types);
        self::assertContains(TokenType::BraceOpen, $types);
        self::assertContains(TokenType::BraceClose, $types);
        self::assertContains(TokenType::BracketOpen, $types);
        self::assertContains(TokenType::GridLine, $types);
        self::assertContains(TokenType::Property, $types);
        self::assertContains(TokenType::Container, $types);
    }
}
