<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Parser;

use PhpLayout\Parser\GridParser;
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
    public function it_parses_empty_input(): void
    {
        $grid = $this->parser->parse([]);

        self::assertSame([], $grid->rows);
    }

    #[Test]
    public function it_parses_single_cell_grid(): void
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
    public function it_parses_multiple_columns(): void
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
    public function it_parses_multiple_rows(): void
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
    public function it_extracts_slot_names(): void
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
    public function it_handles_spanning_cells(): void
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
    public function it_ignores_empty_lines(): void
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
}
