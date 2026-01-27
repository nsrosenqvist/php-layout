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
    public function itGeneratesSimpleGridCss(): void
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
    public function itGeneratesMultiColumnGrid(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+-----------+
  | sidebar  |  content  |
  +----------+-----------+

  [sidebar]
    grid-width: 200px

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
    public function itGeneratesMultiRowGrid(): void
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
    grid-height: 80px

  [content]
    component: Content

  [footer]
    grid-height: 50px
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
    public function itGeneratesSpanningCells(): void
    {
        $input = <<<'LAYOUT'
@layout page {
  +----------+-----------+
  |       header         |
  +----------+-----------+
  | sidebar  |  content  |
  +----------+-----------+

  [header]
    grid-height: 80px

  [sidebar]
    grid-width: 200px

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

        $css = $this->cssGenerator->generate($resolved, 'my-layout');

        self::assertStringContainsString('.my-layout {', $css);
        self::assertStringContainsString('.my-layout__content {', $css);
    }

    #[Test]
    public function itGeneratesResponsiveMediaQueries(): void
    {
        $input = <<<'LAYOUT'
@breakpoints {
  sm: 600px
}

@layout page {
  +-----------|------------!sm-------+
  | nav       | content    | aside   |
  +-----------|------------|---------+

  [nav]
    grid-width: 200px

  [content]
    component: Content

  [aside]
    grid-width: 300px
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $css = $this->cssGenerator->generate($resolved);

        // Should have base grid
        self::assertStringContainsString('"nav content aside"', $css);

        // Should have media query for sm breakpoint
        self::assertStringContainsString('@media (max-width: 600px)', $css);
    }

    #[Test]
    public function itGeneratesStackingMediaQuery(): void
    {
        $input = <<<'LAYOUT'
@breakpoints {
  sm: 480px
}

@layout page {
  +-----------|------------>sm-------+
  | nav       | content    | aside   |
  +-----------|------------|---------+
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $css = $this->cssGenerator->generate($resolved);

        // Should have media query with stacking
        self::assertStringContainsString('@media (max-width: 480px)', $css);
        self::assertStringContainsString('grid-template-areas:', $css);
    }

    #[Test]
    public function itSortsBreakpointsLargestFirst(): void
    {
        $input = <<<'LAYOUT'
@breakpoints {
  sm: 300px
  md: 600px
  lg: 900px
}

@layout page {
  +-----------|------------!sm!md!lg-+
  | nav       | content    | aside   |
  +-----------|------------|---------+
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $css = $this->cssGenerator->generate($resolved);

        // Desktop-first: largest breakpoint should come first
        $lgPos = strpos($css, '@media (max-width: 900px)');
        $mdPos = strpos($css, '@media (max-width: 600px)');
        $smPos = strpos($css, '@media (max-width: 300px)');

        self::assertNotFalse($lgPos);
        self::assertNotFalse($mdPos);
        self::assertNotFalse($smPos);
        self::assertLessThan($mdPos, $lgPos);
        self::assertLessThan($smPos, $mdPos);
    }

    #[Test]
    public function itGeneratesFoldedLayoutInMediaQuery(): void
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

        $css = $this->cssGenerator->generate($resolved);

        // Should have media query with transformed grid areas
        self::assertStringContainsString('@media (max-width: 480px)', $css);
        self::assertStringContainsString('grid-template-areas:', $css);
    }

    #[Test]
    public function itGeneratesHiddenElementStyle(): void
    {
        $input = <<<'LAYOUT'
@breakpoints {
  sm: 480px
}

@layout page {
  +-----------|------------!sm-------+
  | nav       | content    | aside   |
  +-----------|------------|---------+
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $css = $this->cssGenerator->generate($resolved);

        // Should have media query
        self::assertStringContainsString('@media (max-width: 480px)', $css);
    }

    #[Test]
    public function itHandlesComplexResponsiveLayout(): void
    {
        $input = <<<'LAYOUT'
@breakpoints {
  sm: 480px
  md: 768px
}

@layout page {
  +----------------------------------+
  |              header              |
  +!sm----------|------------>>md----+
  | nav         | content    | aside |
  +-------------|------------+-------+
  |              footer              |
  +----------------------------------+
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $css = $this->cssGenerator->generate($resolved);

        // Should have both breakpoints
        self::assertStringContainsString('@media (max-width: 768px)', $css);
        self::assertStringContainsString('@media (max-width: 480px)', $css);
    }

    #[Test]
    public function itGeneratesBaseLayoutWithoutOperators(): void
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

        $css = $this->cssGenerator->generate($resolved);

        // Base layout (desktop) should have all three columns
        // Look for the base grid-template-areas before media queries
        $baseMatch = preg_match('/grid-template-areas:\s*\n\s*"nav content aside"/', $css);
        self::assertSame(1, $baseMatch, 'Base layout should have nav content aside in one row');
    }

    #[Test]
    public function itGeneratesNestedContentWrapperCss(): void
    {
        $input = <<<'LAYOUT'
@breakpoints {
  md: 768px
}

@layout page {
  +<<md------+----------+
  |  nav     |  main    |
  +----------+----------+
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $css = $this->cssGenerator->generate($resolved);

        // Should have CSS to hide nested wrapper by default
        self::assertStringContainsString('.layout__main--nested-nav-md', $css);
        self::assertStringContainsString('display: none', $css);

        // Should have media query to show nested wrapper at breakpoint
        self::assertStringContainsString('@media (max-width: 768px)', $css);

        // Check the structure: nested wrapper hidden by default, shown at breakpoint
        // First occurrence should be the hiding rule
        $hiddenPos = strpos($css, '.layout__main--nested-nav-md');
        self::assertNotFalse($hiddenPos);

        // Find display: none after the class
        $afterClass = substr($css, $hiddenPos);
        self::assertStringContainsString('display: none', $afterClass);

        // Also should have display: block in media query
        self::assertStringContainsString('display: block', $css);
    }

    #[Test]
    public function itCopiesSourceSlotStylesToNestedWrapper(): void
    {
        $input = <<<'LAYOUT'
@breakpoints {
  md: 768px
}

@layout page {
  +<<md------+----------+
  |  nav     |  main    |
  +----------+----------+

  [nav]
    background: #ede9fe
    padding: 20px
    border-radius: 8px

  [main]
    background: white
    padding: 32px
}
LAYOUT;

        $layouts = $this->parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $css = $this->cssGenerator->generate($resolved);

        // The nested wrapper should include the source slot's visual styles
        self::assertStringContainsString('.layout__main--nested-nav-md', $css);

        // Extract the nested wrapper rule
        $nestedPos = strpos($css, '.layout__main--nested-nav-md');
        self::assertNotFalse($nestedPos);

        $afterNested = substr($css, $nestedPos);
        $ruleEnd = strpos($afterNested, '}');
        self::assertNotFalse($ruleEnd);

        $nestedRule = substr($afterNested, 0, $ruleEnd + 1);

        // Should have copied visual styles from nav
        self::assertStringContainsString('background: #ede9fe', $nestedRule);
        self::assertStringContainsString('padding: 20px', $nestedRule);
        self::assertStringContainsString('border-radius: 8px', $nestedRule);
    }
}
