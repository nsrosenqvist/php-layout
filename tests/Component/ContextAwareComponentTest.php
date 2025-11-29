<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Component;

use PhpLayout\Component\ComponentRegistry;
use PhpLayout\Generator\HtmlGenerator;
use PhpLayout\Parser\LayoutParser;
use PhpLayout\Resolver\LayoutResolver;
use PhpLayout\Tests\Fixtures\HeaderComponent;
use PhpLayout\Tests\Fixtures\PageData;
use PhpLayout\Tests\Fixtures\SimpleComponent;
use PhpLayout\Tests\Fixtures\SlotNameComponent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContextAwareComponentTest extends TestCase
{
    #[Test]
    public function itReceivesTypedDataInContextAwareComponent(): void
    {
        $registry = new ComponentRegistry();
        $registry->register('header', new HeaderComponent());
        $registry->setContext(new PageData(
            title: 'My Page Title',
            author: 'John Doe',
            publishedAt: new \DateTimeImmutable('2024-01-15'),
        ));

        $html = $registry->render('header', ['background' => '#2563eb'], '', 'header');

        self::assertStringContainsString('My Page Title', $html);
        self::assertStringContainsString('background: #2563eb', $html);
    }

    #[Test]
    public function itReceivesSlotNameInContextAwareComponent(): void
    {
        $registry = new ComponentRegistry();
        $registry->register('slotname', new SlotNameComponent());

        $html = $registry->render('slotname', [], '', 'my-custom-slot');

        self::assertStringContainsString('data-slot="my-custom-slot"', $html);
        self::assertStringContainsString('Slot: my-custom-slot', $html);
    }

    #[Test]
    public function itWorksNormallyWithNonContextAwareComponent(): void
    {
        $registry = new ComponentRegistry();
        $registry->register('simple', new SimpleComponent());
        $registry->setContext(new PageData(
            title: 'Test',
            author: 'Test',
            publishedAt: new \DateTimeImmutable(),
        ));

        $html = $registry->render('simple', ['background' => '#f00'], '', 'content');

        // Should use regular render(), not receive context
        self::assertStringContainsString('background: #f00', $html);
        self::assertStringContainsString('Simple', $html);
    }

    #[Test]
    public function itHandlesNoContextInContextAwareComponent(): void
    {
        $registry = new ComponentRegistry();
        $registry->register('header', new HeaderComponent());
        // No setContext() called

        $html = $registry->render('header', ['background' => '#333'], '', 'header');

        // Should handle null data gracefully
        self::assertStringContainsString('Untitled', $html);
        self::assertStringContainsString('background: #333', $html);
    }

    #[Test]
    public function itPassesContextThroughHtmlGenerator(): void
    {
        $layout = <<<'LAYOUT'
@layout page {
  +----------+
  |  header  |
  +----------+

  [header]
    component: header
    background: #2563eb
}
LAYOUT;

        $parser = new LayoutParser();
        $layouts = $parser->parse($layout);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $registry = new ComponentRegistry();
        $registry->register('header', new HeaderComponent());
        $registry->setContext(new PageData(
            title: 'Generated Page',
            author: 'Jane Doe',
            publishedAt: new \DateTimeImmutable(),
        ));

        $generator = new HtmlGenerator();
        $html = $generator->generate($resolved, $registry, 'layout');

        self::assertStringContainsString('Generated Page', $html);
        self::assertStringContainsString('background: #2563eb', $html);
    }

    #[Test]
    public function itPassesSlotNameThroughHtmlGenerator(): void
    {
        $layout = <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+

  [content]
    component: slotname
}
LAYOUT;

        $parser = new LayoutParser();
        $layouts = $parser->parse($layout);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $registry = new ComponentRegistry();
        $registry->register('slotname', new SlotNameComponent());

        $generator = new HtmlGenerator();
        $html = $generator->generate($resolved, $registry, 'layout');

        self::assertStringContainsString('data-slot="content"', $html);
    }

    #[Test]
    public function itSupportsFluentInterfaceForContext(): void
    {
        $pageData = new PageData(
            title: 'Test',
            author: 'Test',
            publishedAt: new \DateTimeImmutable(),
        );

        $registry = new ComponentRegistry();
        $result = $registry
            ->register('header', new HeaderComponent())
            ->setContext($pageData)
            ->setDefaultComponent('header');

        self::assertSame($registry, $result);
        self::assertTrue($registry->hasContext());
        self::assertSame($pageData, $registry->getContext());
    }

    #[Test]
    public function itPassesContextWithNestedGrids(): void
    {
        $layout = <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+

  [content] {
    +--------+--------+
    | card1  | card2  |
    +--------+--------+
  }

  [card1]
    component: header
    background: #dbeafe

  [card2]
    component: header
    background: #dcfce7
}
LAYOUT;

        $parser = new LayoutParser();
        $layouts = $parser->parse($layout);
        $resolver = new LayoutResolver($layouts);
        $resolved = $resolver->resolve('page');

        $registry = new ComponentRegistry();
        $registry->register('header', new HeaderComponent());
        $registry->setContext(new PageData(
            title: 'Nested Test',
            author: 'Test',
            publishedAt: new \DateTimeImmutable(),
        ));

        $generator = new HtmlGenerator();
        $html = $generator->generate($resolved, $registry, 'layout');

        // Both nested cards should receive the context
        self::assertStringContainsString('background: #dbeafe', $html);
        self::assertStringContainsString('background: #dcfce7', $html);
        // Title should appear twice (once for each card)
        self::assertSame(2, substr_count($html, 'Nested Test'));
    }
}
