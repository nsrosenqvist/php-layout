<?php

declare(strict_types=1);

namespace PhpLayout\Resolver;

use PhpLayout\Ast\Breakpoint;
use PhpLayout\Ast\Grid;
use PhpLayout\Ast\Layout;
use PhpLayout\Ast\ResolvedLayout;
use PhpLayout\Ast\ResolvedSlot;
use PhpLayout\Ast\SlotDefinition;

/**
 * Resolves layout inheritance and merges slots.
 */
final class LayoutResolver
{
    /** @var array<string, Layout> */
    private array $layouts = [];

    /**
     * @param list<Layout> $layouts
     */
    public function __construct(array $layouts)
    {
        foreach ($layouts as $layout) {
            $this->layouts[$layout->name] = $layout;
        }
    }

    /**
     * Resolve a layout by name, applying all inheritance.
     */
    public function resolve(string $name): ResolvedLayout
    {
        if (!isset($this->layouts[$name])) {
            throw new \InvalidArgumentException("Layout '$name' not found.");
        }

        $layout = $this->layouts[$name];
        $chain = $this->getInheritanceChain($layout);

        // Start with empty resolved state
        $grid = null;
        $slots = [];
        $breakpoints = [];

        // Apply each layout in the chain (from base to child)
        foreach ($chain as $chainLayout) {
            // Inherit grid from parent if not defined
            if ($chainLayout->grid !== null) {
                $grid = $chainLayout->grid;
            }

            // Merge slots
            foreach ($chainLayout->slots as $slotName => $slotDef) {
                $slots = $this->mergeSlot($slots, $slotName, $slotDef);
            }

            // Merge breakpoints (child overrides parent)
            $breakpoints = $this->mergeBreakpoints($breakpoints, $chainLayout->breakpoints);
        }

        // Build resolved slots with children
        $resolvedSlots = $this->buildResolvedSlots($slots, $grid);

        return new ResolvedLayout($name, $grid, $resolvedSlots, $breakpoints);
    }

    /**
     * Get the inheritance chain from base to child.
     *
     * @return list<Layout>
     */
    private function getInheritanceChain(Layout $layout): array
    {
        $chain = [$layout];
        $current = $layout;

        while ($current->extends !== null) {
            if (!isset($this->layouts[$current->extends])) {
                throw new \InvalidArgumentException(
                    "Parent layout '{$current->extends}' not found for '{$current->name}'."
                );
            }

            $parent = $this->layouts[$current->extends];
            array_unshift($chain, $parent);
            $current = $parent;
        }

        return $chain;
    }

    /**
     * Merge a slot definition into existing slots.
     *
     * @param array<string, SlotDefinition> $slots
     * @return array<string, SlotDefinition>
     */
    private function mergeSlot(array $slots, string $name, SlotDefinition $slotDef): array
    {
        if (!isset($slots[$name])) {
            $slots[$name] = $slotDef;
            return $slots;
        }

        $existing = $slots[$name];

        // Merge properties (child overrides parent)
        $mergedProperties = array_merge($existing->properties, $slotDef->properties);

        // Child's nested grid replaces parent's
        $nestedGrid = $slotDef->nestedGrid ?? $existing->nestedGrid;

        // Container status
        $isContainer = $slotDef->isContainer || $existing->isContainer;

        $slots[$name] = new SlotDefinition(
            $name,
            $mergedProperties,
            $nestedGrid,
            $isContainer,
        );

        return $slots;
    }

    /**
     * Merge breakpoints from parent and child (child wins).
     *
     * @param array<string, Breakpoint> $parent
     * @param array<string, Breakpoint> $child
     * @return array<string, Breakpoint>
     */
    private function mergeBreakpoints(array $parent, array $child): array
    {
        return array_merge($parent, $child);
    }

    /**
     * Build resolved slots with parent-child relationships.
     *
     * @param array<string, SlotDefinition> $slots
     * @return array<string, ResolvedSlot>
     */
    private function buildResolvedSlots(array $slots, ?Grid $rootGrid): array
    {
        $resolved = [];

        foreach ($slots as $name => $slotDef) {
            $children = [];

            // If slot has a nested grid, find child slots
            if ($slotDef->nestedGrid !== null) {
                $childNames = $slotDef->nestedGrid->getSlotNames();
                foreach ($childNames as $childName) {
                    if (isset($slots[$childName])) {
                        $childSlot = $slots[$childName];
                        $children[$childName] = new ResolvedSlot(
                            $childName,
                            $childSlot->properties,
                            $childSlot->nestedGrid,
                            $this->resolveChildren($childSlot, $slots),
                            $childSlot->isContainer,
                        );
                    }
                }
            }

            $resolved[$name] = new ResolvedSlot(
                $name,
                $slotDef->properties,
                $slotDef->nestedGrid,
                $children,
                $slotDef->isContainer,
            );
        }

        return $resolved;
    }

    /**
     * Recursively resolve children for a slot.
     *
     * @param array<string, SlotDefinition> $allSlots
     * @return array<string, ResolvedSlot>
     */
    private function resolveChildren(SlotDefinition $slot, array $allSlots): array
    {
        if ($slot->nestedGrid === null) {
            return [];
        }

        $children = [];
        $childNames = $slot->nestedGrid->getSlotNames();

        foreach ($childNames as $childName) {
            if (isset($allSlots[$childName])) {
                $childSlot = $allSlots[$childName];
                $children[$childName] = new ResolvedSlot(
                    $childName,
                    $childSlot->properties,
                    $childSlot->nestedGrid,
                    $this->resolveChildren($childSlot, $allSlots),
                    $childSlot->isContainer,
                );
            }
        }

        return $children;
    }
}
