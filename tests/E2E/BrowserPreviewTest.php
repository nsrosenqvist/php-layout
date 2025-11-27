<?php

declare(strict_types=1);

namespace PhpLayout\Tests\E2E;

use PhpLayout\Component\BoxComponent;
use PhpLayout\Component\ComponentRegistry;
use PhpLayout\Generator\CssGenerator;
use PhpLayout\Generator\HtmlGenerator;
use PhpLayout\Parser\LayoutParser;
use PhpLayout\Resolver\LayoutResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * E2E test that generates a complete HTML page for browser preview.
 */
final class BrowserPreviewTest extends TestCase
{
    private const OUTPUT_DIR = __DIR__ . '/../../output';

    #[Test]
    public function it_generates_complete_html_page(): void
    {
        $layout = <<<'LAYOUT'
@layout page {
  +------------------------------------------+
  |                 header                   |
  +------------+-----------------------------+
  |  sidebar   |           content           |
  +------------+-----------------------------+
  |                 footer                   |
  +------------------------------------------+

  [header]
    height: 70px
    background: #2563eb
    padding: 0 20px
    align: center

  [sidebar]
    width: 250px
    background: #f1f5f9
    padding: 20px

  [content]
    padding: 30px

  [footer]
    height: 60px
    background: #1e293b
    padding: 0 20px
    align: center
}

@layout dashboard extends page {
  [content] {
    +-------------+-------------+-------------+
    |   card1     |   card2     |   card3     |
    +-------------+-------------+-------------+
    |             stats                       |
    +-------------+-------------+-------------+
  }

  [card1]
    background: #dbeafe
    padding: 20px
    border-radius: 8px
    margin: 0 10px 20px 0

  [card2]
    background: #dcfce7
    padding: 20px
    border-radius: 8px
    margin: 0 10px 20px 10px

  [card3]
    background: #fef3c7
    padding: 20px
    border-radius: 8px
    margin: 0 0 20px 10px

  [stats]
    background: #f8fafc
    padding: 30px
    border-radius: 8px
    border: 1px solid #e2e8f0
}
LAYOUT;

        // Parse and resolve
        $parser = new LayoutParser();
        $layouts = $parser->parse($layout);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('dashboard');

        // Generate CSS
        $cssGenerator = new CssGenerator();
        $css = $cssGenerator->generate($resolved, 'layout');

        // Generate HTML with box components
        $box = new BoxComponent();
        $htmlGenerator = new HtmlGenerator();

        $components = new ComponentRegistry();
        $components->register('box', $box);

        $components
            ->setContent('header', $box->render(
                ['align' => 'center', 'justify' => 'space-between'],
                '<span style="color: white; font-weight: bold; font-size: 1.5rem;">Dashboard</span>
                 <nav style="display: flex; gap: 20px;">
                   <a href="#" style="color: white; text-decoration: none;">Home</a>
                   <a href="#" style="color: white; text-decoration: none;">Reports</a>
                   <a href="#" style="color: white; text-decoration: none;">Settings</a>
                 </nav>'
            ))
            ->setContent('sidebar', $box->render(
                [],
                '<h3 style="margin: 0 0 20px 0; color: #334155;">Navigation</h3>
                 <ul style="list-style: none; padding: 0; margin: 0;">
                   <li style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">📊 Overview</li>
                   <li style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">📈 Analytics</li>
                   <li style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">👥 Users</li>
                   <li style="padding: 10px 0;">⚙️ Settings</li>
                 </ul>'
            ))
            ->setContent('card1', $box->render(
                [],
                '<h4 style="margin: 0 0 10px 0; color: #1e40af;">Revenue</h4>
                 <p style="font-size: 2rem; font-weight: bold; margin: 0; color: #1e3a8a;">$45,231</p>
                 <p style="color: #3b82f6; margin: 5px 0 0 0;">↑ 12% from last month</p>'
            ))
            ->setContent('card2', $box->render(
                [],
                '<h4 style="margin: 0 0 10px 0; color: #166534;">Users</h4>
                 <p style="font-size: 2rem; font-weight: bold; margin: 0; color: #14532d;">2,543</p>
                 <p style="color: #22c55e; margin: 5px 0 0 0;">↑ 8% from last month</p>'
            ))
            ->setContent('card3', $box->render(
                [],
                '<h4 style="margin: 0 0 10px 0; color: #a16207;">Orders</h4>
                 <p style="font-size: 2rem; font-weight: bold; margin: 0; color: #78350f;">1,832</p>
                 <p style="color: #eab308; margin: 5px 0 0 0;">↑ 3% from last month</p>'
            ))
            ->setContent('stats', $box->render(
                [],
                '<h3 style="margin: 0 0 20px 0; color: #334155;">Recent Activity</h3>
                 <p style="color: #64748b; margin: 0;">This is where detailed statistics and charts would go.
                 The layout system handles the structure while components handle the content.</p>'
            ))
            ->setContent('footer', $box->render(
                ['align' => 'center', 'justify' => 'center'],
                '<span style="color: #94a3b8;">© 2025 PHP Layout Engine Demo</span>'
            ));

        $html = $htmlGenerator->generate($resolved, $components, 'layout');

        // Build complete HTML page
        $page = $this->buildHtmlPage($html, $css, $resolved);

        // Write to output directory
        $this->ensureOutputDirectory();
        $outputPath = self::OUTPUT_DIR . '/dashboard.html';
        file_put_contents($outputPath, $page);

        // Assertions
        self::assertFileExists($outputPath);
        self::assertStringContainsString('<!DOCTYPE html>', $page);
        self::assertStringContainsString('display: grid', $page);
        self::assertStringContainsString('grid-template-areas', $page);
        self::assertStringContainsString('Dashboard', $page);
        self::assertStringContainsString('$45,231', $page);
    }

