<?php

declare(strict_types=1);

namespace PhpLayout\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * A simple filesystem-based PSR-16 cache implementation.
 */
final class FilesystemCache implements CacheInterface
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return $default;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return $default;
        }

        $data = unserialize($content);
        if (!is_array($data)) {
            return $default;
        }

        // Check TTL
        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->ensureDirectory();

        $expires = null;
        if ($ttl !== null) {
            if ($ttl instanceof \DateInterval) {
                $expires = (new \DateTimeImmutable())->add($ttl)->getTimestamp();
            } elseif ($ttl > 0) {
                $expires = time() + $ttl;
            }
        }

        $data = [
            'value' => $value,
            'expires' => $expires,
        ];

        $path = $this->getPath($key);
        $result = file_put_contents($path, serialize($data), LOCK_EX);

        return $result !== false;
    }

    public function delete(string $key): bool
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return true;
        }

        return unlink($path);
    }

    public function clear(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $files = glob($this->directory . '/*.cache');
        if ($files === false) {
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        return $success;
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
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    private function getPath(string $key): string
    {
        // Sanitize key for filesystem
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->directory . '/' . $safeKey . '.cache';
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }
}
