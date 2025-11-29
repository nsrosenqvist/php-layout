<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Component;

use PhpLayout\Component\BoxComponent;
use PhpLayout\Component\ComponentInterface;
use PhpLayout\Component\ComponentRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComponentRegistryTest extends TestCase
{
    #[Test]
    public function itRegistersAndRendersComponent(): void
    {
        $registry = new ComponentRegistry();
        $registry->register('box', new BoxComponent());

        self::assertTrue($registry->has('box'));
        $html = $registry->render('box', ['width' => '100px'], 'Content');

        self::assertStringContainsString('width: 100px', $html);
        self::assertStringContainsString('Content', $html);
    }

    #[Test]
    public function itRegistersAndRendersStaticContent(): void
    {
        $registry = new ComponentRegistry();
        $registry->setContent('header', '<header>Hello</header>');

        self::assertTrue($registry->has('header'));
        $html = $registry->render('header');

        self::assertSame('<header>Hello</header>', $html);
    }

    #[Test]
    public function itReturnsPlaceholderForUnknownComponent(): void
    {
        $registry = new ComponentRegistry();

        $html = $registry->render('unknown');

        self::assertSame('<!-- unknown -->', $html);
    }

    #[Test]
    public function itSupportsFluentInterface(): void
    {
        $registry = new ComponentRegistry();

        $result = $registry
            ->register('box', new BoxComponent())
            ->setContent('header', '<header>Test</header>')
            ->setContent('footer', '<footer>Test</footer>');

        self::assertSame($registry, $result);
        self::assertTrue($registry->has('box'));
        self::assertTrue($registry->has('header'));
        self::assertTrue($registry->has('footer'));
    }

    #[Test]
    public function itListsRegisteredNames(): void
    {
        $registry = new ComponentRegistry();
        $registry
            ->register('box', new BoxComponent())
            ->setContent('header', '<header>Test</header>');

        $names = $registry->getRegisteredNames();

        self::assertContains('box', $names);
        self::assertContains('header', $names);
    }

    #[Test]
    public function itRegistersCustomComponent(): void
    {
        $customComponent = new class () implements ComponentInterface {
            public function render(array $properties, string $content = ''): string
            {
                $title = $properties['title'] ?? 'Default Title';
                return "<article><h1>{$title}</h1>{$content}</article>";
            }
        };

        $registry = new ComponentRegistry();
        $registry->register('article', $customComponent);

        $html = $registry->render('article', ['title' => 'My Article'], '<p>Body</p>');

        self::assertSame('<article><h1>My Article</h1><p>Body</p></article>', $html);
    }

    #[Test]
    public function itEscapesUnknownComponentNames(): void
    {
        $registry = new ComponentRegistry();

        $html = $registry->render('<script>alert("xss")</script>');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function itSetsDefaultComponent(): void
    {
        $registry = new ComponentRegistry();
        $registry->register('box', new BoxComponent());

        self::assertFalse($registry->hasDefaultComponent());
        self::assertNull($registry->getDefaultComponent());

        $registry->setDefaultComponent('box');

        self::assertTrue($registry->hasDefaultComponent());
        self::assertSame('box', $registry->getDefaultComponent());
    }

    #[Test]
    public function itThrowsWhenSettingUnregisteredDefault(): void
    {
        $registry = new ComponentRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot set default component 'unknown': component not registered.");

        $registry->setDefaultComponent('unknown');
    }

    #[Test]
    public function itSupportsFluentInterfaceWithDefault(): void
    {
        $registry = new ComponentRegistry();

        $result = $registry
            ->register('box', new BoxComponent())
            ->setDefaultComponent('box');

        self::assertSame($registry, $result);
        self::assertSame('box', $registry->getDefaultComponent());
    }
}
