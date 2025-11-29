<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Generator;

use PhpLayout\Component\ComponentRegistry;
use PhpLayout\Generator\HtmlGenerator;
use PhpLayout\Parser\LayoutParser;
use PhpLayout\Resolver\LayoutResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HtmlGeneratorTest extends TestCase
{
    private LayoutParser $parser;
    private HtmlGenerator $htmlGenerator;

    protected function setUp(): void
    {
        $this->parser = new LayoutParser();
        $this->htmlGenerator = new HtmlGenerator();
    }

    #[Test]
    public function itGeneratesSimpleGridHtml(): void
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

        $components = new ComponentRegistry();
        $html = $this->htmlGenerator->generate($resolved, $components);

        self::assertStringContainsString('<div class="layout">', $html);
        self::assertStringContainsString('<div class="layout__content">', $html);
        self::assertStringContainsString('<!-- Content -->', $html);
    }

    #[Test]
    public function itGeneratesMultiSlotHtml(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+-----------+
  | sidebar  |  content  |
  +----------+-----------+

  [sidebar]
    component: Sidebar

  [content]
    component: Content
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $components = new ComponentRegistry();
        $html = $this->htmlGenerator->generate($resolved, $components);

        self::assertStringContainsString('<div class="layout__sidebar">', $html);
        self::assertStringContainsString('<div class="layout__content">', $html);
        self::assertStringContainsString('<!-- Sidebar -->', $html);
        self::assertStringContainsString('<!-- Content -->', $html);
    }

    #[Test]
    public function itInsertsComponentContent(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+

  [content]
    component: Content
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $components = new ComponentRegistry();
        $components->setContent('Content', '<p>Hello World</p>');
        $html = $this->htmlGenerator->generate($resolved, $components);

        self::assertStringContainsString('<p>Hello World</p>', $html);
    }

    #[Test]
    public function itGeneratesContainerSlotPlaceholder(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+

  [content]
    component: ...
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $components = new ComponentRegistry();
        $html = $this->htmlGenerator->generate($resolved, $components);

        self::assertStringContainsString('<!-- slot: content -->', $html);
    }

    #[Test]
    public function itUsesCustomContainerClass(): void
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

        $components = new ComponentRegistry();
        $html = $this->htmlGenerator->generate($resolved, $components, 'my-page');

        self::assertStringContainsString('<div class="my-page">', $html);
        self::assertStringContainsString('<div class="my-page__content">', $html);
    }

    #[Test]
    public function itGeneratesNestedGrids(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+
  |   head   |
  +----------+

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

        $components = new ComponentRegistry();
        $components
            ->setContent('Logo', '<img src="logo.png">')
            ->setContent('Nav', '<nav>Menu</nav>');
        $html = $this->htmlGenerator->generate($resolved, $components);

        self::assertStringContainsString('<img src="logo.png">', $html);
        self::assertStringContainsString('<nav>Menu</nav>', $html);
    }

    #[Test]
    public function itRendersContentBySlotName(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+
  |  header  |
  +----------+
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $components = new ComponentRegistry();
        $components->setContent('header', '<header>My Header</header>');
        $html = $this->htmlGenerator->generate($resolved, $components);

        self::assertStringContainsString('<header>My Header</header>', $html);
    }

    #[Test]
    public function itUsesDefaultComponentForSlotsWithoutExplicitComponent(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+
  |  header  |
  +----------+

  [header]
    background: blue
    padding: 20px
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $components = new ComponentRegistry();
        $components
            ->register('box', new \PhpLayout\Component\BoxComponent())
            ->setDefaultComponent('box');

        $html = $this->htmlGenerator->generate($resolved, $components);

        // Default component should render the slot with its properties
        self::assertStringContainsString('background: blue', $html);
        self::assertStringContainsString('padding: 20px', $html);
    }

    #[Test]
    public function itPrefersExplicitComponentOverDefault(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+
  |  header  |
  +----------+

  [header]
    component: custom
    color: red
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $customComponent = new class () implements \PhpLayout\Component\ComponentInterface {
            public function render(array $properties, string $content = ''): string
            {
                return '<custom>' . ($properties['color'] ?? 'none') . '</custom>';
            }
        };

        $components = new ComponentRegistry();
        $components
            ->register('box', new \PhpLayout\Component\BoxComponent())
            ->register('custom', $customComponent)
            ->setDefaultComponent('box');

        $html = $this->htmlGenerator->generate($resolved, $components);

        // Explicit component should be used, not default
        self::assertStringContainsString('<custom>red</custom>', $html);
    }
}
