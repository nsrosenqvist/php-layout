<?php

declare(strict_types=1);

namespace PhpLayout\Resolver;

use PhpLayout\Ast\Layout;
use PhpLayout\Ast\ResolvedLayout;
use Psr\SimpleCache\CacheInterface;

/**
 * A caching decorator for LayoutResolver that stores resolved layouts.
 *
 * Cache keys are generated from a hash of the layout source content,
 * ensuring automatic cache invalidation when layouts change.
 */
final class CachedLayoutResolver
{
    private LayoutResolver $resolver;
    private CacheInterface $cache;
    private string $sourceHash;
    private ?int $ttl;

    /**
     * @param list<Layout> $layouts The parsed layouts
     * @param CacheInterface $cache PSR-16 cache implementation
     * @param string $source The original layout source string (used for cache key generation)
     * @param int|null $ttl Cache TTL in seconds (null = forever)
     */
    public function __construct(
        array $layouts,
        CacheInterface $cache,
        string $source,
        ?int $ttl = null,
    ) {
        $this->resolver = new LayoutResolver($layouts);
        $this->cache = $cache;
        $this->sourceHash = $this->generateSourceHash($source);
        $this->ttl = $ttl;
    }

    /**
     * Resolve a layout by name, using cache when available.
     */
    public function resolve(string $name): ResolvedLayout
    {
        $cacheKey = $this->getCacheKey($name);

        $cached = $this->cache->get($cacheKey);
        if ($cached instanceof ResolvedLayout) {
            return $cached;
        }

        $resolved = $this->resolver->resolve($name);
        $this->cache->set($cacheKey, $resolved, $this->ttl);

        return $resolved;
    }

    /**
     * Clear all cached layouts for this source.
     */
    public function clearCache(): bool
    {
        // We can't selectively clear by prefix with PSR-16,
        // so we clear the entire cache
        return $this->cache->clear();
    }

    /**
     * Check if a resolved layout is cached.
     */
    public function isCached(string $name): bool
    {
        return $this->cache->has($this->getCacheKey($name));
    }

    /**
     * Get the cache key for a layout name.
     */
    public function getCacheKey(string $name): string
    {
        return 'layout_' . $this->sourceHash . '_' . $name;
    }

    /**
     * Generate a hash of the source content.
     */
    private function generateSourceHash(string $source): string
    {
        return substr(hash('xxh128', $source), 0, 16);
    }
}
