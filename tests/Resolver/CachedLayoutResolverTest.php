<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Resolver;

use PhpLayout\Cache\FilesystemCache;
use PhpLayout\Parser\LayoutParser;
use PhpLayout\Resolver\CachedLayoutResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class CachedLayoutResolverTest extends TestCase
{
    private string $cacheDir;
    private FilesystemCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/php-layout-resolver-test-' . uniqid();
        $this->cache = new FilesystemCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        // Clean up cache directory
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            rmdir($this->cacheDir);
        }
    }

    #[Test]
    public function itResolvesLayoutAndCachesResult(): void
    {
        $source = <<<'LAYOUT'
@layout page {
  +----------+
  |  content |
  +----------+

  [content]
    padding: 20px
}
LAYOUT;

        $parser = new LayoutParser();
        $layouts = $parser->parse($source);

        $resolver = new CachedLayoutResolver($layouts, $this->cache, $source);

        // First call - not cached
        self::assertFalse($resolver->isCached('page'));

        $resolved = $resolver->resolve('page');

        // Now cached
        self::assertTrue($resolver->isCached('page'));
        self::assertSame('page', $resolved->name);
        self::assertArrayHasKey('content', $resolved->slots);
    }

    #[Test]
    public function itReturnsCachedLayoutOnSecondCall(): void
    {
        $source = '@layout base {}';

        $parser = new LayoutParser();
        $layouts = $parser->parse($source);

        $resolver = new CachedLayoutResolver($layouts, $this->cache, $source);

        $first = $resolver->resolve('base');
        $second = $resolver->resolve('base');

        // Both should be equal (cached)
        self::assertEquals($first, $second);
    }

    #[Test]
    public function itGeneratesDifferentKeysForDifferentSources(): void
    {
        $source1 = '@layout page1 {}';
        $source2 = '@layout page2 {}';

        $parser = new LayoutParser();

        $resolver1 = new CachedLayoutResolver(
            $parser->parse($source1),
            $this->cache,
            $source1,
        );

        $resolver2 = new CachedLayoutResolver(
            $parser->parse($source2),
            $this->cache,
            $source2,
        );

        // Keys should be different due to different source content
        $key1 = $resolver1->getCacheKey('page1');
        $key2 = $resolver2->getCacheKey('page2');

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function itInvalidatesCacheWhenSourceChanges(): void
    {
        $source1 = <<<'LAYOUT'
@layout page {
  [content]
    padding: 20px
}
LAYOUT;

        $source2 = <<<'LAYOUT'
@layout page {
  [content]
    padding: 40px
}
LAYOUT;

        $parser = new LayoutParser();

        // Cache with source1
        $resolver1 = new CachedLayoutResolver(
            $parser->parse($source1),
            $this->cache,
            $source1,
        );
        $resolver1->resolve('page');
        self::assertTrue($resolver1->isCached('page'));

        // Create resolver with modified source - should have different cache key
        $resolver2 = new CachedLayoutResolver(
            $parser->parse($source2),
            $this->cache,
            $source2,
        );

        // Not cached because source hash is different
        self::assertFalse($resolver2->isCached('page'));
    }

    #[Test]
    public function itClearsCache(): void
    {
        $source = '@layout page {}';

        $parser = new LayoutParser();
        $layouts = $parser->parse($source);

        $resolver = new CachedLayoutResolver($layouts, $this->cache, $source);
        $resolver->resolve('page');
        self::assertTrue($resolver->isCached('page'));

        $resolver->clearCache();
        self::assertFalse($resolver->isCached('page'));
    }

    #[Test]
    public function itResolvesInheritanceAndCaches(): void
    {
        $source = <<<'LAYOUT'
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
LAYOUT;

        $parser = new LayoutParser();
        $layouts = $parser->parse($source);

        $resolver = new CachedLayoutResolver($layouts, $this->cache, $source);
        $resolved = $resolver->resolve('page');

        self::assertSame('page', $resolved->name);
        self::assertSame('Page', $resolved->slots['content']->getComponent());
        self::assertSame('20px', $resolved->slots['content']->properties['padding']);
        self::assertTrue($resolver->isCached('page'));
    }

    #[Test]
    public function itWorksWithAnyPsr16Cache(): void
    {
        $source = '@layout test {}';
        $parser = new LayoutParser();
        $layouts = $parser->parse($source);

        // Create a mock PSR-16 cache
        $mockCache = new class () implements CacheInterface {
            /** @var array<string, mixed> */
            private array $data = [];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
            {
                $this->data[$key] = $value;
                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->data[$key]);
                return true;
            }

            public function clear(): bool
            {
                $this->data = [];
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                $result = [];
                foreach ($keys as $key) {
                    $result[$key] = $this->get($key, $default);
                }
                return $result;
            }

            /**
             * @param iterable<string, mixed> $values
             */
            public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
            {
                foreach ($values as $key => $value) {
                    $this->set((string) $key, $value, $ttl);
                }
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                foreach ($keys as $key) {
                    $this->delete($key);
                }
                return true;
            }

            public function has(string $key): bool
            {
                return isset($this->data[$key]);
            }
        };

        $resolver = new CachedLayoutResolver($layouts, $mockCache, $source);
        $resolved = $resolver->resolve('test');

        self::assertSame('test', $resolved->name);
        self::assertTrue($resolver->isCached('test'));
    }

    #[Test]
    public function itSupportsTtl(): void
    {
        $source = '@layout page {}';

        $parser = new LayoutParser();
        $layouts = $parser->parse($source);

        // Use 1 second TTL
        $resolver = new CachedLayoutResolver($layouts, $this->cache, $source, 1);
        $resolver->resolve('page');

        self::assertTrue($resolver->isCached('page'));

        sleep(2);

        self::assertFalse($resolver->isCached('page'));
    }
}
