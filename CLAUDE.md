# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build & Test Commands

```bash
# Run all tests
./vendor/bin/phpunit

# Run a single test file
./vendor/bin/phpunit tests/SmartArrayTest.php

# Run a specific test method
./vendor/bin/phpunit --filter testMethodName

# Install dependencies
composer install
```

## Architecture Overview

SmartArray is an XSS-safe, fluent collection manipulation library for PHP 8.1+ that extends `ArrayObject`. It provides chainable methods and automatic HTML encoding through integration with the SmartString library.

### Three-Tier Class Hierarchy

```
SmartArray (abstract base)      - Core implementation with all methods
├── SmartArrayRaw (final)       - Returns raw PHP types (default for data processing)
└── SmartArrayHtml (final)      - Returns SmartString objects (HTML-safe output)

SmartNull                       - Chainable null object for graceful failure handling
```

**Key design decisions:**
- `SmartArrayRaw` and `SmartArrayHtml` exist primarily for IDE autocomplete - they override return type hints without adding runtime overhead
- `asRaw()` and `asHtml()` use lazy conversion - they return the same object if already the correct type
- All transformation methods return new instances (immutable/functional approach)
- Both array syntax (`$arr['key']`) and object syntax (`$arr->key`) access the internal array storage via `ARRAY_AS_PROPS` flag

### Property System

Properties are stored separately from array elements using private `getProperty()`/`setProperty()` methods:
- `useSmartStrings`, `loadHandler`, `mysqli`, `root`, `isFirst`, `isLast`, `position`

Global static settings:
- `SmartArray::$warnIfMissing` - Toggle missing-key warnings (default: true)
- `SmartArray::$logDeprecations` - Toggle deprecation logging (default: false)

### SmartNull Pattern

`SmartNull` implements Iterator, ArrayAccess, Countable, and JsonSerializable to allow unlimited method chaining without null checks. Returned when accessing missing keys.

## Test Organization

Tests are organized by functional area:
- `SmartArrayTest.php` - Construction, factories, conversions, JSON serialization
- `ValueAccessTest.php` - get(), first(), last(), nth(), defaults, missing keys
- `ArrayInformationTest.php` - isEmpty(), isNotEmpty(), contains()
- `PositionLayoutTest.php` - position(), isFirst(), isLast(), isMultipleOf(), chunk()
- `SortingFilteringTest.php` - sort(), sortBy(), unique(), filter(), where()
- `TransformationTest.php` - keys(), values(), indexBy(), groupBy(), pluck(), implode(), map(), each()
- `GlobalSettingsTest.php` - Static settings behavior
- `SmartNullTest.php` - SmartNull behavior and chaining

## Key Implementation Details

- `where()` uses loose comparison (`==`) intentionally for database/form data tolerance
- Position metadata (`isFirst`, `isLast`, `position`) is calculated during construction via O(n) iteration
- `map()` receives raw values; `smartMap()` receives SmartString/SmartArray wrappers
- `each()` returns `$this` for chaining (used for side effects)
- `implode()` returns `string` from SmartArrayRaw, `SmartString` from SmartArrayHtml
