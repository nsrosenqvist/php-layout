<?php

declare(strict_types=1);

namespace PhpLayout\Tests\E2E;

use PhpLayout\Ast\ResolvedLayout;
use PhpLayout\Component\BoxComponent;
use PhpLayout\Component\ComponentRegistry;
use PhpLayout\Generator\CssGenerator;
use PhpLayout\Generator\HtmlGenerator;
use PhpLayout\Parser\LayoutParser;
use PhpLayout\Resolver\LayoutResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * E2E test that generates a complete responsive HTML page for browser preview.
 * Demonstrates ALL responsive layout features:
 * - `!breakpoint` - Hide column at breakpoint
 * - `>breakpoint` - Stack column down at breakpoint
 * - `>>breakpoint` - Fold column right at breakpoint
 * - `<<breakpoint` - Fold column left at breakpoint
 * - `>>breakpoint:target` - Fold column into specific slot
 */
final class ResponsivePreviewTest extends TestCase
{
    private const OUTPUT_DIR = __DIR__ . '/../../output';

    #[Test]
    public function itGeneratesResponsiveDashboard(): void
    {
        // This layout demonstrates:
        // - sidebar hidden on mobile (!sm)
        // - cards stack on tablet (>md)
        // - aside folds under content on smaller screens (>>lg)
        $layout = <<<'LAYOUT'
@breakpoints {
  sm: 480px
  md: 768px
  lg: 1024px
  xl: 1280px
}

@layout responsive-dashboard {
  +----------------------------------------------+
  |                   header                     |
  +!sm----------|----------------------|>>lg-----+
  |  sidebar    |       main           | aside   |
  +-------------|----------------------|---------+
  |                   footer                     |
  +----------------------------------------------+

  [header]
    grid-height: 64px
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%)
    padding: 0 24px
    align: center

  [sidebar]
    grid-width: 220px
    background: #0f172a
    padding: 24px 16px

  [main]
    padding: 24px
    background: #f8fafc

  [aside]
    grid-width: 280px
    background: #f1f5f9
    padding: 24px
    border-left: 1px solid #e2e8f0

  [footer]
    grid-height: 56px
    background: #0f172a
    padding: 0 24px
    align: center
}

@layout dashboard-content extends responsive-dashboard {
  [main] {
    +>md----------|>md---------|>md---------+
    |   card1     |   card2    |   card3    |
    +-------------|------------|------------+
    |              activity                 |
    +-------------|------------|------------+
    |   quick1    |  quick2    |  quick3    |
    +-------------|------------|------------+
  }

  [card1]
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%)
    padding: 20px
    border-radius: 12px
    margin: 0 8px 16px 0

