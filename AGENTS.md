# Agent Instructions

This document provides instructions for AI coding agents working on this project.

---

## Critical Rules

### Before Considering Any Task Complete

Always run the check suite before marking a task as done:

```bash
composer check
```

This command runs all quality checks in sequence:

1. **Code formatting** (`composer format:check`) - Verifies PER-CS compliance
2. **Type check** (`composer typecheck`) - Runs PHPDoc type validation
3. **Static analysis** (`composer analyze`) - Runs PHPStan analysis
4. **Tests** (`composer test`) - Runs the PHPUnit test suite with Paratest

**All checks must pass for the code to be considered complete.**

### Version Control

Never try to commit changes yourself. The user is in charge of what gets saved and discarded.

### Iterative Development

Never implement something as just a placeholder or a method definition containing just `// TODO`, unless specifically asked to take an iterative approach.

### Documentation

- If you added or changed environment variables, document them
- If you make significant architectural changes, update `README.md` to reflect this


---

## Code Quality

### Coding Standards

- All code must follow **PER-CS 3.0** (PER Coding Style) coding standard
- All PHP files must include `declare(strict_types=1);` at the top
- Use modern PHP 8.4+ standards with strict typing
- Prefer immutable value objects where appropriate
- Use DTOs rather than array shapes
- Use enums instead of string constants
- Borrow concepts from DDD to encourage good and scalable design patterns

### Project Structure

```
src/
├── Ast/           # Data structures representing parsed layouts (Grid, Layout, Slot)
├── Cache/         # PSR-16 cache implementations (filesystem)
├── Component/     # Component system for rendering slot content
├── Generator/     # Output generators (HTML, CSS)
├── Loader/        # High-level API for loading layout files
├── Parser/        # Lexer and parser for .lyt files
└── Resolver/      # Layout inheritance resolution
```

### Architecture Overview

This is a **declarative layout engine** that transforms ASCII box-drawing syntax (`.lyt` files) into HTML structures with CSS Grid layouts.

**Data flow:**

1. **Lexer** tokenizes `.lyt` file content
2. **LayoutParser** builds AST (`Layout`, `Grid`, `SlotDefinition`)
3. **LayoutResolver** processes inheritance chains into `ResolvedLayout`
4. **Generators** produce final HTML and CSS output

**Key concepts:**

- **Layouts** define visual grid structures using box-drawing characters
- **Slots** are named regions within grids that can hold content or nested grids
- **Components** render dynamic content into slots at generation time
- **Inheritance** allows layouts to extend and override parent layouts

### Testing Standards

- Code should be adequately covered by tests
- Use PHPUnit's own mocking functionality, **never use Mockery**
- Use **camelCase** naming for test methods, NOT snake_case
- Use modern PHPUnit attributes (`#[Test]`, `#[DataProvider]`), not the `test*` prefix
- Tests go in `tests/Unit/` for unit tests and `tests/Feature/` for integration tests
- Test fixtures (sample PHP files) go in `tests/fixtures/`

### Available Commands

| Command | Description |
|---------|-------------|
| `composer check` | Run all checks (formatting, static analysis, PHPDoc type validation, tests) |
| `composer format` | Auto-fix PHP code formatting issues |
| `composer format:check` | Check PHP formatting without making changes |
| `composer analyze` | Run PHPStan static analysis |
| `composer typecheck` | Run PHPDoc type validation |
| `composer test` | Run PHPUnit tests with Paratest |
| `composer test:coverage` | Run tests with HTML coverage report |

### Fixing Check Failures

| Check | Fix |
|-------|-----|
| PHP formatting errors | Run `composer format` to auto-fix |
| PHP typecheck errors | Correct PHPDoc annotations manually |
| Static analysis errors | Review PHPStan output and fix type issues manually |
| Test failures | Debug and fix the failing tests |

---

## Development Guidelines

### Adding New Features

1. Write tests first (TDD approach recommended)
2. Implement the feature in `src/`
3. Run `composer check` to verify everything passes
4. Update documentation if needed

### File Naming Conventions

- Classes: PascalCase (e.g., `TypeComparator.php`)
- Interfaces: PascalCase with `Interface` suffix (e.g., `FormatterInterface.php`)
- Test files: Mirror source structure with `Test` suffix (e.g., `TypeComparatorTest.php`)

### Type Hints

- Always use parameter type hints
- Always use return type hints
- Use union types where appropriate (`string|int`)
- Use `?Type` for nullable parameters, `Type|null` for nullable returns
- If the native type hinting and parameter name is adequate for documentation purposes, there is no need to also define it using `@param` 
