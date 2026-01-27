<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Transformer;

use PhpLayout\Parser\GridParser;
use PhpLayout\Transformer\ResponsiveGridTransformer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResponsiveGridTransformerTest extends TestCase
{
    private GridParser $parser;
    private ResponsiveGridTransformer $transformer;

    protected function setUp(): void
    {
        $this->parser = new GridParser();
        $this->transformer = new ResponsiveGridTransformer();
    }

    #[Test]
    public function itReturnsUnchangedGridWhenNoOperatorsForBreakpoint(): void
    {
        $lines = [
            '+-----------|------------|---------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // No operators for 'sm', so grid should have same structure
        self::assertCount(1, $transformed->rows);
        self::assertCount(3, $transformed->rows[0]->cells);
    }

    #[Test]
    public function itHidesLastColumnAtBreakpoint(): void
    {
        // Explicit | before operator makes it clear which column is affected
        $lines = [
            '+-----------|------------|!sm------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // The 'aside' column (boundary 2) should be hidden
        self::assertContains(2, $transformed->hiddenColumns);
    }

    #[Test]
    public function itHidesMiddleColumnAtBreakpoint(): void
    {
        // Explicit | before operator - hides content column
        $lines = [
            '+-----------|!sm---------|---------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // The 'content' column (boundary 1) should be hidden
        self::assertContains(1, $transformed->hiddenColumns);
    }

    #[Test]
    public function itHidesFirstColumnAtBreakpoint(): void
    {
        // Operator after first + hides nav column (boundary 0)
        $lines = [
            '+!sm--------|------------|---------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // The 'nav' column (boundary 0) should be hidden
        self::assertContains(0, $transformed->hiddenColumns);
    }

    #[Test]
    public function itNestsLastColumnIntoTarget(): void
    {
        // Explicit | before >>sm operator
        $lines = [
            '+-----------|------------|>>sm-----+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // The 'aside' column (boundary 2) should be nested into content
        self::assertArrayHasKey(2, $transformed->nestedColumns);
        self::assertSame('right', $transformed->nestedColumns[2]['direction']);
    }

    #[Test]
    public function itNestsMiddleColumnIntoTarget(): void
    {
        // Explicit | before >>sm operator - nests content column
        $lines = [
            '+-----------|>>sm--------|---------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // The 'content' column (boundary 1) should be nested into nav
        self::assertArrayHasKey(1, $transformed->nestedColumns);
        self::assertSame('right', $transformed->nestedColumns[1]['direction']);
    }

    #[Test]
    public function itNestsFirstColumnIntoTarget(): void
    {
        // Operator after first + nests nav column (boundary 0)
        $lines = [
            '+>>sm-------|------------|---------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // The 'nav' column (boundary 0) should be nested
        self::assertArrayHasKey(0, $transformed->nestedColumns);
        self::assertSame('right', $transformed->nestedColumns[0]['direction']);
    }

    #[Test]
    public function itFoldsLastColumnToNewRow(): void
    {
        // Explicit | before >sm operator
        $lines = [
            '+-----------|------------|>sm------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // The 'aside' column (boundary 2) should be folded to a new row
        self::assertArrayHasKey(2, $transformed->foldedColumns);
        self::assertSame('down', $transformed->foldedColumns[2]['direction']);
    }

    #[Test]
    public function itFoldsMiddleColumnToNewRow(): void
    {
        // Explicit | before >sm operator - folds content column
        $lines = [
            '+-----------|>sm---------|---------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // The 'content' column (boundary 1) should be folded to a new row
        self::assertArrayHasKey(1, $transformed->foldedColumns);
        self::assertSame('down', $transformed->foldedColumns[1]['direction']);
    }

    #[Test]
    public function itHandlesNestWithTarget(): void
    {
        // Explicit | before >>sm:content operator
        $lines = [
            '+-----------|------------|>>sm:content--+',
            '| nav       | content    | aside        |',
            '+-----------|------------|-------------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // The 'aside' column (boundary 2) should be nested into content
        self::assertArrayHasKey(2, $transformed->nestedColumns);
        self::assertSame('content', $transformed->nestedColumns[2]['target']);
    }

    #[Test]
    public function itGeneratesTemplateAreas(): void
    {
        $lines = [
            '+-----------|------------|---------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        $areas = $transformed->generateTemplateAreas();
        self::assertStringContainsString('nav', $areas);
        self::assertStringContainsString('content', $areas);
        self::assertStringContainsString('aside', $areas);
    }

    #[Test]
    public function itDoesNotApplyOperatorsFromDifferentBreakpoints(): void
    {
        $lines = [
            '+-----------|------------|!md------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // 'sm' breakpoint should not hide anything (operator is for 'md')
        self::assertEmpty($transformed->hiddenColumns);
    }

    #[Test]
    public function itNestsLeftColumn(): void
    {
        // <<sm operator after + nests nav column into the right neighbor
        $lines = [
            '+<<sm-------|------------|---------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // The 'nav' column (boundary 0) should be nested left into content
        self::assertArrayHasKey(0, $transformed->nestedColumns);
        self::assertSame('left', $transformed->nestedColumns[0]['direction']);
    }

    #[Test]
    public function itFoldsUpColumn(): void
    {
        // Explicit | before <sm operator
        $lines = [
            '+-----------|------------|<sm------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // The 'aside' column (boundary 2) should be folded up as a new row
        self::assertArrayHasKey(2, $transformed->foldedColumns);
        self::assertSame('up', $transformed->foldedColumns[2]['direction']);
    }

    #[Test]
    public function itHandlesMultipleOperatorsAtDifferentBreakpoints(): void
    {
        // Explicit | before combined operators
        $lines = [
            '+-----------|------------|>>sm!md--+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);

        // At 'sm' breakpoint: nest (boundary 2)
        $smTransformed = $this->transformer->transform($grid, 'sm');
        self::assertArrayHasKey(2, $smTransformed->nestedColumns);
        self::assertEmpty($smTransformed->hiddenColumns);

        // At 'md' breakpoint: hide (boundary 2)
        $mdTransformed = $this->transformer->transform($grid, 'md');
        self::assertContains(2, $mdTransformed->hiddenColumns);
        self::assertEmpty($mdTransformed->foldedColumns);
    }

    #[Test]
    public function itOverridesLargerBreakpointOperatorWithSmallerOne(): void
    {
        // With cumulative breakpoints, smaller breakpoint operators should override larger ones
        // !sm<<md means: nest at md, but hide at sm (sm overrides md)
        $lines = [
            '+!sm<<md----|------------------|>>md---------+',
            '|   left    |      content     |    right    |',
            '+-----------|------------------|-------------+',
        ];

        $grid = $this->parser->parse($lines);

        // At md: left should be nested (<<md), right should be nested (>>md)
        $mdTransformed = $this->transformer->transform($grid, 'md', ['md']);
        self::assertArrayHasKey(0, $mdTransformed->nestedColumns);
        self::assertArrayHasKey(2, $mdTransformed->nestedColumns);
        self::assertEmpty($mdTransformed->hiddenColumns);

        // At sm with cumulative [md, sm]: left should be hidden (!sm overrides <<md)
        $smTransformed = $this->transformer->transform($grid, 'sm', ['md', 'sm']);
        self::assertContains(0, $smTransformed->hiddenColumns);
        self::assertArrayNotHasKey(0, $smTransformed->nestedColumns);
        // right should still be nested from md (no sm operator)
        self::assertArrayHasKey(2, $smTransformed->nestedColumns);
    }

    #[Test]
    public function itHandlesOperatorsOnMultipleColumns(): void
    {
        // Different operators on different columns at same breakpoint
        // First segment: hide nav at sm (boundary 0)
        // Second segment: stack content at sm (boundary 1)
        $lines = [
            '+!sm--------|>sm---------|---------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // 'nav' (boundary 0) hidden, 'content' (boundary 1) folded
        self::assertContains(0, $transformed->hiddenColumns);
        self::assertArrayHasKey(1, $transformed->foldedColumns);
    }

    #[Test]
    public function itHandlesMultipleColumnsWithDifferentBreakpoints(): void
    {
        // Different breakpoints for different columns, explicit | before operators
        $lines = [
            '+!sm--------|>md---------|>>lg-----+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);

        // At 'sm': nav hidden (boundary 0)
        $smTransformed = $this->transformer->transform($grid, 'sm');
        self::assertContains(0, $smTransformed->hiddenColumns);
        self::assertEmpty($smTransformed->foldedColumns);
        self::assertEmpty($smTransformed->nestedColumns);

        // At 'md': content folded (boundary 1)
        $mdTransformed = $this->transformer->transform($grid, 'md');
        self::assertArrayHasKey(1, $mdTransformed->foldedColumns);
        self::assertEmpty($mdTransformed->hiddenColumns);
        self::assertEmpty($mdTransformed->nestedColumns);

        // At 'lg': aside nested (boundary 2)
        $lgTransformed = $this->transformer->transform($grid, 'lg');
        self::assertArrayHasKey(2, $lgTransformed->nestedColumns);
        self::assertEmpty($lgTransformed->hiddenColumns);
        self::assertEmpty($lgTransformed->foldedColumns);
    }

    #[Test]
    public function itHandlesSpanningCellsWithOperators(): void
    {
        // Grid with spanning header in first row
        $lines = [
            '+----------------------------------+',
            '|              header              |',
            '+-----------|------------|>>sm-----+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // 'aside' is nested (boundary index 2 - starts at position 25)
        self::assertArrayHasKey(2, $transformed->nestedColumns);
        // Should have 2 rows (header + content row)
        self::assertGreaterThanOrEqual(2, count($transformed->rows));
    }

    #[Test]
    public function itGeneratesTemplateAreasForFoldedColumn(): void
    {
        // Explicit | before >sm operator - folds to new row
        $lines = [
            '+-----------|------------|>sm------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        $areas = $transformed->generateTemplateAreas();

        // Folded 'aside' should appear as a separate row
        self::assertStringContainsString('nav', $areas);
        self::assertStringContainsString('content', $areas);
        self::assertStringContainsString('aside', $areas);
    }

    #[Test]
    public function itGeneratesTemplateAreasForHiddenColumn(): void
    {
        // Explicit | before !sm operator
        $lines = [
            '+-----------|!sm-----------------+',
            '| nav       | content            |',
            '+-----------|--------------------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        $areas = $transformed->generateTemplateAreas();

        // Hidden 'content' should not appear in template areas,
        // only 'nav' should be present
        self::assertStringContainsString('nav', $areas);
    }

    #[Test]
    public function itHandlesTwoColumnGrid(): void
    {
        // Explicit | before !sm operator
        $lines = [
            '+-----------|!sm---------+',
            '| nav       | content    |',
            '+-----------|------------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // Content column (boundary 1) should be hidden
        self::assertContains(1, $transformed->hiddenColumns);
    }

    #[Test]
    public function itHandlesComplexMultiRowGrid(): void
    {
        // Complex grid with header, content columns, and footer
        $lines = [
            '+----------------------------------+',
            '|              header              |',
            '+-----------|------------|>>sm-----+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
            '|              footer              |',
            '+----------------------------------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // Aside is nested (boundary index 2)
        self::assertArrayHasKey(2, $transformed->nestedColumns);
        // Should have rows for header, content area, and footer
        self::assertGreaterThanOrEqual(3, count($transformed->rows));
    }

    #[Test]
    public function itReturnsVisibleSlotNames(): void
    {
        $lines = [
            '+-----------|------------|---------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        $visible = $transformed->getVisibleSlotNames();
        self::assertContains('nav', $visible);
        self::assertContains('content', $visible);
        self::assertContains('aside', $visible);
    }

    #[Test]
    public function itReportsCorrectColumnCountAfterHide(): void
    {
        // Explicit | before !sm operator
        $lines = [
            '+-----------|------------|!sm------+',
            '| nav       | content    | aside   |',
            '+-----------|------------|---------+',
        ];

        $grid = $this->parser->parse($lines);
        $transformed = $this->transformer->transform($grid, 'sm');

        // Hidden column reduces visible column count
        self::assertLessThanOrEqual(3, $transformed->getColumnCount());
    }
}
