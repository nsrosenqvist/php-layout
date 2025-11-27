<?php

declare(strict_types=1);

namespace PhpLayout\Tests\Cache;

use PhpLayout\Cache\FilesystemCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilesystemCacheTest extends TestCase
{
    private string $cacheDir;
    private FilesystemCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/php-layout-test-' . uniqid();
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
    public function it_returns_default_for_missing_key(): void
    {
        $result = $this->cache->get('nonexistent', 'default');

        self::assertSame('default', $result);
    }

    #[Test]
    public function it_stores_and_retrieves_values(): void
    {
        $this->cache->set('key', 'value');

        self::assertSame('value', $this->cache->get('key'));
    }

    #[Test]
    public function it_stores_complex_values(): void
    {
        $data = ['name' => 'test', 'items' => [1, 2, 3]];
        $this->cache->set('complex', $data);

        self::assertSame($data, $this->cache->get('complex'));
    }

    #[Test]
    public function it_stores_objects(): void
    {
        $object = new \stdClass();
        $object->name = 'test';
        $object->value = 42;

        $this->cache->set('object', $object);
        $retrieved = $this->cache->get('object');

        self::assertInstanceOf(\stdClass::class, $retrieved);
        self::assertSame('test', $retrieved->name);
        self::assertSame(42, $retrieved->value);
    }

    #[Test]
    public function it_deletes_values(): void
    {
        $this->cache->set('key', 'value');
        self::assertTrue($this->cache->has('key'));

        $this->cache->delete('key');
        self::assertFalse($this->cache->has('key'));
    }

    #[Test]
    public function it_clears_all_values(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $this->cache->clear();

        self::assertFalse($this->cache->has('key1'));
        self::assertFalse($this->cache->has('key2'));
    }

    #[Test]
    public function it_checks_if_key_exists(): void
    {
        self::assertFalse($this->cache->has('key'));

        $this->cache->set('key', 'value');

        self::assertTrue($this->cache->has('key'));
    }

    #[Test]
    public function it_respects_integer_ttl(): void
    {
        $this->cache->set('key', 'value', 1);

        self::assertTrue($this->cache->has('key'));

        sleep(2);

        self::assertFalse($this->cache->has('key'));
    }

    #[Test]
    public function it_respects_dateinterval_ttl(): void
    {
        $this->cache->set('key', 'value', new \DateInterval('PT1S'));

        self::assertTrue($this->cache->has('key'));

        sleep(2);

        self::assertFalse($this->cache->has('key'));
    }

    #[Test]
    public function it_gets_multiple_values(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $results = iterator_to_array($this->cache->getMultiple(['key1', 'key2', 'key3'], 'default'));

        self::assertSame('value1', $results['key1']);
        self::assertSame('value2', $results['key2']);
        self::assertSame('default', $results['key3']);
    }

    #[Test]
    public function it_sets_multiple_values(): void
    {
        $this->cache->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        self::assertSame('value1', $this->cache->get('key1'));
        self::assertSame('value2', $this->cache->get('key2'));
    }

    #[Test]
    public function it_deletes_multiple_values(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $this->cache->deleteMultiple(['key1', 'key2']);

        self::assertFalse($this->cache->has('key1'));
        self::assertFalse($this->cache->has('key2'));
    }

    #[Test]
    public function it_sanitizes_cache_keys(): void
    {
        $this->cache->set('key/with:special.chars', 'value');

        self::assertSame('value', $this->cache->get('key/with:special.chars'));
    }

    #[Test]
    public function it_creates_directory_if_not_exists(): void
    {
        $newDir = $this->cacheDir . '/nested/path';
        $cache = new FilesystemCache($newDir);

        $cache->set('key', 'value');

        self::assertSame('value', $cache->get('key'));
        self::assertDirectoryExists($newDir);

        // Cleanup
        unlink($newDir . '/key.cache');
        rmdir($newDir);
        rmdir($this->cacheDir . '/nested');
    }
}
