<?php

declare(strict_types=1);

namespace PhpLayout\Component;

/**
 * Registry for managing components that can be rendered within layouts.
 */
final class ComponentRegistry
{
    /** @var array<string, ComponentInterface> */
    private array $components = [];

    /** @var array<string, string> */
    private array $content = [];

    /**
     * Register a component instance.
     */
    public function register(string $name, ComponentInterface $component): self
    {
        $this->components[$name] = $component;
        return $this;
    }

    /**
     * Register static content for a slot/component.
     */
    public function setContent(string $name, string $content): self
    {
        $this->content[$name] = $content;
        return $this;
    }

    /**
     * Check if a component is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->components[$name]) || isset($this->content[$name]);
    }

    /**
     * Render a component or return static content.
     *
     * @param array<string, string> $properties
     */
    public function render(string $name, array $properties = [], string $innerContent = ''): string
    {
        if (isset($this->components[$name])) {
            return $this->components[$name]->render($properties, $innerContent);
        }

        if (isset($this->content[$name])) {
            return $this->content[$name];
        }

        return '<!-- ' . htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8') . ' -->';
    }

    /**
     * Get all registered component/content names.
     *
     * @return list<string>
     */
    public function getRegisteredNames(): array
    {
        return array_values(array_unique([
            ...array_keys($this->components),
            ...array_keys($this->content),
        ]));
    }
}
