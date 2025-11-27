<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Generator;

use PhpLayout\Generator\CssGenerator;
use PhpLayout\Parser\LayoutParser;
use PhpLayout\Resolver\LayoutResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CssGeneratorTest extends TestCase
{
    private LayoutParser $parser;
    private CssGenerator $cssGenerator;

    protected function setUp(): void
    {
        $this->parser = new LayoutParser();
        $this->cssGenerator = new CssGenerator();
    }

    #[Test]
    public function it_generates_simple_grid_css(): void
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

        $css = $this->cssGenerator->generate($resolved);

        self::assertStringContainsString('display: grid', $css);
        self::assertStringContainsString('grid-template-areas:', $css);
        self::assertStringContainsString('"content"', $css);
        self::assertStringContainsString('grid-area: content', $css);
    }

    #[Test]
    public function it_generates_multi_column_grid(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+-----------+
  | sidebar  |  content  |
  +----------+-----------+

  [sidebar]
    width: 200px

  [content]
    component: Content
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $css = $this->cssGenerator->generate($resolved);

        self::assertStringContainsString('"sidebar content"', $css);
        self::assertStringContainsString('grid-template-columns: 200px 1fr', $css);
    }

    #[Test]
    public function it_generates_multi_row_grid(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+
  |  header  |
  +----------+
  |  content |
  +----------+
  |  footer  |
  +----------+

  [header]
    height: 80px

  [content]
    component: Content

  [footer]
    height: 50px
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $css = $this->cssGenerator->generate($resolved);

        self::assertStringContainsString('"header"', $css);
        self::assertStringContainsString('"content"', $css);
        self::assertStringContainsString('"footer"', $css);
        self::assertStringContainsString('grid-template-rows: 80px auto 50px', $css);
    }

    #[Test]
    public function it_generates_spanning_cells(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+-----------+
  |       header         |
  +----------+-----------+
  | sidebar  |  content  |
  +----------+-----------+

  [header]
    height: 80px

  [sidebar]
    width: 200px

  [content]
    component: Content
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $css = $this->cssGenerator->generate($resolved);

        self::assertStringContainsString('"header header"', $css);
        self::assertStringContainsString('"sidebar content"', $css);
    }

    #[Test]
    public function it_uses_custom_container_class(): void
    {
        $input = <<<'LAYOUT'
@layout base {
  +----------+
  |  content |
  +----------+
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('base');

        $css = $this->cssGenerator->generate($resolved, 'my-layout');

        self::assertStringContainsString('.my-layout {', $css);
        self::assertStringContainsString('.my-layout__content {', $css);
    }
}