  [card2]
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%)
    padding: 20px
    border-radius: 12px
    margin: 0 8px 16px 8px

  [card3]
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%)
    padding: 20px
    border-radius: 12px
    margin: 0 0 16px 8px

  [activity]
    background: white
    padding: 24px
    border-radius: 12px
    border: 1px solid #e2e8f0
    margin: 0 0 16px 0

  [quick1]
    background: #faf5ff
    padding: 16px
    border-radius: 8px
    margin: 0 8px 0 0

  [quick2]
    background: #fdf4ff
    padding: 16px
    border-radius: 8px
    margin: 0 8px 0 8px

  [quick3]
    background: #fff7ed
    padding: 16px
    border-radius: 8px
    margin: 0 0 0 8px
}
LAYOUT;

        $resolved = $this->parseAndResolve($layout, 'dashboard-content');
        $components = $this->createDashboardComponents();
        $page = $this->generatePage($resolved, $components, 'Responsive Dashboard');

        $this->ensureOutputDirectory();
        $outputPath = self::OUTPUT_DIR . '/responsive-dashboard.html';
        file_put_contents($outputPath, $page);

        self::assertFileExists($outputPath);
        self::assertStringContainsString('@media', $page);
        self::assertStringContainsString('max-width: 480px', $page);
        self::assertStringContainsString('max-width: 768px', $page);
        self::assertStringContainsString('max-width: 1024px', $page);
    }

    #[Test]
    public function itGeneratesHolyGrailLayout(): void
    {
        // Classic Holy Grail layout with responsive behavior:
        // - Both sidebars fold on tablet (<<md and >>md)
        // - Left sidebar hides on mobile (!sm)
        $layout = <<<'LAYOUT'
@breakpoints {
  sm: 480px
  md: 768px
  lg: 1024px
}

@layout holy-grail {
  +----------------------------------------------+
  |                   header                     |
  +!sm<<md------|------------------|>>md---------+
  |    left     |      content     |    right    |
  +-------------|------------------|-------------+
  |                   footer                     |
  +----------------------------------------------+

  [header]
    grid-height: 70px
    background: #7c3aed
    padding: 0 24px
    align: center

  [left]
    grid-width: 200px
    background: #ede9fe
    padding: 20px

  [content]
    padding: 32px
    background: white

  [right]
    grid-width: 240px
    background: #f5f3ff
    padding: 20px

  [footer]
    grid-height: 60px
    background: #5b21b6
    padding: 0 24px
    align: center
}
LAYOUT;

        $resolved = $this->parseAndResolve($layout, 'holy-grail');
        $components = $this->createHolyGrailComponents();
        $page = $this->generatePage($resolved, $components, 'Holy Grail Layout');

        $this->ensureOutputDirectory();
        $outputPath = self::OUTPUT_DIR . '/holy-grail.html';
        file_put_contents($outputPath, $page);

        self::assertFileExists($outputPath);
        self::assertStringContainsString('Holy Grail', $page);
    }

    #[Test]
    public function itGeneratesBlogLayout(): void
    {
        // Blog layout where sidebar stacks under content on mobile
        $layout = <<<'LAYOUT'
@breakpoints {
  sm: 480px
  md: 768px
}

@layout blog {
  +-------------------------------------+
  |              header                 |
  +-------------------------------------+
  |              hero                   |
  +-------------------------|>sm--------+
  |        articles         | sidebar   |
  +-------------------------|-----------+
  |              footer                 |
  +-------------------------------------+

  [header]
    grid-height: 60px
    background: #18181b
    padding: 0 20px
    align: center

  [hero]
    grid-height: 300px
    background: linear-gradient(180deg, #18181b 0%, #3f3f46 100%)
    padding: 60px 20px
    align: center
    justify: center

  [articles]
    padding: 32px 24px
    background: white

  [sidebar]
    grid-width: 300px
    background: #fafafa
    padding: 24px
    border-left: 1px solid #e4e4e7

  [footer]
    grid-height: 80px
    background: #18181b
    padding: 0 20px
    align: center
}
LAYOUT;

        $resolved = $this->parseAndResolve($layout, 'blog');
        $components = $this->createBlogComponents();
        $page = $this->generatePage($resolved, $components, 'Responsive Blog');

        $this->ensureOutputDirectory();
        $outputPath = self::OUTPUT_DIR . '/responsive-blog.html';
        file_put_contents($outputPath, $page);

        self::assertFileExists($outputPath);
        self::assertStringContainsString('Latest Articles', $page);
    }

    #[Test]
    public function itGeneratesProgressiveCollapseLayout(): void
    {
        // Four-column layout that progressively collapses:
        // - 4 columns on desktop
        // - 3 columns on large tablet (aside folds right)
        // - 2 columns on tablet (nav folds left)
        // - 1 column on mobile (panel hides)
        $layout = <<<'LAYOUT'
@breakpoints {
  sm: 480px
  md: 768px
  lg: 1024px
  xl: 1280px
}

@layout progressive {
  +------------------------------------------------------+
  |                       header                         |
  +<<md---------|-------------|-------------|>>lg--------+
  |    nav      |   primary   |  secondary  |   panel    |
  +-------------|-------------|-------------|-----------+
  |                       footer                         |
  +------------------------------------------------------+

  [header]
    grid-height: 56px
    background: #0ea5e9
    padding: 0 20px
    align: center

  [nav]
    grid-width: 180px
    background: #0c4a6e
    padding: 16px

  [primary]
    padding: 24px
    background: white

  [secondary]
    grid-width: 280px
    background: #f0f9ff
    padding: 20px

  [panel]
    grid-width: 220px
    background: #e0f2fe
    padding: 16px

  [footer]
    grid-height: 48px
    background: #0c4a6e
    padding: 0 20px
    align: center
}
LAYOUT;

        $resolved = $this->parseAndResolve($layout, 'progressive');
        $components = $this->createProgressiveComponents();
        $page = $this->generatePage($resolved, $components, 'Progressive Collapse');

        $this->ensureOutputDirectory();
        $outputPath = self::OUTPUT_DIR . '/progressive-collapse.html';
        file_put_contents($outputPath, $page);

        self::assertFileExists($outputPath);
        self::assertStringContainsString('Progressive', $page);
    }

    private function parseAndResolve(string $layout, string $name): ResolvedLayout
    {
        $parser = new LayoutParser();
        $layouts = $parser->parse($layout);
        $resolver = new LayoutResolver($layouts);

        return $resolver->resolve($name);
    }

    private function generatePage(
        ResolvedLayout $resolved,
        ComponentRegistry $components,
        string $title,
    ): string {
        $cssGenerator = new CssGenerator();
        $css = $cssGenerator->generate($resolved, 'layout');

        $htmlGenerator = new HtmlGenerator();
        $html = $htmlGenerator->generate($resolved, $components, 'layout');

        return $this->buildHtmlPage($html, $css, $resolved, $title);
    }

    private function createDashboardComponents(): ComponentRegistry
    {
        $box = new BoxComponent();
        $components = new ComponentRegistry();

        return $components
            ->setContent('header', $box->render(
                ['align' => 'center', 'justify' => 'space-between'],
                '<div style="display: flex; align-items: center; gap: 16px;">
                   <span style="color: white; font-weight: bold; font-size: 1.25rem;">📊 Analytics</span>
                   <span style="color: rgba(255,255,255,0.7); font-size: 0.875rem; display: none;" class="mobile-hint">
                     ← Resize window to see responsive behavior
                   </span>
                 </div>
                 <div style="display: flex; align-items: center; gap: 12px;">
                   <span style="color: white; font-size: 0.875rem;">John Doe</span>
                   <div style="width: 32px; height: 32px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">👤</div>
                 </div>'
            ))
            ->setContent('sidebar', $box->render(
                [],
                '<div style="color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 16px;">Navigation</div>
                 <nav style="display: flex; flex-direction: column; gap: 4px;">
                   <a href="#" style="color: white; text-decoration: none; padding: 10px 12px; border-radius: 6px; background: #1e40af;">📈 Dashboard</a>
                   <a href="#" style="color: #94a3b8; text-decoration: none; padding: 10px 12px; border-radius: 6px;">📊 Analytics</a>
                   <a href="#" style="color: #94a3b8; text-decoration: none; padding: 10px 12px; border-radius: 6px;">👥 Customers</a>
                   <a href="#" style="color: #94a3b8; text-decoration: none; padding: 10px 12px; border-radius: 6px;">📦 Products</a>
                   <a href="#" style="color: #94a3b8; text-decoration: none; padding: 10px 12px; border-radius: 6px;">⚙️ Settings</a>
                 </nav>
                 <div style="margin-top: auto; padding-top: 24px; border-top: 1px solid #1e293b; color: #64748b; font-size: 0.75rem;">
                   Hidden on mobile (sm)
                 </div>'
            ))
            ->setContent('aside', $box->render(
                [],
                '<h3 style="margin: 0 0 16px 0; font-size: 0.875rem; color: #475569;">Recent Activity</h3>
                 <div style="display: flex; flex-direction: column; gap: 12px;">
                   <div style="padding: 12px; background: white; border-radius: 8px; font-size: 0.875rem;">
                     <strong>New order</strong><br><span style="color: #64748b;">#12345 - $299</span>
                   </div>
                   <div style="padding: 12px; background: white; border-radius: 8px; font-size: 0.875rem;">
                     <strong>User signup</strong><br><span style="color: #64748b;">jane@example.com</span>
                   </div>
                   <div style="padding: 12px; background: white; border-radius: 8px; font-size: 0.875rem;">
                     <strong>Payment</strong><br><span style="color: #64748b;">$1,234 received</span>
                   </div>
                 </div>
                 <div style="margin-top: 16px; padding: 8px; background: #dbeafe; border-radius: 6px; font-size: 0.75rem; color: #1e40af;">
                   ℹ️ Folds below main on lg
                 </div>'
            ))
            ->setContent('card1', $box->render(
                [],
                '<div style="display: flex; justify-content: space-between; align-items: flex-start;">
                   <div>
                     <div style="color: #1e40af; font-size: 0.875rem; font-weight: 500;">Revenue</div>
                     <div style="font-size: 2rem; font-weight: bold; color: #1e3a8a; margin-top: 8px;">$45,231</div>
                     <div style="color: #22c55e; font-size: 0.875rem; margin-top: 4px;">↑ 12% from last month</div>
                   </div>
                   <div style="width: 48px; height: 48px; background: #1e40af; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">💰</div>
                 </div>'
            ))
            ->setContent('card2', $box->render(
                [],
                '<div style="display: flex; justify-content: space-between; align-items: flex-start;">
                   <div>
                     <div style="color: #166534; font-size: 0.875rem; font-weight: 500;">Users</div>
                     <div style="font-size: 2rem; font-weight: bold; color: #14532d; margin-top: 8px;">2,543</div>
                     <div style="color: #22c55e; font-size: 0.875rem; margin-top: 4px;">↑ 8% this week</div>
                   </div>
                   <div style="width: 48px; height: 48px; background: #166534; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">👥</div>
                 </div>'
            ))
            ->setContent('card3', $box->render(
                [],
                '<div style="display: flex; justify-content: space-between; align-items: flex-start;">
                   <div>
                     <div style="color: #a16207; font-size: 0.875rem; font-weight: 500;">Orders</div>
                     <div style="font-size: 2rem; font-weight: bold; color: #78350f; margin-top: 8px;">1,832</div>
                     <div style="color: #22c55e; font-size: 0.875rem; margin-top: 4px;">↑ 3% today</div>
                   </div>
                   <div style="width: 48px; height: 48px; background: #a16207; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">📦</div>
                 </div>'
            ))
            ->setContent('activity', $box->render(
                [],
                '<h3 style="margin: 0 0 16px 0; color: #1e293b;">Recent Activity</h3>
                 <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                   <div style="padding: 16px; background: #f8fafc; border-radius: 8px;">
                     <div style="font-weight: 500;">Order #12345</div>
                     <div style="color: #64748b; font-size: 0.875rem;">Completed • $299.00</div>
                   </div>
                   <div style="padding: 16px; background: #f8fafc; border-radius: 8px;">
                     <div style="font-weight: 500;">New Customer</div>
                     <div style="color: #64748b; font-size: 0.875rem;">jane@example.com</div>
                   </div>
                   <div style="padding: 16px; background: #f8fafc; border-radius: 8px;">
                     <div style="font-weight: 500;">Product Update</div>
                     <div style="color: #64748b; font-size: 0.875rem;">Widget Pro v2.0</div>
                   </div>
                 </div>'
            ))
            ->setContent('quick1', $box->render(
                [],
                '<div style="font-weight: 500; color: #7c3aed;">Quick Action</div>
                 <div style="color: #64748b; font-size: 0.875rem; margin-top: 4px;">Add Product</div>'
            ))
            ->setContent('quick2', $box->render(
                [],
                '<div style="font-weight: 500; color: #c026d3;">Quick Action</div>
                 <div style="color: #64748b; font-size: 0.875rem; margin-top: 4px;">New Order</div>'
            ))
            ->setContent('quick3', $box->render(
                [],
                '<div style="font-weight: 500; color: #ea580c;">Quick Action</div>
                 <div style="color: #64748b; font-size: 0.875rem; margin-top: 4px;">Send Report</div>'
            ))
            ->setContent('footer', $box->render(
                ['align' => 'center', 'justify' => 'space-between'],
                '<span style="color: #64748b; font-size: 0.875rem;">© 2026 Analytics Dashboard</span>
                 <span style="color: #64748b; font-size: 0.75rem;">Resize browser to see responsive behavior</span>'
            ));
    }

    private function createHolyGrailComponents(): ComponentRegistry
    {
        $box = new BoxComponent();
        $components = new ComponentRegistry();

        return $components
            ->setContent('header', $box->render(
                ['align' => 'center', 'justify' => 'space-between'],
                '<span style="color: white; font-weight: bold; font-size: 1.5rem;">🏛️ Holy Grail Layout</span>
                 <nav style="display: flex; gap: 24px;">
                   <a href="#" style="color: rgba(255,255,255,0.9); text-decoration: none;">Home</a>
                   <a href="#" style="color: rgba(255,255,255,0.9); text-decoration: none;">About</a>
                   <a href="#" style="color: rgba(255,255,255,0.9); text-decoration: none;">Contact</a>
                 </nav>'
            ))
            ->setContent('left', $box->render(
                [],
                '<h3 style="margin: 0 0 16px 0; color: #5b21b6; font-size: 0.875rem;">LEFT SIDEBAR</h3>
                 <nav style="display: flex; flex-direction: column; gap: 8px;">
                   <a href="#" style="color: #6d28d9; text-decoration: none; padding: 8px 0;">📄 Page 1</a>
                   <a href="#" style="color: #6d28d9; text-decoration: none; padding: 8px 0;">📄 Page 2</a>
                   <a href="#" style="color: #6d28d9; text-decoration: none; padding: 8px 0;">📄 Page 3</a>
                 </nav>
                 <div style="margin-top: 16px; padding: 12px; background: #ddd6fe; border-radius: 8px; font-size: 0.75rem; color: #5b21b6;">
                   ⬅️ Folds left on md<br>
                   ❌ Hidden on sm
                 </div>'
            ))
            ->setContent('content', $box->render(
                [],
                '<h1 style="margin: 0 0 16px 0; color: #1e1b4b;">Main Content Area</h1>
                 <p style="color: #64748b; line-height: 1.7; margin: 0 0 16px 0;">
                   This is the classic "Holy Grail" layout pattern. It features a header, footer,
                   and three columns in the middle. The sidebars have fixed widths while the
                   main content area expands to fill available space.
                 </p>
                 <p style="color: #64748b; line-height: 1.7; margin: 0 0 16px 0;">
                   <strong>Responsive behavior:</strong>
                 </p>
                 <ul style="color: #64748b; line-height: 1.7; margin: 0; padding-left: 20px;">
                   <li>Desktop (lg+): Three columns side by side</li>
                   <li>Tablet (md): Sidebars fold above/below content</li>
                   <li>Mobile (sm): Left sidebar hidden, right sidebar folded</li>
                 </ul>
                 <div style="margin-top: 24px; padding: 20px; background: #f5f3ff; border-radius: 12px;">
                   <h3 style="margin: 0 0 8px 0; color: #5b21b6;">Try It!</h3>
                   <p style="margin: 0; color: #7c3aed;">Resize your browser window to see the layout adapt.</p>
                 </div>'
            ))
            ->setContent('right', $box->render(
                [],
                '<h3 style="margin: 0 0 16px 0; color: #5b21b6; font-size: 0.875rem;">RIGHT SIDEBAR</h3>
                 <div style="padding: 16px; background: white; border-radius: 8px; margin-bottom: 12px;">
                   <strong style="color: #5b21b6;">Widget 1</strong>
                   <p style="margin: 8px 0 0 0; color: #64748b; font-size: 0.875rem;">Some widget content here.</p>
                 </div>
                 <div style="padding: 16px; background: white; border-radius: 8px;">
                   <strong style="color: #5b21b6;">Widget 2</strong>
                   <p style="margin: 8px 0 0 0; color: #64748b; font-size: 0.875rem;">More content here.</p>
                 </div>
                 <div style="margin-top: 16px; padding: 12px; background: #ddd6fe; border-radius: 8px; font-size: 0.75rem; color: #5b21b6;">
                   ➡️ Folds right on md
                 </div>'
            ))
            ->setContent('footer', $box->render(
                ['align' => 'center', 'justify' => 'center'],
                '<span style="color: rgba(255,255,255,0.8);">© 2026 Holy Grail Layout Demo</span>'
            ));
    }

    private function createBlogComponents(): ComponentRegistry
    {
        $box = new BoxComponent();
        $components = new ComponentRegistry();

        return $components
            ->setContent('header', $box->render(
                ['align' => 'center', 'justify' => 'space-between'],
                '<span style="color: white; font-weight: bold; font-size: 1.25rem;">✍️ The Blog</span>
                 <nav style="display: flex; gap: 20px;">
                   <a href="#" style="color: white; text-decoration: none;">Articles</a>
                   <a href="#" style="color: white; text-decoration: none;">Categories</a>
                   <a href="#" style="color: white; text-decoration: none;">About</a>
                 </nav>'
            ))
            ->setContent('hero', $box->render(
                ['align' => 'center', 'justify' => 'center'],
                '<div style="text-align: center; max-width: 600px;">
                   <h1 style="color: white; font-size: 2.5rem; margin: 0 0 16px 0;">Welcome to The Blog</h1>
                   <p style="color: rgba(255,255,255,0.8); font-size: 1.125rem; margin: 0;">
                     Thoughts on technology, design, and building great products.
                   </p>
                 </div>'
            ))
            ->setContent('articles', $box->render(
                [],
                '<h2 style="margin: 0 0 24px 0; color: #18181b;">Latest Articles</h2>
                 <article style="padding-bottom: 24px; margin-bottom: 24px; border-bottom: 1px solid #e4e4e7;">
                   <h3 style="margin: 0 0 8px 0; color: #18181b;">Building Responsive Layouts with PHP</h3>
                   <p style="color: #71717a; margin: 0 0 12px 0;">Learn how to create adaptive layouts using ASCII box-drawing syntax...</p>
                   <span style="color: #a1a1aa; font-size: 0.875rem;">December 15, 2025</span>
                 </article>
                 <article style="padding-bottom: 24px; margin-bottom: 24px; border-bottom: 1px solid #e4e4e7;">
                   <h3 style="margin: 0 0 8px 0; color: #18181b;">The Art of Component Design</h3>
                   <p style="color: #71717a; margin: 0 0 12px 0;">Exploring patterns for building reusable UI components...</p>
                   <span style="color: #a1a1aa; font-size: 0.875rem;">December 10, 2025</span>
                 </article>
                 <article>
                   <h3 style="margin: 0 0 8px 0; color: #18181b;">CSS Grid vs Flexbox: When to Use What</h3>
                   <p style="color: #71717a; margin: 0 0 12px 0;">A practical guide to choosing the right layout system...</p>
                   <span style="color: #a1a1aa; font-size: 0.875rem;">December 5, 2025</span>
                 </article>'
            ))
            ->setContent('sidebar', $box->render(
                [],
                '<h3 style="margin: 0 0 16px 0; color: #18181b; font-size: 1rem;">Categories</h3>
                 <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px;">
                   <span style="padding: 4px 12px; background: #18181b; color: white; border-radius: 100px; font-size: 0.875rem;">PHP</span>
                   <span style="padding: 4px 12px; background: #e4e4e7; color: #18181b; border-radius: 100px; font-size: 0.875rem;">CSS</span>
                   <span style="padding: 4px 12px; background: #e4e4e7; color: #18181b; border-radius: 100px; font-size: 0.875rem;">Design</span>
                 </div>
                 <h3 style="margin: 0 0 16px 0; color: #18181b; font-size: 1rem;">Newsletter</h3>
                 <p style="color: #71717a; font-size: 0.875rem; margin: 0 0 12px 0;">Get updates in your inbox.</p>
                 <input type="email" placeholder="your@email.com" style="width: 100%; padding: 10px 12px; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 8px;">
                 <button style="width: 100%; padding: 10px; background: #18181b; color: white; border: none; border-radius: 6px; cursor: pointer;">Subscribe</button>
                 <div style="margin-top: 16px; padding: 12px; background: #fef3c7; border-radius: 8px; font-size: 0.75rem; color: #92400e;">
                   📱 Stacks below articles on mobile (sm)
                 </div>'
            ))
            ->setContent('footer', $box->render(
                ['align' => 'center', 'justify' => 'center'],
                '<span style="color: rgba(255,255,255,0.7);">© 2026 The Blog. All rights reserved.</span>'
            ));
    }

    private function createProgressiveComponents(): ComponentRegistry
    {
        $box = new BoxComponent();
        $components = new ComponentRegistry();

        return $components
            ->setContent('header', $box->render(
                ['align' => 'center', 'justify' => 'space-between'],
                '<span style="color: white; font-weight: bold;">📐 Progressive Collapse Demo</span>
                 <span style="color: rgba(255,255,255,0.7); font-size: 0.875rem;">4 → 3 → 2 columns</span>'
            ))
            ->setContent('nav', $box->render(
                [],
                '<div style="color: rgba(255,255,255,0.6); font-size: 0.75rem; margin-bottom: 12px;">NAVIGATION</div>
                 <nav style="display: flex; flex-direction: column; gap: 4px;">
                   <a href="#" style="color: white; text-decoration: none; padding: 8px; border-radius: 4px; background: rgba(255,255,255,0.1);">🏠 Home</a>
                   <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none; padding: 8px;">📊 Stats</a>
                   <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none; padding: 8px;">⚡ Actions</a>
                 </nav>
                 <div style="margin-top: auto; padding-top: 12px; font-size: 0.75rem; color: rgba(255,255,255,0.5);">
                   ⬅️ Folds left on md
                 </div>'
            ))
            ->setContent('primary', $box->render(
                [],
                '<h2 style="margin: 0 0 16px 0; color: #0c4a6e;">Primary Content</h2>
                 <p style="color: #64748b; line-height: 1.6; margin: 0 0 16px 0;">
                   This four-column layout demonstrates progressive collapse. As the viewport shrinks:
                 </p>
                 <ol style="color: #64748b; line-height: 1.8; margin: 0; padding-left: 20px;">
                   <li><strong>Desktop (xl+):</strong> All 4 columns visible</li>
                   <li><strong>Large tablet (lg):</strong> Panel folds below</li>
                   <li><strong>Tablet (md):</strong> Nav also folds above</li>
                 </ol>
                 <div style="margin-top: 20px; padding: 16px; background: #f0f9ff; border-radius: 8px; border-left: 4px solid #0ea5e9;">
                   <strong style="color: #0c4a6e;">Primary always stays visible</strong>
                   <p style="margin: 8px 0 0 0; color: #64748b; font-size: 0.875rem;">This is the main content area that never gets folded or hidden.</p>
                 </div>'
            ))
            ->setContent('secondary', $box->render(
                [],
                '<h3 style="margin: 0 0 12px 0; color: #0c4a6e; font-size: 1rem;">Secondary Info</h3>
                 <div style="padding: 12px; background: white; border-radius: 8px; margin-bottom: 12px;">
                   <div style="font-weight: 500; color: #0c4a6e;">Quick Stats</div>
                   <div style="color: #64748b; font-size: 0.875rem; margin-top: 4px;">1,234 views today</div>
                 </div>
                 <div style="padding: 12px; background: white; border-radius: 8px;">
                   <div style="font-weight: 500; color: #0c4a6e;">Status</div>
                   <div style="color: #22c55e; font-size: 0.875rem; margin-top: 4px;">● All systems operational</div>
                 </div>'
            ))
            ->setContent('panel', $box->render(
                [],
                '<h3 style="margin: 0 0 12px 0; color: #0c4a6e; font-size: 1rem;">Notifications</h3>
                 <div style="font-size: 0.875rem; color: #64748b;">
                   <div style="padding: 8px 0; border-bottom: 1px solid #bae6fd;">🔔 New message</div>
                   <div style="padding: 8px 0; border-bottom: 1px solid #bae6fd;">📧 Email sent</div>
                   <div style="padding: 8px 0;">✅ Task completed</div>
                 </div>
                 <div style="margin-top: 12px; padding: 8px; background: #dbeafe; border-radius: 6px; font-size: 0.75rem; color: #1e40af;">
                   ➡️ Folds right on lg
                 </div>'
            ))
            ->setContent('footer', $box->render(
                ['align' => 'center', 'justify' => 'center'],
                '<span style="color: rgba(255,255,255,0.7); font-size: 0.875rem;">Resize to see the progressive collapse in action</span>'
            ));
    }

    private function buildHtmlPage(
        string $bodyHtml,
        string $layoutCss,
        ResolvedLayout $resolved,
        string $title,
    ): string {
        $slotCss = $this->generateSlotStyles($resolved, 'layout');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title} - PHP Layout</title>
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

    /* Responsive indicator */
    .viewport-indicator {
      position: fixed;
      bottom: 16px;
      right: 16px;
      padding: 8px 16px;
      background: rgba(0,0,0,0.8);
      color: white;
      font-size: 0.75rem;
      border-radius: 100px;
      font-family: monospace;
      z-index: 9999;
    }
  </style>
</head>
<body>
{$bodyHtml}
<div class="viewport-indicator">
  <script>
    const indicator = document.currentScript.parentElement;
    function updateIndicator() {
      const w = window.innerWidth;
      let bp = 'xl';
      if (w < 480) bp = 'xs';
      else if (w < 768) bp = 'sm';
      else if (w < 1024) bp = 'md';
      else if (w < 1280) bp = 'lg';
      indicator.textContent = w + 'px (' + bp + ')';
    }
    updateIndicator();
    window.addEventListener('resize', updateIndicator);
  </script>
</div>
</body>
</html>
HTML;
    }

    private function generateSlotStyles(ResolvedLayout $resolved, string $prefix): string
    {
        $css = [];

        // Find slots that receive nested content (their padding goes to --own wrapper)
        $targetsReceivingNested = $this->findTargetSlotsReceivingNested($resolved);

        foreach ($resolved->slots as $slot) {
            $selector = '.' . $prefix . '__' . $slot->name;
            $excludeSpacing = isset($targetsReceivingNested[$slot->name]);
            $styles = $this->propertiesToCss($slot->properties, $excludeSpacing);
            if ($styles !== '') {
                $css[] = $selector . ' { ' . $styles . ' }';
            }
        }

        return implode("\n    ", $css);
    }

    /**
     * Find slot names that receive nested content from other slots.
     *
     * @return array<string, bool>
     */
    private function findTargetSlotsReceivingNested(ResolvedLayout $resolved): array
    {
        if ($resolved->grid === null || $resolved->breakpoints === []) {
            return [];
        }

        $transformer = new \PhpLayout\Transformer\ResponsiveGridTransformer();
        $targets = [];

        foreach ($resolved->breakpoints as $name => $breakpoint) {
            $transformed = $transformer->transform($resolved->grid, $name, [$name]);
            $relationships = $transformed->getNestedRelationships();

            foreach ($relationships as $info) {
                $targets[$info['target']] = true;
            }
        }

        return $targets;
    }

    /**
     * @param array<string, string> $properties
     */
    private function propertiesToCss(array $properties, bool $excludeSpacing = false): string
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
            'border-left',
            'border-right',
        ];

        foreach ($allowedProps as $prop) {
            // Skip padding and margin if they should be excluded (moved to --own wrapper)
            if ($excludeSpacing && ($prop === 'padding' || $prop === 'margin')) {
                continue;
            }
            if (isset($properties[$prop])) {
                $cssProps[] = $prop . ': ' . $properties[$prop];
            }
        }

        if (isset($properties['max-width']) && !isset($properties['margin'])) {
            $cssProps[] = 'margin-left: auto';
            $cssProps[] = 'margin-right: auto';
        }

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
