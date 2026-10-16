# SmartArray Changelog

> **Upgrading?** See [UPGRADING.md](UPGRADING.md) for the checks that matter,
> per version - tagged releases roll up every change since the previous tag.
> Versions bundled with CMS Builder are marked on their sections.

## [3.0.0] - [UNRELEASED]

> **Bundled with CMS Builder v3.85**

The headlines: building arrays is about 4x faster, and method
names now match JavaScript and PHP conventions (old names keep working).
Everything else is security and bug fixes.

### Requirements

- Now requires SmartString 3.0+; Composer installs it automatically unless
  your composer.json pins `itools/smartstring` lower

### Security

- **Warnings and debug output encode keys** - missing-key warnings
  and deprecation notices printed keys like `$_GET['sort']` unencoded, so a
  crafted link could add script to a page. `debug()` had a similar gap with
  stored values

### Added

- **`where($field)` / `whereNot($field)`** - one-argument forms filter by
  PHP's `empty()` rule: `where('featured')` keeps rows where the field is
  non-empty, `whereNot('featured')` keeps the rest
- **`set404Handler()`** - show your own 404 page from `or404()` instead of
  the built-in one; SmartString has its own, so set both

### Performance

- **Building arrays is ~4x faster** than 2.6.7, `toArray()` ~5x, and
  `foreach` ~1.5x. Measured on a 25-row news page with PHP 8.1 and 8.5

### Renamed

Old names keep working - nothing to update. IDEs like PHPStorm show them in
strikethrough and offer a one-click rename.

| Old          | New          | Notes                                       |
|--------------|--------------|---------------------------------------------|
| `nth()`      | `at()`       | by position, like JavaScript's `Array.at()` |
| `pluckNth()` | `columnAt()` | by position                                 |
| `pluck()`    | `column()`   | same arguments, like PHP's `array_column()` |

### Parameter renames (named arguments only)

- `sortBy(flags:)` was `type:` - only matters if you pass it by name

### Deprecated

These still work, with no notice at runtime. IDEs show a strikethrough and
the replacement.

- **`get()` and `set()`** - use `$row->name`, `$row->name ?? 'n/a'`, and
  `$row->name = $value`. In HTML mode, use `->or()` for defaults that come
  from data: `??` doesn't encode them
- **`each()`** - use a `foreach` loop
- **`sprintf()`** - use `map()`: `$list->map(fn($v) => "<li>$v</li>")`
- **`help()`** - read the docs on GitHub

### Removed

- **`usingSmartStrings()`, `setLoadHandler()`, `newSmartNull()`** -
  undocumented and unused; replacements in [UPGRADING.md](UPGRADING.md)

### Behavior changes

- **Value matching is more precise** in `where()`, `whereNot()`,
  `whereInList()`, and `contains()` - strings must match exactly, null
  matches only null, and true/false mean 1/0; numbers still match numeric
  strings. See [UPGRADING.md](UPGRADING.md)
- **`isset()`, `empty()`, and `??` treat a stored NULL as missing**, like
  plain PHP arrays, so `??` fallbacks now fire on NULL columns. See
  [UPGRADING.md](UPGRADING.md)
- **Row methods skip non-row elements** - `where()`, `sortBy()`,
  `indexBy()`, and the other row methods ignore scalars mixed in with the
  rows. See [UPGRADING.md](UPGRADING.md)
- **`SmartArray::new($data, true)` throws** - the `true` was silently
  ignored, so you got raw values when you asked for HTML-safe ones. See
  [UPGRADING.md](UPGRADING.md)
- **Missing fields chain cleanly** - `$row->missing->trim()->implode(', ')`
  works instead of throwing, and still outputs `""`
- **Missing-key warnings only fire on rows from a result set**, where a
  miss is usually a typo. Other lookups, like
  `$authorById->{$id}->or('Unknown')`, stay silent
- **Values copy between arrays** - assigning a field or row from one array
  into another works instead of throwing
- **`isLast()` and `position()` count rows added after the array was
  built** - they used to report 0/false for those rows

### Fixed

- **Float key values throw in `indexBy()`, `groupBy()`, and `column()`** -
  PHP turned `19.99` and `19.50` both into key `19`, silently losing a row
- **Rows missing the sort or index field work** - `sortBy()` sorts them
  first instead of throwing, and `indexBy()` and `column()` key them
  under `""`
- **`json_encode()` handles malformed UTF-8 in keys** - bad bytes become �
  instead of the whole result being false
- **Errors name your file and line**, not the library's

### Minor

Also: writing through a missing key throws instead of silently doing
nothing, raw-mode arrays throw on `->or()` for a missing key (use `??`),
`print_r()` and `var_dump()` show plain array data, `orDie()` and `or404()`
exit with status 1, `or404()` works like SmartString's, `help()` and
`debug()` print plain text on the command line, and small fixes to error
messages and edge cases.

## [2.7.0] - 2026-07-07

### Security

- `json_encode($smartArray)` now substitutes malformed UTF-8 bytes with � (U+FFFD) instead of returning false, so one corrupt byte in a value no longer breaks the whole page (matches SmartString v2.7.0)

### Changed

- `or404()`, `orDie()`, `orThrow()` message parameter renamed to `$text` and documented as HTML-encoded before output

## [2.6.7] - 2026-04-27

> **Bundled with CMS Builder v3.83**

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
- `isMultipleOf()`, `chunk()`, `smartMap()` - now trigger deprecation notice (isFirst/isLast/position were promoted back to first-class methods in 2.6.2)
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

### Added

- `column()` - Mirrors PHP's `array_column()`, calls `pluck()` or `indexBy()` internally

### Fixed

- Fixed `new()` factory ignoring `useSmartStrings` property
- Fixed `load()` throwing "Property 'loadHandler' does not exist" on SmartNull results

---

## [2.4.0] - 2025-10-07

> **Bundled with CMS Builder v3.80**

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

- Initial release of SmartArray
- `sort()` and `sortBy($column)` for array sorting
- `unique()` for removing duplicate values

## [1.0.0] - 2024-10-28

- Initial release
