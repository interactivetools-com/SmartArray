# SmartArray Changelog

## [2.6.6] - 2026-04-27
> **Bundled with CMS Builder v3.83**
> Roll-up release - every change from **v2.4.3 → v2.6.6** is now part of this version.

### Added
- `SmartArrayBase::$onOffsetAccess` - Controls how deprecated `$array['key']` offset access is surfaced. Three modes:
  - `'log'` - `trigger_error(E_USER_DEPRECATED)` only (silent unless surfaced by error handler)
  - `'notify'` - Echoes a visible `Deprecated:` notice + `trigger_error()` (default)
  - `'throw'` - Throws `RuntimeException` (strict mode for new installs)
  - Apps running legacy code can downgrade to `'log'` during migration:
```php
    SmartArrayBase::$onOffsetAccess = 'log';
    ```
- `whereNot($field, $value)` - Returns elements where a field does NOT match the value. Inverse of `where()`. Uses loose comparison. Rows with a missing field are kept.
- `whereInList($field, $value)` - Returns elements where a tab-delimited list field contains the specified value. Matches discrete values (not substrings). Designed for CMS Builder checkbox groups and multi-select fields.
- `sprintf($format)` - Applies sprintf formatting to each element, useful for wrapping values in HTML tags
  - Values are automatically HTML-encoded for SmartArrayHtml (XSS-safe)
  - Returns SmartArray (not SmartArrayHtml) to prevent double-encoding
  - Supports `{value}` and `{key}` as readable aliases for sprintf formats `%1$s` and `%2$s`
  - Example usage:
```php
  // Table cells (auto HTML-encoded)
  <tr><?= $row->sprintf("<td>{value}</td>")->implode() ?></tr>

  // Select options with keys as values
  <?= $options->sprintf("<option value='{key}'>{value}</option>")->implode("\n") ?>
  ```
- **SmartNull improvements**
  - `SmartNull->value()` - Returns null explicitly, for IDE support and consistency with SmartString
  - `SmartNull->asHtml()` - Converts to empty SmartArrayHtml, preserving query metadata (mysqli, loadHandler)
  - `SmartNull->asRaw()` - Converts to empty SmartArray, preserving query metadata
  - Enables patterns like `DB::get(...)->first()->asHtml()` for typed results even when no rows found
- **IDE support**: Added `@implements \Iterator` annotations for PhpStorm foreach type inference

### Changed
- **Default offset-access behavior is now visible.** Offset-access deprecations now echo a `Deprecated:` notice to output in addition to `trigger_error(E_USER_DEPRECATED)` (matches the `warnIfMissing()` pattern). Apps that need silent deprecations should set `SmartArrayBase::$onOffsetAccess = 'log'`.
- **Performance**: ~50% better performance via internal architecture rewrite
- **Architecture**: New `SmartArrayBase` abstract class contains all implementation; `SmartArray` and `SmartArrayHtml` are thin subclasses
- `implode($separator)` - Separator parameter now optional, defaults to empty string
- Replaced `$warnIfDeprecated`, `$warnIfMissing`, and `$logDeprecations` settings with PHP's native `@trigger_error()`
- Deprecation warnings now include file and line number for easier debugging
- Updated docs to use property syntax (`->key`) instead of array brackets
- Error messages now show the correct class name (`SmartArray` or `SmartArrayHtml`)

### Deprecated
- **Array access syntax**: `$array['key']` is deprecated - use `$array->key` or `$array->get('key')` instead
- `SmartArrayRaw` class - now an alias for `SmartArray`, use `SmartArray` directly
- `isFirst()`, `isLast()`, `position()`, `isMultipleOf()`, `chunk()`, `smartMap()` - now trigger deprecation notice
- `toRaw()` and `toHtml()` - use `asRaw()` and `asHtml()` instead

### Fixed
- **SmartNull HTML mode.** Method chains from a missing key on `SmartArrayHtml` now return `SmartArrayHtml`, not `SmartArray`.
- **`warnIfMissing()` offset mode.** Checks the array's own keys instead of the first row's keys. A key in row 0 was masking a missing top-level key.
- **`$arr[] = $value` deprecation suggestion.** Now reads `->set($key, $value) using an explicit key` instead of suggesting an empty-string key.
- **Class short names on Linux.** `print_r` and `->debug()` show `SmartArrayHtml` instead of the full `Itools\SmartArray\SmartArrayHtml`.
- **`->debug(1)` root label.** Shows the actual class instead of always saying `SmartArray`.
- `where()` now handles SmartString values passed as conditions

---

## [2.4.2] - 2025-12-03
> **Bundled with CMS Builder v3.81**
> Roll-up release - every change from **v2.4.1 → v2.4.2** is now part of this version.

### Added
- `column()` - Mirrors PHP's `array_column()`, calls `pluck()` or `indexBy()` internally

