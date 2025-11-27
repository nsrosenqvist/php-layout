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
    public function it_registers_and_renders_component(): void
    {
        $registry = new ComponentRegistry();
        $registry->register('box', new BoxComponent());

        self::assertTrue($registry->has('box'));
        $html = $registry->render('box', ['width' => '100px'], 'Content');

        self::assertStringContainsString('width: 100px', $html);
        self::assertStringContainsString('Content', $html);
    }

    #[Test]
    public function it_registers_and_renders_static_content(): void
    {
        $registry = new ComponentRegistry();
        $registry->setContent('header', '<header>Hello</header>');

        self::assertTrue($registry->has('header'));
        $html = $registry->render('header');

        self::assertSame('<header>Hello</header>', $html);
    }

    #[Test]
    public function it_returns_placeholder_for_unknown_component(): void
    {
        $registry = new ComponentRegistry();

        $html = $registry->render('unknown');

        self::assertSame('<!-- unknown -->', $html);
    }

    #[Test]
    public function it_supports_fluent_interface(): void
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
    public function it_lists_registered_names(): void
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
    public function it_registers_custom_component(): void
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
    public function it_escapes_unknown_component_names(): void
    {
        $registry = new ComponentRegistry();

        $html = $registry->render('<script>alert("xss")</script>');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }
}