    #[Test]
    public function it_generates_simple_centered_layout(): void
    {
        $layout = <<<'LAYOUT'
@layout centered {
  +------------------------------------------+
  |                 header                   |
  +------------------------------------------+
  |                 content                  |
  +------------------------------------------+
  |                 footer                   |
  +------------------------------------------+

  [header]
    height: 60px
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)
    padding: 0 20px
    align: center
    justify: center

  [content]
    max-width: 800px
    padding: 60px 20px
    align: center
    justify: center

  [footer]
    height: 50px
    background: #1a1a2e
    align: center
    justify: center
}
LAYOUT;

        $parser = new LayoutParser();
        $layouts = $parser->parse($layout);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('centered');

        $cssGenerator = new CssGenerator();
        $css = $cssGenerator->generate($resolved, 'layout');

        $box = new BoxComponent();
        $htmlGenerator = new HtmlGenerator();

        $components = new ComponentRegistry();
        $components
            ->setContent('header', $box->render(
                [],
                '<h1 style="color: white; margin: 0; font-size: 1.25rem;">PHP Layout Engine</h1>'
            ))
            ->setContent('content', $box->render(
                ['align' => 'center', 'justify' => 'center'],
                '<div style="text-align: center;">
                   <h2 style="font-size: 3rem; margin: 0 0 20px 0; color: #1a1a2e;">
                     Visual Layout Definition
                   </h2>
                   <p style="font-size: 1.25rem; color: #64748b; max-width: 600px; margin: 0 auto 30px;">
                     Define your layouts using ASCII box-drawing syntax.
                     What you see is what you get.
                   </p>
                   <pre style="background: #f1f5f9; padding: 20px; border-radius: 8px; text-align: left; overflow-x: auto;"><code>+----------+-----------+
|  header  |  header   |
+----------+-----------+
| sidebar  |  content  |
+----------+-----------+</code></pre>
                 </div>'
            ))
            ->setContent('footer', $box->render(
                [],
                '<span style="color: #64748b;">Built with PHP 8.4</span>'
            ));

        $html = $htmlGenerator->generate($resolved, $components, 'layout');
        $page = $this->buildHtmlPage($html, $css, $resolved, 'PHP Layout - Centered');

        $this->ensureOutputDirectory();
        $outputPath = self::OUTPUT_DIR . '/centered.html';
        file_put_contents($outputPath, $page);

        self::assertFileExists($outputPath);
        self::assertStringContainsString('Visual Layout Definition', $page);
    }

    private function buildHtmlPage(
        string $bodyHtml,
        string $layoutCss,
        \PhpLayout\Ast\ResolvedLayout $resolved,
        string $title = 'PHP Layout Demo',
    ): string {
        // Generate additional CSS for slot styling
        $slotCss = $this->generateSlotStyles($resolved, 'layout');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title}</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
      min-height: 100vh;
      background: #f8fafc;
    }

    /* Layout Grid */
    {$layoutCss}

    /* Slot Styles */
    {$slotCss}
  </style>
</head>
<body>
{$bodyHtml}
</body>
</html>
HTML;
    }

    private function generateSlotStyles(\PhpLayout\Ast\ResolvedLayout $resolved, string $prefix): string
    {
        $css = [];

        foreach ($resolved->slots as $slot) {
            $selector = '.' . $prefix . '__' . $slot->name;
            $styles = $this->propertiesToCss($slot->properties);
            if ($styles !== '') {
                $css[] = $selector . ' { ' . $styles . ' }';
            }
        }

        return implode("\n    ", $css);
    }

    /**
     * @param array<string, string> $properties
     */
    private function propertiesToCss(array $properties): string
    {
        $cssProps = [];
        $allowedProps = [
            'width',
            'height',
            'min-width',
            'max-width',
            'min-height',
            'max-height',
            'padding',
            'margin',
            'background',
            'border',
            'border-radius',
        ];

        foreach ($allowedProps as $prop) {
            if (isset($properties[$prop])) {
                $cssProps[] = $prop . ': ' . $properties[$prop];
            }
        }

        // Auto-center elements with max-width if no margin is explicitly set
        if (isset($properties['max-width']) && !isset($properties['margin'])) {
            $cssProps[] = 'margin-left: auto';
            $cssProps[] = 'margin-right: auto';
        }

        // Handle alignment
        if (isset($properties['align']) || isset($properties['justify'])) {
            $cssProps[] = 'display: flex';
            if (isset($properties['align'])) {
                $cssProps[] = 'align-items: ' . $properties['align'];
            }
            if (isset($properties['justify'])) {
                $cssProps[] = 'justify-content: ' . $properties['justify'];
            }
        }

        return implode('; ', $cssProps);
    }

    private function ensureOutputDirectory(): void
    {
        if (!is_dir(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0755, true);
        }
    }
}
