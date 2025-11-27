<?php

declare(strict_types=1);

namespace PhpLayout\Tests;

use PhpLayout\Component\ComponentRegistry;
use PhpLayout\Generator\CssGenerator;
use PhpLayout\Generator\HtmlGenerator;
use PhpLayout\Parser\LayoutParser;
use PhpLayout\Resolver\LayoutResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration test demonstrating the full workflow.
 */
final class IntegrationTest extends TestCase
{
    #[Test]
    public function it_parses_and_generates_full_website_layout(): void
    {
        $input = file_get_contents(__DIR__ . '/../examples/website.lyt');
        self::assertIsString($input);

        // Parse
        $parser = new LayoutParser();
        $layouts = $parser->parse($input);

        self::assertCount(3, $layouts);
        self::assertSame('base', $layouts[0]->name);
        self::assertSame('page', $layouts[1]->name);
        self::assertSame('article', $layouts[2]->name);

        // Resolve
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('article');

        self::assertSame('article', $resolved->name);
        self::assertNotNull($resolved->grid);

        // Check inheritance worked
        self::assertArrayHasKey('head', $resolved->slots);
        self::assertArrayHasKey('body', $resolved->slots);
        self::assertArrayHasKey('foot', $resolved->slots);
        self::assertArrayHasKey('logo', $resolved->slots);
        self::assertArrayHasKey('content', $resolved->slots);
        self::assertArrayHasKey('breadcrumbs', $resolved->slots);

        // Generate CSS
        $cssGenerator = new CssGenerator();
        $css = $cssGenerator->generate($resolved, 'article-page');

        self::assertStringContainsString('.article-page {', $css);
        self::assertStringContainsString('display: grid', $css);
        self::assertStringContainsString('grid-template-areas:', $css);

        // Generate HTML
        $components = new ComponentRegistry();
        $components
            ->setContent('Logo', '<img src="/logo.png" alt="Logo">')
            ->setContent('MainNav', '<nav><a href="/">Home</a></nav>')
            ->setContent('AuthButtons', '<button>Login</button>')
            ->setContent('Sidebar', '<aside>Sidebar</aside>')
            ->setContent('Breadcrumbs', '<nav>Home > Article</nav>')
            ->setContent('ArticleHeader', '<h1>Article Title</h1>')
            ->setContent('ArticleBody', '<p>Article content...</p>')
            ->setContent('Comments', '<section>Comments</section>')
            ->setContent('FooterNav', '<nav>Footer links</nav>')
            ->setContent('Copyright', '<p>&copy; 2025</p>');

        $htmlGenerator = new HtmlGenerator();
        $html = $htmlGenerator->generate($resolved, $components, 'article-page');

        self::assertStringContainsString('<div class="article-page">', $html);
        self::assertStringContainsString('<img src="/logo.png" alt="Logo">', $html);
        self::assertStringContainsString('<h1>Article Title</h1>', $html);
    }

    #[Test]
    public function it_demonstrates_slot_inheritance(): void
    {
        $input = <<<'LAYOUT'
@layout base {
  +----------+
  |  content |
  +----------+

  [content]
    padding: 20px
    ...
}

@layout page extends base {
  [content]
    background: white
}

@layout article extends page {
  [content]
    component: Article
    max-width: 800px
}
LAYOUT;

        $parser = new LayoutParser();
        $layouts = $parser->parse($input);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('article');

        $content = $resolved->slots['content'];

        // All properties should be merged
        self::assertSame('20px', $content->properties['padding']);
        self::assertSame('white', $content->properties['background']);
        self::assertSame('Article', $content->properties['component']);
        self::assertSame('800px', $content->properties['max-width']);
    }
}
