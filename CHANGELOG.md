# SmartArray Changelog

## [2.5.0] - 2026-02-12 - Deprecation cleanup and SmartNull improvements

### Added
- `SmartNull->value()` - Explicit method returning null, for IDE support and consistency with SmartString
- `SmartNull->asHtml()` - Converts to empty SmartArrayHtml, preserving internal properties (mysqli, loadHandler)
- `SmartNull->asRaw()` - Converts to empty SmartArray, preserving internal properties
- Use case: `DB::get(...)->first()->asHtml()` now gives a typed SmartArrayHtml with query metadata even when no results

### Changed
- Removed `$warnIfDeprecated`, `$warnIfMissing`, and `$logDeprecations` settings (use PHP's native error handling instead)
- Deprecation and missing-key warnings now always trigger via `@trigger_error()`
- `getArrayCopy()` is now private, use `->toArray()` instead
- `sprintf()` now HTML-encodes `{key}` placeholders for SmartArrayHtml
- `orRedirect()` only checks `headers_sent()` when a redirect is actually needed
- Error messages now correctly show `SmartArray` or `SmartArrayHtml` instead of `SmartArrayBase`

### Deprecated
- `isFirst()`, `isLast()`, `position()`, `isMultipleOf()`, `chunk()`, `smartMap()` - now trigger deprecation notice

---

## [2.4.6] - 2026-01-11 - Improved deprecation handling
- Added `toRaw()` and `toHtml()` as deprecated aliases for `asRaw()` and `asHtml()`
- Improved array access deprecation messages to show context-appropriate suggestions for get, set, and unset operations

---

## [2.4.5] - 2025-12-23

### Changed
- **IDE support**: Added `@implements \Iterator` annotations for PhpStorm foreach type inference

---

## [2.4.4] - 2025-12-23

### Changed
- **Documentation**: Updated README.md and help.txt to use property syntax (`->key`) and `->get()` instead of array brackets
- **Deprecation warnings**: Now include file and line number; removed obsolete `$var['key']` suggestions from error messages

---

## [2.4.3] - 2025-12-22

### Added
- `sprintf($format)` - Applies sprintf formatting to each element, useful for wrapping values in HTML tags.
  - Values are automatically HTML-encoded for SmartArrayHtml (XSS-safe).
  - Always returns SmartArray to prevent double-encoding.
  - Supports `{value}` and `{key}` as readable aliases for sprintf formats `%1$s` and `%2$s`.
  - See "Building Dynamic HTML Tables with sprintf()" in README.md.
  - Example usage:
```php
  // Table cells (auto HTML-encoded)
  <tr><?= $row->sprintf("<td>{value}</td>")->implode() ?></tr>

  // Table headers from keys
  <tr><?= $row->keys()->sprintf("<th>{value}</th>")->implode() ?></tr>

  // Select options with keys as values
  <?= $options->sprintf("<option value='{key}'>{value}</option>")->implode("\n") ?>
  // Output: <option value='us'>United States</option>
  //         <option value='ca'>Canada</option>
  ```

### Changed
- `implode($separator)` - Separator parameter now optional, defaults to empty string
- **Performance**: Removed `ArrayObject` dependency - SmartArray now implements `ArrayAccess`, `IteratorAggregate`, `Countable` directly for ~50% better performance
- **Architecture**: New `SmartArrayBase` abstract class contains all implementation; `SmartArray` and `SmartArrayHtml` are thin subclasses

### Deprecated
- **Array access syntax**: `$array['key']` is deprecated. Use `$array->key` or `$array->get('key')` instead.
  - `SmartArray::$warnIfDeprecated = true` - Echo warnings to output (for development)
  - `SmartArray::$logDeprecations = true` - Log via `trigger_error()` (for error logs)
- `SmartArrayRaw` class - Now an alias for `SmartArray`. Use `SmartArray` directly.

---

## [2.4.2] - 2025-12-03

### Fixed
- Fixed `new()` factory ignoring `useSmartStrings` property - was always returning `SmartArrayRaw` regardless of setting

---

## [2.4.1] - 2025-12-03

### Added
- `column()` alias - Mirrors PHP's `array_column()`, calls `pluck()` or `indexBy()` internally (repurposes deprecated `getColumn()`)

### Fixed
- Fixed `load()` throwing "Property 'loadHandler' does not exist" when called on non-existent elements (returned as SmartNull)
- Minor optimization: removed redundant `array_values()` call in `implode()`

---

## [2.4.0] - 2025-10-07

### Added
- `where($field, $value)` - Two-argument shorthand syntax for filtering by a single field (alternative to `where(['field' => 'value'])`)

## [2.3.0] - 2025-09-21

### Added
- `orRedirect($url)` - Redirects to a URL if array is empty (HTTP 302 Temporary Redirect)
- `asRaw()` method - Return values as raw PHP types for data processing (lazy conversion - returns same object if already raw)
- `asHtml()` method - Return values as HTML-safe SmartString objects (lazy conversion - returns same object if already HTML-safe)
- Direct instantiation methods for explicit typing:
  - `SmartArrayHtml::new($array)` - Creates HTML-safe SmartArray directly (alternative to `SmartArray::new($array)->asHtml()`)
  - `SmartArrayRaw::new($array)` - Creates raw-value SmartArray directly (alternative to `SmartArray::new($array)->asRaw()`)
  - These return a specific type, allowing IDEs to know exactly which methods and return types are available without calling `asHtml()` or `asRaw()`

### Changed
- Minimum PHP version raised to 8.1 (from 8.0)
- `where()` method now uses non-strict comparison (==) to match PHP's type coercion behavior: `'1' == 1`, `0 == false`, `null == false`, but `'' != 0`.
  - This provides type-tolerant matching useful for database/form data where numeric values are often strings

### Deprecated
- `enableSmartStrings()` and `disableSmartStrings()` - use `->asHtml()` and `->asRaw()` instead
- Using boolean second parameter in `SmartArray::new($data, bool $smartStrings)` - use `SmartArray::new($data)->asHtml()` instead
- Deprecation warnings are opt-in via `SmartArray::$logDeprecations = true` or the deprecation setting in your CMS

---

## [2.2.3] - 2025-01-06

### Changed
- More compact error messages

### Fixed
- Cleaned up temporary development files

---

## [2.2.2] - 2025-04-29
> **Bundled with CMS Builder v3.76**  
> Roll-up release - every change from **v2.0.2 -> v2.2.1** is now part of this version.

### Added
- **Configuration & diagnostics**
  - `SmartArray::$warnIfMissing` - toggle "missing-key" warnings (default **true**).
  - `SmartArray::$logDeprecations` - turn legacy-method logging on/off (default **false**).
  - **Friendly alias suggestions** - calling a known alias from common libraries now shows a "did you mean ...?" hint.
- **Smart-object helpers**
  - `smartMap(callable $fn)` - apply a callback while preserving `SmartString`/`SmartArray` wrappers.
  - `each(callable $fn)` - iterate with wrappers intact; returns the original array for chaining.
  - `contains($value)` - returns **true** if the array holds any matching value.
  - Constructor shorthand: `SmartArray::new($data, bool $smartStrings)` - enable/disable SmartStrings in one call.
- **Error-handling shortcuts**   
  - `orDie()`, `or404()`, `orThrow()` - terminate, send 404, or throw when the array is empty.
- **Fluent toggles**   
  - `enableSmartStrings(bool $clone = false)` / `disableSmartStrings(bool $clone = false)` - toggle SmartString output; pass **true** to return a cloned array.

### Changed
- **Deprecations quieter by default** - `$logDeprecations` now defaults to **false**.
- **Alias rename** - `rawValue()` -> `getRawValue()` (old name still works).
- **404 helper** - `or404()` now renders a full HTML error page.
- **Documentation** - inline `help()` moved to `/src/help.txt`; expanded examples in `README.md`.
- **Internal cleanup**
  - Removed legacy ZenDB hooks.
  - `SmartNull` now extends `stdClass`; dynamic-property warnings eliminated.
  - Runtime errors consolidated under base `Exception`.
  - Numerous micro-optimisations and stricter type hints.

### Fixed
- Misc bug fixes and optimizations

---

## [2.0.1] - 2024-12-09

> **Bundled with CMS Builder v3.75**

### Changed
- ZenDB support: Removed references to ->mysqli('error') and ->mysqli('errno') as try/catch is now used for error handling
- Code and text formatting updates

## [2.0.0] - 2024-11-26

### Changed
* BREAKING: Values now stay as raw values by default (previously auto-converted to SmartStrings)
* Added `SmartArray::new()` for raw value handling and `SmartArray::newSS()` for SmartString conversion
* Improved performance through optimized value handling and lazy conversion

### Added
* New methods for array manipulation:
    * `where()` - Filter rows by matching conditions
    * `pluckNth()` - Extract values by position from nested arrays
    * `merge()` - Combine multiple arrays
* New debugging and introspection tools:
    * `debug()` - Enhanced troubleshooting information
    * Added `SmartArray::rawValue()` helper for consistent value conversion
* New database integration and advanced features:
    * `load()` - Load related records from a database column 
    * `mysqli()` - Access database result metadata (affected_rows, insert_id, error, etc.)
    * `root()` - Access root SmartArray from nested children (useful for eager caching and other advanced use cases)
    * `setLoadHandler()` - For configuring related record loading

### Deprecated
- Added support for legacy ZenDB methods, legacy methods still work, but log deprecation errors and will be removed in future
- Renamed `join()` to `implode()` to better match PHP's implode() function (old method still works, but logs deprecation error)

## [1.2.0] - 2024-10-31
* Added `sort()` and `sortBy($column)` methods
* Updated readme.md with examples
* Misc code optimizations and other minor improvements

## [1.1.0] - 2024-10-28
* Added `unique()` method
* Misc code optimizations and other minor improvements

## [1.0.0] - 2024-10-28
* Initial release
