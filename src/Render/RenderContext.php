<?php

declare(strict_types=1);

namespace PhpLayout\Render;

/**
 * Context object passed to components during rendering.
 *
 * Holds a typed data model and slot-specific properties, allowing components
 * to access page data (title, metadata, etc.) alongside their configuration.
 *
 * @template T of object
 */
final class RenderContext
{
    /**
     * @param T|null $data The typed data model (e.g., page frontmatter)
     * @param array<string, string> $properties Slot properties from layout definition
     * @param string $slotName The name of the slot being rendered
     */
    public function __construct(
        private readonly ?object $data,
        private readonly array $properties,
        private readonly string $slotName,
    ) {
    }

    /**
     * Get the typed data model.
     *
     * @return T|null
     */
    public function getData(): ?object
    {
        return $this->data;
    }

    /**
     * Get slot properties from the layout definition.
     *
     * @return array<string, string>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Get a single property value.
     */
    public function getProperty(string $key, string $default = ''): string
    {
        return $this->properties[$key] ?? $default;
    }

    /**
     * Check if a property exists.
     */
    public function hasProperty(string $key): bool
    {
        return isset($this->properties[$key]);
    }

    /**
     * Get the name of the slot being rendered.
     */
    public function getSlotName(): string
    {
        return $this->slotName;
    }

    /**
     * Check if data is available.
     */
    public function hasData(): bool
    {
        return $this->data !== null;
    }
}
