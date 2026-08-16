# SmartArray Changelog

> **Upgrading?** See [UPGRADING.md](UPGRADING.md) for the checks that matter,
> per version - tagged releases roll up every change since the previous tag.
> Versions bundled with CMS Builder are marked on their sections.

## [3.0.0] - [UNRELEASED]

> **Bundled with CMS Builder v3.85**

The headlines: building arrays is over 3x faster, and method
names now match JavaScript and PHP conventions (old names keep working).
Everything else is hardening and fixes.

### Requirements

- Now requires SmartString 3.0+ (previously any version); Composer picks it
  up automatically unless your own composer.json pins `itools/smartstring`
  lower

### Security

- **Warnings HTML-encode dynamic keys** - missing-key warnings and
  deprecation notices echoed keys like `$_GET['sort']` unencoded, a
  reflected XSS vector
- **`json_encode()` survives malformed UTF-8 in keys** - bad bytes become
  � instead of the whole document returning false (values were already
  handled)
- **`debug()` and `help()` escape `</xmp`** - a stored value containing
  `</xmp>` ended the debug block early, so the rest of it parsed as live
  HTML

### Added

- **`where($field)` / `whereNot($field)`** - one-argument forms filter by
  PHP's `empty()` rule: `where('featured')` keeps rows where the field is
  non-empty, `whereNot('featured')` keeps the rest

### Performance

- **Building arrays is ~3.4x faster** (25-row record set: 15.5 → 4.5
  microseconds), `toArray()` ~4.7x, and `foreach` up to ~1.3x. Numbers and
  how the speedups work: [docs/performance.md](docs/performance.md)

### Renamed

Old names keep working - nothing to update. IDEs like PHPStorm show them in
strikethrough and offer a one-click rename.

| Old          | New          | Notes                                                          |
|--------------|--------------|----------------------------------------------------------------|
| `nth()`      | `at()`       | by position, matching JavaScript's `Array.at()`; `$row->key` is by key |
| `pluckNth()` | `columnAt()` | by position; `column()` is by key                              |
| `pluck()`    | `column()`   | same arguments; matches PHP's `array_column()`                 |

### Parameter renames (named arguments only)

- `sortBy(flags:)` was `type:` - named-argument calls only; the old name
  fails with a clear "Unknown named parameter" Error

### Deprecated

These still work with no runtime notice, they're just no longer featured in
the docs - IDEs show a strikethrough with the replacement.

- **`get()` and `set()`** - use property access: `$row->name`,
  `$row->name ?? 'n/a'`, `$row->name = $value` (for non-literal
  defaults in HTML mode use `->or()` - a `??` fallback skips encoding)
- **`each()`** - a `foreach` loop does the same in plain PHP
- **`sprintf()`** - use `map()` with an inline format string:
  `$list->map(fn($v) => "<li>$v</li>")`. `%c` now throws - it converts
  numbers to raw characters after HTML encoding; other directives unchanged
- **`help()`** - the docs on GitHub replaced the built-in cheat sheet

### Removed

- **`usingSmartStrings()`, `setLoadHandler()`, `newSmartNull()`** - never
  documented, no found uses; replacements in [UPGRADING.md](UPGRADING.md)

### Behavior changes

- **Value matching is more precise** in `where()`, `whereNot()`,
  `whereInList()`, and `contains()` - numbers still match numeric strings,
  but strings compare exactly, null only matches null (like SQL IS NULL),
  and true/false mean 1/0. See [UPGRADING.md](UPGRADING.md)
- **`isset()`, `empty()`, and `??` treat a stored NULL as missing** -
  matching plain PHP arrays, so `??` fallbacks now fire on NULL columns.
  See [UPGRADING.md](UPGRADING.md)
- **Row-only methods throw on mixed arrays** - a scalar next to rows
  (usually a wrapped API response) throws instead of being silently
  skipped; database results are unaffected. See
  [UPGRADING.md](UPGRADING.md)
- **`SmartArray::new($data, true)` throws** - a boolean that contradicts
  the class was silently ignored, returning raw values where HTML-safe
  ones were asked for. See [UPGRADING.md](UPGRADING.md)
- **Missing fields chain cleanly** - a missing key stays a SmartNull
  through the whole chain, so `$row->missing->trim()->implode(', ')` works
  where it previously threw; output is unchanged (echoes `""`, `or()`
  still fires)
- **Missing-key warnings fire only on rows inside a result set** - where
  keys are column names and a miss is almost always a typo; derived
  collections and standalone arrays render blank silently, so fallbacks
  like `$authorById->{$id}->or('Unknown Author')` chain cleanly
- **Smart values copy between arrays** - `set()`, `->key = $value`, and
  array assignment unwrap SmartString/SmartArray/SmartNull values instead
  of throwing, converting to the target array's mode; `get()` and `at()`
  unwrap Smart keys the same way
- **Row positions resolve on first use, not at build time** - a row added
  after construction reports its real `position()` and `isLast()` instead
  of 0/false, the row that was last at build stops reporting `isLast()`,
  and late-added rows now warn on missing keys like any other row; once
  read, a row's position is kept

### Fixed

- **Float key values throw in `indexBy()`, `groupBy()`, and `column()`** -
  PHP's float-to-int key truncation keyed `19.99` and `19.50` both as
  `19`, silently losing a row; the error asks for strings instead
- **Rows missing the sort or index field are handled** - `sortBy()` sorts
  them first (like MySQL ORDER BY) instead of throwing, and `indexBy()`
  and `column()` key them under `""` instead of a leftover numeric key
  that looked like real data
- **Errors name your file, not the library's** - deprecation notices and
  error messages report the right caller when a call routes through
  SmartString, and unknown methods on `SmartNull` throw the same helpful
  Error as the rest of the library

### Minor

Also: writes to a `SmartNull` throw instead of silently discarding the
value, raw-mode arrays throw on SmartString-style fallbacks like `->or()`
on missing keys (use `??` instead), `print_r()` and `var_dump()` show
clean array data (use `debug()` for exact types), `orDie()` and `or404()`
exit with status 1 so shell scripts see the failure, deprecated names are
real declared methods so IDEs and `method_exists()` see them, `help()`
and `debug()` print plain text on the command line, and a couple dozen
small fixes to error messages and `load()`, `column()`, `get()`, and
`debug()` edge cases.

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
