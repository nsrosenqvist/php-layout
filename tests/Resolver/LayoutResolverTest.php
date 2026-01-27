<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Resolver;

use PhpLayout\Parser\LayoutParser;
use PhpLayout\Resolver\LayoutResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LayoutResolverTest extends TestCase
{
    private LayoutParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LayoutParser();
    }

    #[Test]
    public function itResolvesSimpleLayout(): void
    {
        $input = <<<'LAYOUT'
@layout base {
  +----------+
  |  content |
  +----------+

  [content]
    component: Content
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('base');

        self::assertSame('base', $resolved->name);
        self::assertNotNull($resolved->grid);
        self::assertArrayHasKey('content', $resolved->slots);
        self::assertSame('Content', $resolved->slots['content']->getComponent());
    }

    #[Test]
    public function itResolvesInheritance(): void
    {
        $input = <<<'LAYOUT'
@layout base {
  +----------+
  |  content |
  +----------+

  [content]
    component: ...
}

@layout page extends base {
  [content]
    component: Page
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        self::assertSame('page', $resolved->name);
        self::assertNotNull($resolved->grid);
        self::assertSame('Page', $resolved->slots['content']->getComponent());
    }

    #[Test]
    public function itInheritsGridFromParent(): void
    {
        $input = <<<'LAYOUT'
@layout base {
  +----------+-----------+
  |  header  |  header   |
  +----------+-----------+
  | sidebar  |  content  |
  +----------+-----------+
}

@layout page extends base {
  [header]
    component: Header

  [sidebar]
    component: Sidebar

  [content]
    component: Content
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        self::assertNotNull($resolved->grid);
        self::assertCount(2, $resolved->grid->rows);
    }

    #[Test]
    public function itMergesPropertiesFromInheritance(): void
    {
        $input = <<<'LAYOUT'
@layout base {
  +----------+
  |  sidebar |
  +----------+

  [sidebar]
    width: 200px
}

@layout page extends base {
  [sidebar]
    component: Sidebar
    background: blue
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $sidebar = $resolved->slots['sidebar'];
        self::assertSame('200px', $sidebar->properties['width']);
        self::assertSame('Sidebar', $sidebar->properties['component']);
        self::assertSame('blue', $sidebar->properties['background']);
    }

    #[Test]
    public function itResolvesNestedSlots(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  [head] {
    +----------+----------+
    |  logo    |   nav    |
    +----------+----------+
  }

  [logo]
    component: Logo

  [nav]
    component: Nav
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $head = $resolved->slots['head'];
        self::assertNotNull($head->grid);
        self::assertTrue($head->hasChildren());
        self::assertArrayHasKey('logo', $head->children);
        self::assertArrayHasKey('nav', $head->children);
    }

    #[Test]
    public function itResolvesDeepInheritance(): void
    {
        $input = <<<'LAYOUT'
@layout base {
  +----------+
  |  content |
  +----------+

  [content]
    component: ...
}

@layout page extends base {
  [content]
    grid-width: 800px
}

@layout article extends page {
  [content]
    component: Article
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('article');

        self::assertSame('article', $resolved->name);
        $content = $resolved->slots['content'];
        self::assertSame('800px', $content->properties['grid-width']);
        self::assertSame('Article', $content->getComponent());
    }

    #[Test]
    public function itThrowsOnMissingLayout(): void
    {
        $input = '@layout base {}';
        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Layout 'missing' not found");
        $resolver->resolve('missing');
    }

    #[Test]
    public function itThrowsOnMissingParent(): void
    {
        $input = '@layout page extends missing {}';
        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Parent layout 'missing' not found");
        $resolver->resolve('page');
    }

    #[Test]
    public function itInheritsBreakpointsFromParent(): void
    {
        $input = <<<'LAYOUT'
@breakpoints {
  sm: 480px
  md: 768px
}

@layout base {
  +----------+
  | content  |
  +----------+
}

@layout page extends base {
  [content]
    component: Article
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        // Child should inherit breakpoints from global scope
        self::assertArrayHasKey('sm', $resolved->breakpoints);
        self::assertArrayHasKey('md', $resolved->breakpoints);
        self::assertSame('480px', $resolved->breakpoints['sm']->value);
    }

    #[Test]
    public function itMergesBreakpointsInInheritance(): void
    {
        $input = <<<'LAYOUT'
@breakpoints {
  sm: 480px
}

@layout base {
  +----------+
  | content  |
  +----------+
}

@layout page extends base {}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);

        $base = $resolver->resolve('base');
        $page = $resolver->resolve('page');

        // Both should have the global breakpoints
        self::assertCount(1, $base->breakpoints);
        self::assertCount(1, $page->breakpoints);
    }

    #[Test]
    public function itResolvesLayoutWithResponsiveOperators(): void
    {
        $input = <<<'LAYOUT'
@breakpoints {
  sm: 480px
}

@layout page {
  +-----------|------------>>sm------+
  | nav       | content    | aside   |
  +-----------|------------|---------+
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        // Grid should have responsive operators
        self::assertNotNull($resolved->grid);
        self::assertTrue($resolved->grid->hasResponsiveOperators());
        self::assertContains('sm', $resolved->grid->getReferencedBreakpoints());
    }
}
