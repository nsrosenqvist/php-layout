<?php

declare(strict_types=1);

namespace PhpLayout\Ast;

/**
 * Represents a slot definition with its properties.
 */
final readonly class SlotDefinition
{
    /**
     * @param array<string, string> $properties
     */
    public function __construct(
        public string $name,
        public array $properties,
        public ?Grid $nestedGrid = null,
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
}