### Fixed
- Fixed `new()` factory ignoring `useSmartStrings` property
- Fixed `load()` throwing "Property 'loadHandler' does not exist" on SmartNull results

---

## [2.4.0] - 2025-10-07
> **Bundled with CMS Builder v3.80**
> Roll-up release - every change from **v2.3.0 → v2.4.0** is now part of this version.

### Added
- `where($field, $value)` - Two-argument shorthand for filtering by a single field
- `orRedirect($url)` - Redirects to a URL if array is empty (HTTP 302)
- `asRaw()` / `asHtml()` - Switch between raw PHP values and HTML-safe SmartString output (no-op if already in requested mode)
- `SmartArrayHtml::new($array)` - Create HTML-safe SmartArray directly

### Changed
- Minimum PHP version raised to 8.1 (from 8.0)
- `where()` now uses loose comparison (==) for type-tolerant matching - useful for database/form data where numeric values are often strings

### Deprecated
- `enableSmartStrings()` / `disableSmartStrings()` - use `->asHtml()` and `->asRaw()` instead
- Boolean second parameter in `SmartArray::new()` - use `SmartArrayHtml::new()` instead

---

## [2.2.3] - 2025-06-01
> **Bundled with CMS Builder v3.79**

### Changed
- More compact error messages

---

## [2.2.2] - 2025-04-29
> **Bundled with CMS Builder v3.76**
> Roll-up release - every change from **v2.0.2 → v2.2.1** is now part of this version.

### Added
- **Configuration & diagnostics**
  - `SmartArray::$warnIfMissing` - toggle "missing-key" warnings (default **true**)
  - `SmartArray::$logDeprecations` - turn legacy-method logging on/off (default **false**)
  - **Friendly alias suggestions** - calling a known alias from common libraries now shows a "did you mean ...?" hint
- **Smart-object helpers**
  - `smartMap(callable $fn)` - apply a callback while preserving `SmartString`/`SmartArray` wrappers
  - `each(callable $fn)` - iterate with wrappers intact; returns the original array for chaining
  - `contains($value)` - returns **true** if the array holds any matching value
  - Constructor shorthand: `SmartArray::new($data, bool $smartStrings)` - enable/disable SmartStrings in one call
- **Error-handling shortcuts**
  - `orDie()`, `or404()`, `orThrow()` - terminate, send 404, or throw when the array is empty
- **Fluent toggles**
  - `enableSmartStrings(bool $clone = false)` / `disableSmartStrings(bool $clone = false)` - toggle SmartString output; pass **true** to return a cloned array

### Changed
- **Deprecations quieter by default** - `$logDeprecations` now defaults to **false**
- **Alias rename** - `rawValue()` -> `getRawValue()` (old name still works)
- **404 helper** - `or404()` now renders a full HTML error page
- **Documentation** - inline `help()` moved to `/src/help.txt`; expanded examples in `README.md`
- **Internal cleanup**
  - Removed legacy ZenDB hooks
  - `SmartNull` now extends `stdClass`; dynamic-property warnings eliminated
  - Numerous micro-optimisations and stricter type hints

### Fixed
- Misc bug fixes and optimizations

---

## [2.0.1] - 2024-12-09
> **Bundled with CMS Builder v3.75**
> Roll-up release - every change from **v2.0.0 → v2.0.1** is now part of this version.

### Changed
- BREAKING: Values now stay as raw values by default (previously auto-converted to SmartStrings)
- Added `SmartArray::new()` for raw value handling and `SmartArray::newSS()` for SmartString conversion
- Improved performance through optimized value handling and lazy conversion
- ZenDB support: Removed references to `->mysqli('error')` and `->mysqli('errno')` as try/catch is now used

### Added
- New methods for array manipulation:
  - `where()` - Filter rows by matching conditions
  - `pluckNth()` - Extract values by position from nested arrays
  - `merge()` - Combine multiple arrays
- New debugging and introspection tools:
  - `debug()` - Enhanced troubleshooting information
  - `SmartArray::rawValue()` - Helper for consistent value conversion
- New database integration:
  - `load()` - Load related records from a database column
  - `mysqli()` - Access database result metadata (affected_rows, insert_id, etc.)
  - `root()` - Access root SmartArray from nested children
  - `setLoadHandler()` - Configure related record loading

### Deprecated
- Legacy ZenDB methods still work but log deprecation errors
- `join()` renamed to `implode()` (old name still works)

---

## [1.2.0] - 2024-10-31
> **Bundled with CMS Builder v3.74**
> Roll-up release - every change from **v1.0.0 → v1.2.0** is now part of this version.

- Initial release of SmartArray
- `sort()` and `sortBy($column)` for array sorting
- `unique()` for removing duplicate values

## [1.0.0] - 2024-10-28
- Initial release
