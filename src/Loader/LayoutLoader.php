<?php

declare(strict_types=1);

namespace PhpLayout\Loader;

use PhpLayout\Ast\ResolvedLayout;
use PhpLayout\Parser\LayoutParser;
use PhpLayout\Resolver\LayoutResolver;
use Psr\SimpleCache\CacheInterface;

/**
 * High-level loader for layout files with integrated caching.
 *
 * Handles file reading, parsing, resolving, and caching in one convenient interface.
 * File-based loading uses mtime for fast cache invalidation, while string-based
 * loading uses content hashing.
 */
final class LayoutLoader
{
    private LayoutParser $parser;

    /**
     * @param CacheInterface|null $cache PSR-16 cache implementation (null = no caching)
     * @param int|null $ttl Cache TTL in seconds (null = forever)
     */
    public function __construct(
        private readonly ?CacheInterface $cache = null,
        private readonly ?int $ttl = null,
    ) {
        $this->parser = new LayoutParser();
    }

    /**
     * Load and resolve a layout from a file.
     *
     * Uses file path + modification time for cache invalidation.
     *
     * @param string $filePath Path to the .lyt file
     * @param string $layoutName Name of the layout to resolve
     * @return ResolvedLayout The resolved layout
     * @throws \RuntimeException If file cannot be read
     */
    public function load(string $filePath, string $layoutName): ResolvedLayout
    {
        $realPath = realpath($filePath);
        if ($realPath === false || !is_readable($realPath)) {
            throw new \RuntimeException("Cannot read layout file: {$filePath}");
        }

        // Check cache first using file path + mtime
        if ($this->cache !== null) {
            $mtime = filemtime($realPath);
            $cacheKey = $this->getFileCacheKey($realPath, $mtime !== false ? $mtime : 0, $layoutName);

            $cached = $this->cache->get($cacheKey);
            if ($cached instanceof ResolvedLayout) {
                return $cached;
            }

            // Load, parse, resolve, and cache
            $resolved = $this->parseAndResolve($realPath, $layoutName);
            $this->cache->set($cacheKey, $resolved, $this->ttl);

            return $resolved;
        }

        // No cache - just load directly
        return $this->parseAndResolve($realPath, $layoutName);
    }

    /**
     * Parse and resolve a layout from a file path.
     */
    private function parseAndResolve(string $realPath, string $layoutName): ResolvedLayout
    {
        $source = file_get_contents($realPath);
        if ($source === false) {
            throw new \RuntimeException("Failed to read layout file: {$realPath}");
        }

        $layouts = $this->parser->parse($source);
        $resolver = new LayoutResolver($layouts);

        return $resolver->resolve($layoutName);
    }

    /**
     * Load and resolve a layout from a string.
     *
     * Uses content hash for cache invalidation.
     *
     * @param string $source The layout source string
     * @param string $layoutName Name of the layout to resolve
     * @return ResolvedLayout The resolved layout
     */
    public function loadFromString(string $source, string $layoutName): ResolvedLayout
    {
        // Check cache first using content hash
        if ($this->cache !== null) {
            $cacheKey = $this->getStringCacheKey($source, $layoutName);

            $cached = $this->cache->get($cacheKey);
            if ($cached instanceof ResolvedLayout) {
                return $cached;
            }

            // Parse, resolve, and cache
            $layouts = $this->parser->parse($source);
            $resolver = new LayoutResolver($layouts);
            $resolved = $resolver->resolve($layoutName);
            $this->cache->set($cacheKey, $resolved, $this->ttl);

            return $resolved;
        }

        // No cache - just parse directly
        $layouts = $this->parser->parse($source);
        $resolver = new LayoutResolver($layouts);

        return $resolver->resolve($layoutName);
    }

    /**
     * Clear all cached layouts.
     */
    public function clearCache(): bool
    {
        if ($this->cache === null) {
            return true;
        }

        return $this->cache->clear();
    }

    /**
     * Check if caching is enabled.
     */
    public function isCachingEnabled(): bool
    {
        return $this->cache !== null;
    }

    /**
     * Generate cache key for file-based loading.
     */
    private function getFileCacheKey(string $realPath, int $mtime, string $layoutName): string
    {
        // Use path hash + mtime for fast invalidation without reading file contents
        $pathHash = substr(hash('xxh128', $realPath), 0, 12);
        return "layout_file_{$pathHash}_{$mtime}_{$layoutName}";
    }

    /**
     * Generate cache key for string-based loading.
     */
    private function getStringCacheKey(string $source, string $layoutName): string
    {
        $contentHash = substr(hash('xxh128', $source), 0, 16);
        return "layout_string_{$contentHash}_{$layoutName}";
    }
}
