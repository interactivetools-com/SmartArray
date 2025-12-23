# CLAUDE.md

## Build & Test Commands

```bash
./vendor/bin/phpunit                          # Run all tests
./vendor/bin/phpunit tests/SmartArrayTest.php # Run single file
./vendor/bin/phpunit --filter testMethodName  # Run specific test
composer install                              # Install dependencies
```

## Architecture

SmartArray is a fluent collection library for PHP 8.1+ with automatic HTML encoding via SmartString integration.

### Class Hierarchy

```
SmartArrayBase (abstract)       - All implementation lives here
├── SmartArray                  - Returns raw PHP types (string, int, null, etc.)
└── SmartArrayHtml              - Returns SmartString objects (auto HTML-encodes)

SmartArrayRaw                   - DEPRECATED alias for SmartArray
SmartNull                       - Chainable null object (returned for missing keys)
SmartBase                       - Marker interface for instanceof checks
```

### When to Use Which Class

| Class | Use Case | Value Access |
|-------|----------|--------------|
| `SmartArray` | Data processing, APIs, internal logic | `$arr->name` returns `"John"` |
| `SmartArrayHtml` | HTML templates, output | `$arr->name` returns `SmartString` (HTML-safe) |

Convert between them: `$arr->asHtml()` / `$arr->asRaw()` (lazy - returns same object if already correct type)

### Storage & Access

- **Array elements**: Stored in private `$data` array
- **Access methods**: `getElement()` / `setElement()` (internal), `get()` / `__get()` (public)
- **Preferred syntax**: `$arr->key` or `$arr->get('key')`
- **Deprecated syntax**: `$arr['key']` (triggers warning if `$warnIfDeprecated` enabled)

### Internal Properties

Passed between instances via `getInternalProperties()`:
- `loadHandler` - Callback for lazy-loading related data
- `mysqli` - Database result metadata (affected_rows, insert_id)
- `root` - Reference to root SmartArray (for nested arrays)

Calculated during construction (not passed):
- `isFirst`, `isLast`, `position` - Position metadata for nested arrays

### Global Settings

```php
SmartArray::$warnIfMissing    = true;   // Echo warning for missing keys
SmartArray::$warnIfDeprecated = false;  // Echo warning for deprecated usage (array syntax)
SmartArray::$logDeprecations  = false;  // trigger_error() for deprecated usage (for error logs)
```

## Method Reference

### Value Access
`get($key)`, `first()`, `last()`, `nth($index)` - Return element or SmartNull

### Array Information
`count()`, `isEmpty()`, `isNotEmpty()`, `contains($value)`, `toArray()`

### Position (for nested arrays in loops)
`position()`, `isFirst()`, `isLast()`, `isMultipleOf($n)`, `isNth($n)`

### Sorting & Filtering
`sort()`, `sortBy($column)`, `unique()`, `filter($callback)`, `where($conditions)`

### Transformation (return new SmartArray)
`keys()`, `values()`, `indexBy($col)`, `groupBy($col)`, `pluck($col)`, `column()`, `chunk($size)`, `merge()`

### Iteration
`map($callback)` - Receives raw values, returns new SmartArray
`smartMap($callback)` - Receives SmartString/SmartArray wrappers
`each($callback)` - Side effects only, returns `$this`

### Output
`implode($sep)` - Returns string (SmartArray) or SmartString (SmartArrayHtml)
`sprintf($format)` - Supports `{value}` and `{key}` aliases, always returns SmartArray

### Error Handling
`or404()`, `orDie($msg)`, `orThrow($msg)`, `orRedirect($url)` - Handle empty arrays

## Key Implementation Details

1. **Immutable transformations**: All methods return new instances, never modify in place

2. **SmartNull pattern**: Missing keys return SmartNull which allows continued chaining:
   ```php
   $arr->nonexistent->alsoMissing->value(); // Returns null, no errors
   ```

3. **Position metadata**: Set during element insertion (single pass) for nested arrays only

4. **where() uses loose comparison**: `==` intentionally for database/form data tolerance

5. **sprintf() returns SmartArray**: Pre-formatted HTML shouldn't be re-encoded:
   ```php
   $html->sprintf("<li>{value}</li>")->implode(); // Returns SmartArray, not SmartArrayHtml
   ```

6. **Internal methods bypass deprecation**: `getElement()` doesn't trigger warnings; `offsetGet()` does

## Patterns to Follow

### Adding a new transformation method

```php
// In SmartArrayBase - implement the logic
public function myMethod(): self
{
    $result = /* transform $this->toArray() */;
    return new static($result, $this->getInternalProperties());
}

// In SmartArray and SmartArrayHtml - override for return type narrowing
public function myMethod(): static
{
    return parent::myMethod();
}
```

### Deprecation warnings

```php
// Check both flags before doing expensive work
if (!self::$warnIfDeprecated && !self::$logDeprecations) {
    return;
}
self::logDeprecation("Message about what's deprecated and what to use instead");
```

## Test Organization

```
tests/
├── SmartArrayTest.php          # Construction, factories, JSON serialization
├── SmartNullTest.php           # SmartNull chaining behavior
├── GlobalSettingsTest.php      # $warnIfMissing, $warnIfDeprecated, $logDeprecations
├── LegacyMethodsTest.php       # Deprecated method compatibility
├── SmartArrayTestCase.php      # Base test class with helpers
├── TestHelpers.php             # normalizeRaw(), normalizeSS(), getTestRecords()
└── Methods/                    # One file per method
    ├── CreationConversionTest.php  # new, asRaw(), asHtml()
    ├── GetTest.php, FirstTest.php, LastTest.php, NthTest.php
    ├── FilterTest.php, WhereTest.php, SortTest.php, SortByTest.php
    ├── PluckTest.php, PluckNthTest.php, ColumnTest.php
    ├── IndexByTest.php, GroupByTest.php, ChunkTest.php
    ├── MapTest.php, SmartMapTest.php, EachTest.php
    ├── ImplodeTest.php, SprintfTest.php
    ├── PropertyGetTest.php, PropertySetTest.php    # $arr->key syntax
    ├── OffsetGetTest.php, OffsetSetTest.php        # $arr['key'] syntax (deprecated)
    ├── PositionTest.php, IsFirstTest.php, IsLastTest.php, IsMultipleOfTest.php
    ├── LoadTest.php, MysqliTest.php, RootTest.php
    └── ErrorHandlersTest.php   # or404(), orDie(), orThrow(), orRedirect()
```
