<?php

declare(strict_types=1);

namespace PhpLayout\Component;

use PhpLayout\Render\RenderContext;

/**
 * Registry for managing components that can be rendered within layouts.
 */
final class ComponentRegistry
{
    /** @var array<string, ComponentInterface> */
    private array $components = [];

    /** @var array<string, string> */
    private array $content = [];

    private ?string $defaultComponent = null;

    private ?object $context = null;

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
     * Set the default component to use when a slot has no explicit component.
     *
     * The component must already be registered.
     */
    public function setDefaultComponent(string $name): self
    {
        if (!isset($this->components[$name])) {
            throw new \InvalidArgumentException(
                "Cannot set default component '{$name}': component not registered."
            );
        }
        $this->defaultComponent = $name;
        return $this;
    }

    /**
     * Get the default component name, if set.
     */
    public function getDefaultComponent(): ?string
    {
        return $this->defaultComponent;
    }

    /**
     * Check if a component is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->components[$name]) || isset($this->content[$name]);
    }

    /**
     * Check if a default component is set.
     */
    public function hasDefaultComponent(): bool
    {
        return $this->defaultComponent !== null;
    }

    /**
     * Set the typed data context for rendering.
     *
     * This data will be passed to ContextAwareComponentInterface components
     * via the RenderContext object.
     *
     * @param object $data The typed data model (e.g., page frontmatter object)
     */
    public function setContext(object $data): self
    {
        $this->context = $data;
        return $this;
    }

    /**
     * Get the current context data.
     */
    public function getContext(): ?object
    {
        return $this->context;
    }

    /**
     * Check if context data is set.
     */
    public function hasContext(): bool
    {
        return $this->context !== null;
    }

    /**
     * Render a component or return static content.
     *
     * @param array<string, string> $properties
     */
    public function render(string $name, array $properties = [], string $innerContent = '', string $slotName = ''): string
    {
        if (isset($this->components[$name])) {
            $component = $this->components[$name];

            // Use context-aware rendering if the component supports it
            if ($component instanceof ContextAwareComponentInterface) {
                $context = new RenderContext(
                    $this->context,
                    $properties,
                    $slotName !== '' ? $slotName : $name,
                );
                return $component->renderWithContext($context, $innerContent);
            }

            return $component->render($properties, $innerContent);
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
