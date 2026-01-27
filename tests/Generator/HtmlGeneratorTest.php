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

    #[Test]
    public function itNestsContentWithNestLeftOperator(): void
    {
        // Test: nav nests INTO main using << (nest left = into right neighbor)
        $input = <<<'LAYOUT'
@breakpoints {
  md: 768px
}

@layout page {
  +<<md------+----------+
  |  nav     |  main    |
  +----------+----------+

  [nav]
    component: nav

  [main]
    component: main
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $components = new ComponentRegistry();
        $components
            ->setContent('nav', '<nav>Navigation</nav>')
            ->setContent('main', '<main>Content</main>');

        $html = $this->htmlGenerator->generate($resolved, $components);

        // Original slot content should be present
        self::assertStringContainsString('<nav>Navigation</nav>', $html);
        self::assertStringContainsString('<main>Content</main>', $html);

        // nav has <<md = nest left = nav flows into main (its right neighbor)
        // So nav's content should appear nested inside main
        self::assertStringContainsString('layout__main--nested-nav-md', $html);
    }

    #[Test]
    public function itNestsContentWithNestRightOperator(): void
    {
        // Test: sidebar nests INTO main using >> (nest right = into left neighbor)
        $input = <<<'LAYOUT'
@breakpoints {
  md: 768px
}

@layout page {
  +----------+>>md------+
  |  main    |  sidebar |
  +----------+----------+

  [main]
    component: main

  [sidebar]
    component: sidebar
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $components = new ComponentRegistry();
        $components
            ->setContent('main', '<main>Content</main>')
            ->setContent('sidebar', '<aside>Sidebar</aside>');

        $html = $this->htmlGenerator->generate($resolved, $components);

        // Original slot content should be present
        self::assertStringContainsString('<main>Content</main>', $html);
        self::assertStringContainsString('<aside>Sidebar</aside>', $html);

        // sidebar has >>md = nest right = sidebar flows into main (its left neighbor)
        // So sidebar's content should appear nested inside main
        self::assertStringContainsString('layout__main--nested-sidebar-md', $html);
    }

    #[Test]
    public function itPrependsContentForNestLeftDirection(): void
    {
        // << means content flows left (into right neighbor), which should PREPEND
        // because the nested content visually "comes from the left"
        $input = <<<'LAYOUT'
@breakpoints {
  md: 768px
}

@layout page {
  +<<md------+----------+
  |  nav     |  main    |
  +----------+----------+

  [nav]
    component: nav

  [main]
    component: main
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $components = new ComponentRegistry();
        $components
            ->setContent('nav', '<nav>Navigation</nav>')
            ->setContent('main', '<main>Content</main>');

        $html = $this->htmlGenerator->generate($resolved, $components);

        // For nest left (<<), the nested content should be PREPENDED
        // Find positions in the main div
        $mainDivStart = strpos($html, '<div class="layout__main">');
        self::assertNotFalse($mainDivStart, 'Main div should exist');

        // Get content after main div starts
        $afterMainDiv = substr($html, $mainDivStart);

        // Nested content (from nav nesting into main) should appear BEFORE main's own content
        $nestedPos = strpos($afterMainDiv, 'layout__main--nested-nav-md');
        $ownContentPos = strpos($afterMainDiv, '<main>Content</main>');

        self::assertNotFalse($nestedPos, 'Nested wrapper should exist');
        self::assertNotFalse($ownContentPos, 'Own content should exist');
        self::assertLessThan($ownContentPos, $nestedPos, 'For nest left (<<), nested content should appear BEFORE own content');
    }

    #[Test]
    public function itAppendsContentForNestRightDirection(): void
    {
        // >> means content flows right (into left neighbor), which should APPEND
        // because the nested content visually "comes from the right"
        $input = <<<'LAYOUT'
@breakpoints {
  md: 768px
}

@layout page {
  +----------+>>md------+
  |  main    |  sidebar |
  +----------+----------+

  [main]
    component: main

  [sidebar]
    component: sidebar
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $components = new ComponentRegistry();
        $components
            ->setContent('main', '<main>Content</main>')
            ->setContent('sidebar', '<aside>Sidebar</aside>');

        $html = $this->htmlGenerator->generate($resolved, $components);

        // For nest right (>>), the nested content should be APPENDED
        // Find positions in the main div
        $mainDivStart = strpos($html, '<div class="layout__main">');
        self::assertNotFalse($mainDivStart, 'Main div should exist');

        // Get content after main div starts
        $afterMainDiv = substr($html, $mainDivStart);

        // Main's own content should appear BEFORE nested content (from sidebar nesting into main)
        $nestedPos = strpos($afterMainDiv, 'layout__main--nested-sidebar-md');
        $ownContentPos = strpos($afterMainDiv, '<main>Content</main>');

        self::assertNotFalse($nestedPos, 'Nested wrapper should exist');
        self::assertNotFalse($ownContentPos, 'Own content should exist');
        self::assertLessThan($nestedPos, $ownContentPos, 'For nest right (>>), own content should appear BEFORE nested content');
    }
}
