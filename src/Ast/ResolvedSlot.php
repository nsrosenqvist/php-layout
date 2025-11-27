<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Represents a resolved slot with its final grid and properties.
 */
final readonly class ResolvedSlot
{
    /**
     * @param array<string, string> $properties
     * @param array<string, ResolvedSlot> $children
     */
    public function __construct(
        public string $name,
        public array $properties,
        public ?Grid $grid = null,
        public array $children = [],
        public bool $isContainer = false,
    ) {
    }

    public function getComponent(): ?string
    {
        return $this->properties['component'] ?? null;
    }

    public function hasComponent(): bool
    {
        return isset($this->properties['component']);
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }
}
