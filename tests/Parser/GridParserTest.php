<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Parser;

use PhpLayout\Ast\ResponsiveOperatorType;
use PhpLayout\Parser\GridParser;
use PhpLayout\Parser\ParseException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GridParserTest extends TestCase
{
    private GridParser $parser;

    protected function setUp(): void
    {
        $this->parser = new GridParser();
    }

    #[Test]
    public function itParsesEmptyInput(): void
    {
        $grid = $this->parser->parse([]);

        self::assertSame([], $grid->rows);
    }

    #[Test]
    public function itParsesSingleCellGrid(): void
    {
        $lines = [
            '+----------+',
            '|  header  |',
            '+----------+',
        ];

        $grid = $this->parser->parse($lines);

        self::assertCount(1, $grid->rows);
        self::assertCount(1, $grid->rows[0]->cells);
        self::assertSame('header', $grid->rows[0]->cells[0]->name);
        self::assertSame(1, $grid->rows[0]->cells[0]->columnSpan);
    }

    #[Test]
    public function itParsesMultipleColumns(): void
    {
        $lines = [
            '+----------+------------------+---------+',
            '|  logo    |       nav        |  auth   |',
            '+----------+------------------+---------+',
        ];

        $grid = $this->parser->parse($lines);

        self::assertCount(1, $grid->rows);
        self::assertCount(3, $grid->rows[0]->cells);
        self::assertSame('logo', $grid->rows[0]->cells[0]->name);
        self::assertSame('nav', $grid->rows[0]->cells[1]->name);
        self::assertSame('auth', $grid->rows[0]->cells[2]->name);
    }

    #[Test]
    public function itParsesMultipleRows(): void
    {
        $lines = [
            '+----------+-----------+',
            '|  header  |  header   |',
            '+----------+-----------+',
            '| sidebar  |  content  |',
            '+----------+-----------+',
            '|  footer  |  footer   |',
            '+----------+-----------+',
        ];

        $grid = $this->parser->parse($lines);

        self::assertCount(3, $grid->rows);
        self::assertSame('header', $grid->rows[0]->cells[0]->name);
        self::assertSame('sidebar', $grid->rows[1]->cells[0]->name);
        self::assertSame('content', $grid->rows[1]->cells[1]->name);
        self::assertSame('footer', $grid->rows[2]->cells[0]->name);
    }

    #[Test]
    public function itExtractsSlotNames(): void
    {
        $lines = [
            '+----------+-----------+',
            '|  header  |  header   |',
            '+----------+-----------+',
            '| sidebar  |  content  |',
            '+----------+-----------+',
        ];

        $grid = $this->parser->parse($lines);
        $names = $grid->getSlotNames();

        self::assertContains('header', $names);
        self::assertContains('sidebar', $names);
        self::assertContains('content', $names);
    }

    #[Test]
    public function itHandlesSpanningCells(): void
    {
        $lines = [
            '+----------+-----------+',
            '|       header         |',
            '+----------+-----------+',
            '| sidebar  |  content  |',
            '+----------+-----------+',
        ];

        $grid = $this->parser->parse($lines);

        self::assertCount(2, $grid->rows);
        self::assertCount(1, $grid->rows[0]->cells);
        self::assertSame('header', $grid->rows[0]->cells[0]->name);
        self::assertSame(2, $grid->rows[0]->cells[0]->columnSpan);
    }

    #[Test]
    public function itIgnoresEmptyLines(): void
    {
        $lines = [
            '',
            '+----------+',
            '|  header  |',
            '+----------+',
            '',
        ];

        $grid = $this->parser->parse($lines);

        self::assertCount(1, $grid->rows);
        self::assertSame('header', $grid->rows[0]->cells[0]->name);
    }

    #[Test]
    public function itParsesNestRightOperator(): void
    {
        // Explicit | before >>sm operator
        $lines = [
            '+-----------|------------|>>sm-----+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);

        self::assertTrue($grid->hasResponsiveOperators());

        // The >> operator is after the | at position 25, so it applies to aside column (boundary 2)
        $asideBoundary = $grid->columnBoundaries[2];
        self::assertCount(1, $asideBoundary->operators);
        self::assertSame(ResponsiveOperatorType::NestRight, $asideBoundary->operators[0]->type);
        self::assertSame('sm', $asideBoundary->operators[0]->breakpoint);
    }

    #[Test]
    public function itParsesNestRightOperatorWithSeparator(): void
    {
        // Same as above - explicit | is now required
        $lines = [
            '+-----------|------------|>>sm-----+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);

        self::assertTrue($grid->hasResponsiveOperators());
        // Operator after | at position 25, applies to aside column (boundary 2)
        $asideBoundary = $grid->columnBoundaries[2];
        self::assertCount(1, $asideBoundary->operators);
        self::assertSame(ResponsiveOperatorType::NestRight, $asideBoundary->operators[0]->type);
        self::assertSame('sm', $asideBoundary->operators[0]->breakpoint);
    }

    #[Test]
    public function itParsesHideOperator(): void
    {
        // Explicit | before !sm operator
        $lines = [
            '+-----------|------------|!sm------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);

        // Operator after | at position 25, applies to aside column (boundary 2)
        $asideBoundary = $grid->columnBoundaries[2];
        self::assertCount(1, $asideBoundary->operators);
        self::assertSame(ResponsiveOperatorType::Hide, $asideBoundary->operators[0]->type);
        self::assertSame('sm', $asideBoundary->operators[0]->breakpoint);
    }

    #[Test]
    public function itParsesFoldDownOperator(): void
    {
        // Explicit | before >sm operator
        $lines = [
            '+-----------|------------|>sm------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);

        $asideBoundary = $grid->columnBoundaries[2];
        self::assertCount(1, $asideBoundary->operators);
        self::assertSame(ResponsiveOperatorType::FoldDown, $asideBoundary->operators[0]->type);
    }

    #[Test]
    public function itParsesCombinedOperators(): void
    {
        // Explicit | before combined operators
        $lines = [
            '+-----------|------------|>>sm!md--+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);

        $asideBoundary = $grid->columnBoundaries[2];
        self::assertCount(2, $asideBoundary->operators);
        self::assertSame(ResponsiveOperatorType::NestRight, $asideBoundary->operators[0]->type);
        self::assertSame('sm', $asideBoundary->operators[0]->breakpoint);
        self::assertSame(ResponsiveOperatorType::Hide, $asideBoundary->operators[1]->type);
        self::assertSame('md', $asideBoundary->operators[1]->breakpoint);
    }

    #[Test]
    public function itParsesOperatorWithTarget(): void
    {
        // Explicit | before >>sm:content operator
        $lines = [
            '+-----------|------------|>>sm:content--+',
            '| nav       | content    | aside        |',
            '+-----------|------------|-------------+',
        ];

        $grid = $this->parser->parse($lines);

        $asideBoundary = $grid->columnBoundaries[2];
        self::assertCount(1, $asideBoundary->operators);
        self::assertSame('content', $asideBoundary->operators[0]->target);
    }

    #[Test]
    public function itParsesAllOperatorTypes(): void
    {
        // Test each operator type individually with explicit |
        $testCases = [
            ['>>sm', ResponsiveOperatorType::NestRight],
            ['<<sm', ResponsiveOperatorType::NestLeft],
            ['>sm', ResponsiveOperatorType::FoldDown],
            ['<sm', ResponsiveOperatorType::FoldUp],
            ['!sm', ResponsiveOperatorType::Hide],
        ];

        foreach ($testCases as [$operator, $expectedType]) {
            $lines = [
                "+-----------|------------|{$operator}---+",
                '| nav       | content    | aside   |',
                '+-----------|------------|---------+',
            ];

            $grid = $this->parser->parse($lines);
            // Operator after | at position 25, applies to aside column (boundary 2)
            $asideBoundary = $grid->columnBoundaries[2];

            self::assertCount(1, $asideBoundary->operators, "Failed for operator: {$operator}");
            self::assertSame($expectedType, $asideBoundary->operators[0]->type, "Failed for operator: {$operator}");
        }
    }

    #[Test]
    public function itTracksRowBoundaries(): void
    {
        $lines = [
            '+----------------------------------+',
            '|               header             |',
            '+-----------|------------|>>sm-----+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);

        // Row boundaries track horizontal separators
        self::assertCount(3, $grid->rowBoundaries);
        // Column operators are on column boundaries, not row boundaries
        // Row boundaries currently don't have operators (reserved for future row-level operators)
        self::assertFalse($grid->rowBoundaries[1]->hasOperators());
    }

    #[Test]
    public function itReportsReferencedBreakpoints(): void
    {
        // Explicit | before operators
        $lines = [
            '+-----------|------------|>>sm!md--+',
            '| nav       | content    | aside   |',
            '+-----------|------------|<<lg-----+',
        ];

        $grid = $this->parser->parse($lines);

        $breakpoints = $grid->getReferencedBreakpoints();
        self::assertContains('sm', $breakpoints);
        self::assertContains('md', $breakpoints);
        self::assertContains('lg', $breakpoints);
    }

    #[Test]
    public function itThrowsOnConflictingStructuralOperators(): void
    {
        // Explicit | before conflicting operators
        $lines = [
            '+-----------|------------|>>sm<<sm-+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Conflicting responsive operators at breakpoint "sm"');

        $this->parser->parse($lines);
    }

    #[Test]
    public function itThrowsOnStructuralAndHideAtSameBreakpoint(): void
    {
        // Explicit | before conflicting operators
        $lines = [
            '+-----------|------------|>>sm!sm--+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Conflicting responsive operators at breakpoint "sm"');

        $this->parser->parse($lines);
    }

    #[Test]
    public function itAllowsDifferentOperatorsAtDifferentBreakpoints(): void
    {
        // Explicit | before combined operators
        $lines = [
            '+-----------|------------|>>sm!md--+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        // Should not throw - different breakpoints are allowed
        $grid = $this->parser->parse($lines);

        $asideBoundary = $grid->columnBoundaries[2];
        self::assertCount(2, $asideBoundary->operators);
    }
}
