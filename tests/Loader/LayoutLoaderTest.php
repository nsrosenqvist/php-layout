<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Loader;

use PhpLayout\Cache\FilesystemCache;
use PhpLayout\Loader\LayoutLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LayoutLoaderTest extends TestCase
{
    private string $tempDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/php-layout-loader-test-' . uniqid();
        $this->cacheDir = $this->tempDir . '/cache';
        mkdir($this->tempDir, 0777, true);
        mkdir($this->cacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    #[Test]
    public function itLoadsLayoutFromFile(): void
    {
        $layoutFile = $this->tempDir . '/page.lyt';
        file_put_contents($layoutFile, <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+

  [content]
    component: ...
}
LAYOUT);

        $loader = new LayoutLoader();
        $resolved = $loader->load($layoutFile, 'page');

        self::assertSame('page', $resolved->name);
        self::assertNotNull($resolved->grid);
        self::assertArrayHasKey('content', $resolved->slots);
    }

    #[Test]
    public function itLoadsLayoutFromString(): void
    {
        $source = <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+
}
LAYOUT;

        $loader = new LayoutLoader();
        $resolved = $loader->loadFromString($source, 'page');

        self::assertSame('page', $resolved->name);
        self::assertNotNull($resolved->grid);
    }

    #[Test]
    public function itThrowsOnMissingFile(): void
    {
        $loader = new LayoutLoader();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot read layout file');

        $loader->load('/nonexistent/path/layout.lyt', 'page');
    }

    #[Test]
    public function itCachesFileBasedLayouts(): void
    {
        $layoutFile = $this->tempDir . '/page.lyt';
        file_put_contents($layoutFile, <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+
}
LAYOUT);

        $cache = new FilesystemCache($this->cacheDir);
        $loader = new LayoutLoader(cache: $cache);

        // First load - parses and caches
        $resolved1 = $loader->load($layoutFile, 'page');

        // Second load - should come from cache
        $resolved2 = $loader->load($layoutFile, 'page');

        self::assertSame('page', $resolved1->name);
        self::assertSame('page', $resolved2->name);
    }

    #[Test]
    public function itInvalidatesCacheOnFileChange(): void
    {
        $layoutFile = $this->tempDir . '/page.lyt';
        file_put_contents($layoutFile, <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+

  [content]
    component: ...
}
LAYOUT);

        $cache = new FilesystemCache($this->cacheDir);
        $loader = new LayoutLoader(cache: $cache);

        // First load
        $resolved1 = $loader->load($layoutFile, 'page');
        self::assertArrayHasKey('content', $resolved1->slots);

        // Modify file (need to change mtime)
        sleep(1); // Ensure mtime changes
        file_put_contents($layoutFile, <<<'LAYOUT'
@layout page {
  +----------+
  |  updated |
  +----------+

  [updated]
    component: ...
}
LAYOUT);

        // Second load - should get new content due to mtime change
        $resolved2 = $loader->load($layoutFile, 'page');
        self::assertArrayHasKey('updated', $resolved2->slots);
        self::assertArrayNotHasKey('content', $resolved2->slots);
    }

    #[Test]
    public function itCachesStringBasedLayouts(): void
    {
        $source = <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+
}
LAYOUT;

        $cache = new FilesystemCache($this->cacheDir);
        $loader = new LayoutLoader(cache: $cache);

        // First load - parses and caches
        $resolved1 = $loader->loadFromString($source, 'page');

        // Second load - should come from cache
        $resolved2 = $loader->loadFromString($source, 'page');

        self::assertSame('page', $resolved1->name);
        self::assertSame('page', $resolved2->name);
    }

    #[Test]
    public function itWorksWithoutCache(): void
    {
        $source = <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+
}
LAYOUT;

        $loader = new LayoutLoader(); // No cache
        self::assertFalse($loader->isCachingEnabled());

        $resolved = $loader->loadFromString($source, 'page');
        self::assertSame('page', $resolved->name);
    }

    #[Test]
    public function itClearsCache(): void
    {
        $cache = new FilesystemCache($this->cacheDir);
        $loader = new LayoutLoader(cache: $cache);

        self::assertTrue($loader->isCachingEnabled());
        self::assertTrue($loader->clearCache());
    }

    #[Test]
    public function itResolvesInheritanceFromFile(): void
    {
        $layoutFile = $this->tempDir . '/layouts.lyt';
        file_put_contents($layoutFile, <<<'LAYOUT'
@layout base {
  +----------+
  |  content |
  +----------+

  [content]
    component: ...
}

@layout page extends base {
  [content]
    component: Page
    padding: 20px
}
LAYOUT);

        $loader = new LayoutLoader();
        $resolved = $loader->load($layoutFile, 'page');

        self::assertSame('page', $resolved->name);
        self::assertSame('Page', $resolved->slots['content']->getComponent());
        self::assertSame('20px', $resolved->slots['content']->properties['padding']);
    }

    #[Test]
    public function itRespectsTtl(): void
    {
        $cache = new FilesystemCache($this->cacheDir);
        $loader = new LayoutLoader(cache: $cache, ttl: 3600);

        $source = <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+
}
LAYOUT;

        // Just verify it doesn't throw - TTL is passed to cache
        $resolved = $loader->loadFromString($source, 'page');
        self::assertSame('page', $resolved->name);
    }
}
