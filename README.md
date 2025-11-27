# PHP Layout Engine

A declarative layout engine for PHP that uses ASCII box-drawing syntax to visually define HTML structures and generate CSS Grid layouts.

## Features

- **Visual Layout Definition**: Define layouts using intuitive ASCII box-drawing syntax
- **CSS Grid Output**: Generates modern CSS Grid layouts with `grid-template-areas`
- **Layout Inheritance**: Extend layouts with the `extends` keyword
- **Nested Grids**: Support for nested grid structures within slots
- **Custom Components**: Register your own components for dynamic content rendering
- **Type Safety**: Built with PHP 8.4, strict types, and PHPStan max level

## Requirements

- PHP 8.4+

## Installation

```bash
composer require nsrosenqvist/php-layout
```

## Quick Start

### Define a Layout

Create a `.lyt` file with your layout definition:

```
@layout page {
  +------------------------------------------+
  |                 header                   |
  +------------+-----------------------------+
  |  sidebar   |           content           |
  +------------+-----------------------------+
  |                 footer                   |
  +------------------------------------------+

  [header]
    height: 70px
    background: #2563eb
    padding: 0 20px

  [sidebar]
    width: 250px
    background: #f1f5f9
    padding: 20px

  [content]
    padding: 30px

  [footer]
    height: 60px
    background: #1e293b
}
```

### Parse and Generate

```php
<?php

use PhpLayout\Parser\LayoutParser;
use PhpLayout\Resolver\LayoutResolver;
use PhpLayout\Generator\CssGenerator;
use PhpLayout\Generator\HtmlGenerator;
use PhpLayout\Component\ComponentRegistry;

// Parse layout
$parser = new LayoutParser();
$layouts = $parser->parse($layoutString);

// Resolve inheritance
$resolver = new LayoutResolver($layouts);
$resolved = $resolver->resolve('page');

// Generate CSS
$cssGenerator = new CssGenerator();
$css = $cssGenerator->generate($resolved, 'layout');

// Generate HTML with components
$components = new ComponentRegistry();
$components
    ->setContent('header', '<h1>My Site</h1>')
    ->setContent('sidebar', '<nav>...</nav>')
    ->setContent('content', '<main>...</main>')
    ->setContent('footer', '<footer>...</footer>');

$htmlGenerator = new HtmlGenerator();
$html = $htmlGenerator->generate($resolved, $components, 'layout');
```

### Generated Output

**CSS:**
```css
.layout {
  display: grid;
  grid-template-areas:
    "header header"
    "sidebar content"
    "footer footer";
  grid-template-columns: 250px 1fr;
  grid-template-rows: 70px auto 60px;
}

.layout__header { grid-area: header; }
.layout__sidebar { grid-area: sidebar; }
.layout__content { grid-area: content; }
.layout__footer { grid-area: footer; }
```

**HTML:**
```html
<div class="layout">
  <div class="layout__header"><h1>My Site</h1></div>
  <div class="layout__sidebar"><nav>...</nav></div>
  <div class="layout__content"><main>...</main></div>
  <div class="layout__footer"><footer>...</footer></div>
</div>
```

## Layout Syntax

### Basic Grid

Use `+`, `-`, and `|` to draw grid boundaries:

```
+----------+-----------+
|  cell1   |   cell2   |
+----------+-----------+
```

### Column Spanning

Cells automatically span columns when they extend across boundaries:

```
+----------+-----------+
|       spanning       |
+----------+-----------+
|  left    |   right   |
+----------+-----------+
```

### Slot Properties

Define properties for each slot in `[slotName]` blocks:

```
[header]
  height: 60px
  background: #333
  padding: 0 20px
  align: center
  justify: space-between
```

Supported properties:
- `width`, `height`, `min-width`, `max-width`, `min-height`, `max-height`
- `padding`, `margin`
- `background`, `border`, `border-radius`
- `align` (maps to `align-items`)
- `justify` (maps to `justify-content`)

### Layout Inheritance

Extend existing layouts and fill their slots:

```
@layout dashboard extends page {
  [content] {
    +--------+--------+--------+
    | card1  | card2  | card3  |
    +--------+--------+--------+
  }

  [card1]
    background: #dbeafe
    padding: 20px
}
```

## Custom Components

Register custom components that implement `ComponentInterface`:

```php
use PhpLayout\Component\ComponentInterface;
use PhpLayout\Component\ComponentRegistry;

class CardComponent implements ComponentInterface
{
    public function render(array $properties, string $content = ''): string
    {
        $bg = $properties['background'] ?? '#fff';
        return "<div class=\"card\" style=\"background: {$bg}\">{$content}</div>";
    }
}

$components = new ComponentRegistry();
$components->register('card', new CardComponent());

// Use in layout definition with component: property
// [mySlot]
//   component: card
//   background: #f0f0f0
```

## Development

### Setup

```bash
composer install
```

### Running Tests

```bash
# All tests
composer test
```

### Browser Preview

Generate and preview E2E test outputs:

```bash
composer preview
# Opens http://localhost:8080
```

## Contributing

Contributions are welcome! Please ensure:

1. **Code quality**: All code follows PSR-12 with `declare(strict_types=1)`
2. **Run checks**: `composer check` - runs tests, formatting, and static analysis
3. **Auto-fix formatting**: `composer format` (if needed)

The `composer check` command verifies everything before submitting a PR.

## License

MIT License - see LICENSE file for details
