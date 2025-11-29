<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Parser;

use PhpLayout\Parser\LayoutParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LayoutParserTest extends TestCase
{
    private LayoutParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LayoutParser();
    }

    #[Test]
    public function itParsesEmptyLayout(): void
    {
        $input = '@layout empty {}';
        $layouts = $this->parser->parse($input);

        self::assertCount(1, $layouts);
        self::assertSame('empty', $layouts[0]->name);
        self::assertNull($layouts[0]->extends);
        self::assertNull($layouts[0]->grid);
        self::assertSame([], $layouts[0]->slots);
    }

    #[Test]
    public function itParsesLayoutWithExtends(): void
    {
        $input = '@layout page extends base {}';
        $layouts = $this->parser->parse($input);

        self::assertCount(1, $layouts);
        self::assertSame('page', $layouts[0]->name);
        self::assertSame('base', $layouts[0]->extends);
    }

    #[Test]
    public function itParsesLayoutWithGrid(): void
    {
        $input = <<<'LAYOUT'
@layout base {
  +----------+-----------+
  |  header  |  header   |
  +----------+-----------+
  | sidebar  |  content  |
  +----------+-----------+
}
LAYOUT;

        $layouts = $this->parser->parse($input);

        self::assertCount(1, $layouts);
        self::assertNotNull($layouts[0]->grid);
        self::assertCount(2, $layouts[0]->grid->rows);
    }

    #[Test]
    public function itParsesSlotDefinitions(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  [header]
    component: Header
    height: 80px

  [sidebar]
    component: Sidebar
    width: 200px
}
LAYOUT;

        $layouts = $this->parser->parse($input);

        self::assertArrayHasKey('header', $layouts[0]->slots);
        self::assertArrayHasKey('sidebar', $layouts[0]->slots);

        $header = $layouts[0]->slots['header'];
        self::assertSame('Header', $header->getComponent());
        self::assertSame('80px', $header->properties['height']);

        $sidebar = $layouts[0]->slots['sidebar'];
        self::assertSame('Sidebar', $sidebar->getComponent());
        self::assertSame('200px', $sidebar->properties['width']);
    }

    #[Test]
    public function itParsesContainerMarker(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  [content]
    component: ...
}
LAYOUT;

        $layouts = $this->parser->parse($input);

        self::assertTrue($layouts[0]->slots['content']->isContainer);
        self::assertFalse($layouts[0]->slots['content']->hasComponent());
    }

    #[Test]
    public function itParsesLegacyContainerMarker(): void
    {
        // Legacy standalone ... syntax still works for backward compatibility
        $input = <<<'LAYOUT'
@layout page {
  [content]
    ...
}
LAYOUT;

        $layouts = $this->parser->parse($input);

        self::assertTrue($layouts[0]->slots['content']->isContainer);
        self::assertFalse($layouts[0]->slots['content']->hasComponent());
    }

    #[Test]
    public function itParsesNestedGridInSlot(): void
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
}
LAYOUT;

        $layouts = $this->parser->parse($input);

        $head = $layouts[0]->slots['head'];
        self::assertNotNull($head->nestedGrid);
        self::assertCount(1, $head->nestedGrid->rows);
        self::assertCount(2, $head->nestedGrid->rows[0]->cells);
    }

    #[Test]
    public function itParsesCompleteLayout(): void
    {
        $input = <<<'LAYOUT'
@layout page extends base {
  [head] {
    +----------+------------------+---------+
    |  logo    |       nav        |  auth   |
    +----------+------------------+---------+
  }

  [body] {
    +----------+-----------------------+
    | sidebar  |        content        |
    +----------+-----------------------+
  }

  [foot] {
    +--------------+--------------------+
    |  footer_nav  |     copyright      |
    +--------------+--------------------+
  }

  [logo]
    component: Logo
    width: 120px

  [nav]
    component: MainNav

  [auth]
    component: AuthButtons
    width: 150px

  [sidebar]
    component: Sidebar
    grid-width: 220px

  [content]
    component: ...

  [footer_nav]
    component: FooterNav

  [copyright]
    component: Copyright
}
LAYOUT;

        $layouts = $this->parser->parse($input);

        self::assertCount(1, $layouts);
        $layout = $layouts[0];

        self::assertSame('page', $layout->name);
        self::assertSame('base', $layout->extends);

        // Check slots
        self::assertArrayHasKey('head', $layout->slots);
        self::assertArrayHasKey('body', $layout->slots);
        self::assertArrayHasKey('foot', $layout->slots);
        self::assertArrayHasKey('logo', $layout->slots);
        self::assertArrayHasKey('nav', $layout->slots);
        self::assertArrayHasKey('content', $layout->slots);

        // Check nested grids
        self::assertNotNull($layout->slots['head']->nestedGrid);
        self::assertNotNull($layout->slots['body']->nestedGrid);
        self::assertNotNull($layout->slots['foot']->nestedGrid);

        // Check components
        self::assertSame('Logo', $layout->slots['logo']->getComponent());
        self::assertSame('MainNav', $layout->slots['nav']->getComponent());
        self::assertTrue($layout->slots['content']->isContainer);
    }

    #[Test]
    public function itParsesMultipleLayouts(): void
    {
        $input = <<<'LAYOUT'
@layout base {
  +----------+
  |  content |
  +----------+
}

@layout page extends base {
  [content]
    component: Page
}
LAYOUT;

        $layouts = $this->parser->parse($input);

        self::assertCount(2, $layouts);
        self::assertSame('base', $layouts[0]->name);
        self::assertSame('page', $layouts[1]->name);
    }
}
