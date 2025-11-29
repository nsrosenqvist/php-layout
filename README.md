# PHP Layout Engine

A declarative layout engine for PHP that uses ASCII box-drawing syntax to visually define HTML structures and generate CSS Grid layouts.

## Features

- **Visual Layout Definition**: Define layouts using intuitive ASCII box-drawing syntax
- **CSS Grid Output**: Generates modern CSS Grid layouts with `grid-template-areas`
- **Layout Inheritance**: Extend layouts with the `extends` keyword
- **Nested Grids**: Support for nested grid structures within slots
- **Custom Components**: Register your own components for dynamic content rendering
- **PSR-16 Caching**: Pluggable caching with any PSR-16 implementation, filesystem cache included
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
    component: box
    grid-height: 70px
    background: #2563eb
    padding: 0 20px

  [sidebar]
    component: box
    grid-width: 250px
    background: #f1f5f9
    padding: 20px

  [content]
    component: ...

  [footer]
    component: box
    grid-height: 60px
    background: #1e293b
}
```

### Parse and Generate

```php
<?php

use PhpLayout\Loader\LayoutLoader;
use PhpLayout\Generator\CssGenerator;
use PhpLayout\Generator\HtmlGenerator;
use PhpLayout\Component\ComponentRegistry;

// Load and resolve layout from file
$loader = new LayoutLoader();
$resolved = $loader->load('layouts/page.lyt', 'page');

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
  component: box
  grid-height: 60px
  background: #333
  padding: 0 20px
```

#### The `component` Property

The `component:` property specifies which registered component renders the slot:

- `component: box` - Use the component registered as "box"
- `component: ...` - Mark as a container/placeholder slot (no component renders)
- *(omitted)* - Slot has no component unless a default is set

#### Grid Layout Properties

These properties are used by the layout engine to define the CSS Grid structure:

- `grid-width` - Defines column size in `grid-template-columns` (defaults to `1fr`)
- `grid-height` - Defines row size in `grid-template-rows` (defaults to `auto`)

#### Component Properties

All other properties are passed to the component specified by `component:`. Each component defines its own supported properties.

The built-in `BoxComponent` supports:
- `width`, `height`, `min-width`, `max-width`, `min-height`, `max-height`
- `padding`, `margin`
- `background`, `border`, `border-radius`
- `align` (maps to `align-items`)
- `justify` (maps to `justify-content`)

Custom components can define and require their own properties.

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
    component: card
    background: #dbeafe
    padding: 20px

  [card2]
    component: card
    background: #dcfce7
    padding: 20px

  [card3]
    component: card
    background: #fef3c7
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

// Use in layout definition with explicit component: property
// [mySlot]
//   component: card
//   background: #f0f0f0
```

### Default Component

You can set a default component to use when a slot has no explicit `component:` property:

```php
use PhpLayout\Component\BoxComponent;
use PhpLayout\Component\ComponentRegistry;

$components = new ComponentRegistry();
$components
    ->register('box', new BoxComponent())
    ->setDefaultComponent('box');

// Now slots without "component:" will render using BoxComponent
```

### Render Context

For components that need access to page-level data (like a header displaying the page title), implement `ContextAwareComponentInterface`:

```php
use PhpLayout\Component\ContextAwareComponentInterface;
use PhpLayout\Render\RenderContext;

// Define a typed data model for your pages
class PageData
{
    public function __construct(
        public readonly string $title,
        public readonly string $author,
        public readonly \DateTimeImmutable $publishedAt,
    ) {}
}

// Create a context-aware component
class HeaderComponent implements ContextAwareComponentInterface
{
    // Fallback for non-context rendering
    public function render(array $properties, string $content = ''): string
    {
        return '<header>Default Header</header>';
    }

    // Called when context is available
    public function renderWithContext(RenderContext $context, string $content = ''): string
    {
        $data = $context->getData();
        $title = $data instanceof PageData ? $data->title : 'Untitled';
        $bg = $context->getProperty('background', '#333');

        return "<header style=\"background: {$bg}\"><h1>{$title}</h1></header>";
    }
}
```

Pass context when rendering:

```php
$components = new ComponentRegistry();
$components
    ->register('header', new HeaderComponent())
    ->setContext(new PageData(
        title: 'My Article',
        author: 'Jane Doe',
        publishedAt: new \DateTimeImmutable(),
    ));

$html = $htmlGenerator->generate($resolved, $components, 'layout');
// Header will display "My Article" as the title
```

The `RenderContext` provides:
- `getData()` - The typed data model you passed via `setContext()`
- `getProperties()` - Slot properties from the layout definition
- `getProperty(string $key, string $default)` - Get a single property
- `getSlotName()` - The name of the slot being rendered

## Caching

The `LayoutLoader` supports PSR-16 caching for improved performance. File-based loading uses modification time for fast cache invalidation.

### Basic Usage

```php
use PhpLayout\Loader\LayoutLoader;
use PhpLayout\Cache\FilesystemCache;

// Create loader with filesystem cache
$cache = new FilesystemCache('/path/to/cache');
$loader = new LayoutLoader(cache: $cache);

// Load layout - first call parses and caches, subsequent calls use cache
$resolved = $loader->load('layouts/page.lyt', 'page');

// File changes automatically invalidate cache (uses mtime)
```

### With TTL

```php
// Cache expires after 1 hour (3600 seconds)
$loader = new LayoutLoader(cache: $cache, ttl: 3600);
```

### Loading from Strings

For dynamic layout sources, use `loadFromString()` which caches based on content hash:

```php
$resolved = $loader->loadFromString($layoutString, 'page');
```

### Using Other PSR-16 Caches

Any PSR-16 compliant cache works:

```php
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Cache\Adapter\RedisAdapter;

// Example with Symfony Cache + Redis
$redis = RedisAdapter::createConnection('redis://localhost');
$cache = new Psr16Cache(new RedisAdapter($redis));

$loader = new LayoutLoader(cache: $cache);
```

### Cache Invalidation

- **File-based**: Cache automatically invalidates when file modification time changes
- **String-based**: Cache key includes content hash, so changes create new entries
- **Manual**: Call `$loader->clearCache()` to clear all cached layouts

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
# Opens output/dashboard.html in your default browser
```

## Contributing

Contributions are welcome! Please ensure:

1. **Code quality**: All code follows PSR-12 with `declare(strict_types=1)`
2. **Run checks**: `composer check` - runs tests, formatting, and static analysis
3. **Auto-fix formatting**: `composer format` (if needed)

The `composer check` command verifies everything before submitting a PR.

## License

MIT License - see LICENSE file for details
