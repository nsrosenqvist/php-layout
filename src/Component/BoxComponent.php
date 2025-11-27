<?php

declare(strict_types=1);

namespace PhpLayout\Component;

/**
 * A simple box component that renders a div with configurable dimensions and alignment.
 */
final class BoxComponent implements ComponentInterface
{
    /**
     * Render a box div with the given properties.
     *
     * @param array<string, string> $properties
     */
    public function render(array $properties, string $content = ''): string
    {
        $styles = $this->buildStyles($properties);
        $classes = $this->buildClasses($properties);

        $attributes = [];
        if ($classes !== '') {
            $attributes[] = 'class="' . $this->escape($classes) . '"';
        }
        if ($styles !== '') {
            $attributes[] = 'style="' . $this->escape($styles) . '"';
        }

        $attrString = $attributes !== [] ? ' ' . implode(' ', $attributes) : '';

        return '<div' . $attrString . '>' . $content . '</div>';
    }

    /**
     * @param array<string, string> $properties
     */
    private function buildStyles(array $properties): string
    {
        $styles = [];

        // Dimensions
        if (isset($properties['width'])) {
            $styles[] = 'width: ' . $properties['width'];
        }
        if (isset($properties['height'])) {
            $styles[] = 'height: ' . $properties['height'];
        }
        if (isset($properties['min-width'])) {
            $styles[] = 'min-width: ' . $properties['min-width'];
        }
        if (isset($properties['max-width'])) {
            $styles[] = 'max-width: ' . $properties['max-width'];
        }
        if (isset($properties['min-height'])) {
            $styles[] = 'min-height: ' . $properties['min-height'];
        }
        if (isset($properties['max-height'])) {
            $styles[] = 'max-height: ' . $properties['max-height'];
        }

        // Padding & Margin
        if (isset($properties['padding'])) {
            $styles[] = 'padding: ' . $properties['padding'];
        }
        if (isset($properties['margin'])) {
            $styles[] = 'margin: ' . $properties['margin'];
        }

        // Alignment (for flex container)
        if (isset($properties['align']) || isset($properties['justify'])) {
            $styles[] = 'display: flex';
            if (isset($properties['align'])) {
                $styles[] = 'align-items: ' . $properties['align'];
            }
            if (isset($properties['justify'])) {
                $styles[] = 'justify-content: ' . $properties['justify'];
            }
        }

        // Background
        if (isset($properties['background'])) {
            $styles[] = 'background: ' . $properties['background'];
        }

        // Border
        if (isset($properties['border'])) {
            $styles[] = 'border: ' . $properties['border'];
        }

        // Border radius
        if (isset($properties['border-radius'])) {
            $styles[] = 'border-radius: ' . $properties['border-radius'];
        }

        return implode('; ', $styles);
    }

    /**
     * @param array<string, string> $properties
     */
    private function buildClasses(array $properties): string
    {
        $classes = [];

        if (isset($properties['class'])) {
            $classes[] = $properties['class'];
        }

        return implode(' ', $classes);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
