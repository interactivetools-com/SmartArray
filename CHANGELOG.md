# SmartArray Changelog

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
