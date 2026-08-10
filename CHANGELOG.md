# SmartArray Changelog

> **Upgrading?** See [UPGRADING.md](UPGRADING.md) for the checks that matter,
> per version - tagged releases roll up every change since the previous tag.
> Versions bundled with CMS Builder are marked on their sections.

## [3.0.0] - [UNRELEASED]

> **Bundled with CMS Builder v3.85**

The headlines: building arrays and `toArray()` are ~3x faster, and method
names now match JavaScript and PHP conventions (old names keep working).
Everything else is hardening and fixes.

### Requirements

- Now requires SmartString 3.0+ (previously any version). Composer picks it
  up on `composer update`; only a pinned `itools/smartstring` constraint in
  your own composer.json needs changing.

### Security

- Missing-key warnings and array-syntax deprecation notices HTML-encode the
  key before echoing it. With a dynamic key (`->get($_GET['sort'])`,
  `$arr[$_GET['sort']]`), a key containing HTML reached the browser
  unencoded - a reflected XSS vector. The `trigger_error()` copy carries the
  same encoded key.
- `json_encode($smartArray)` substitutes malformed UTF-8 in keys with �
  (U+FFFD), not just values - one corrupt byte in a key no longer makes it
  return false and lose the whole document.
- `debug()` and `help()` escape a literal `</xmp` in web output as `<\/xmp`,
  same escaping as CMSB's `xmp_safe()`. A stored value containing `</xmp>`
  ended the `<xmp>` block early, so the rest of the value parsed as live
  HTML on any page that called `debug()` on it.

### Added

- `where($field)` and `whereNot($field)` with just a field name filter by
  PHP's `empty()` rule: `where('featured')` keeps rows where the field is
  non-empty, `whereNot('featured')` keeps the rest, and every row lands in
  exactly one of the two. The one-argument form previously ran as
  `where($field, null)`, a loose match against NULL.

### Performance

- Building arrays is ~3x faster (25-row record set: 15.9 → 5.3
  microseconds), `toArray()` is ~3x faster (flat 4-field row: 81 → 26 ns),
  and `foreach` is 1.2-1.3x faster when no value needs wrapping. Numbers and
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

These only matter if you write parameter names in calls - calls using an old
name fail with a clear "Unknown named parameter" Error:

- `sortBy(flags:)` was `type:` - it always held PHP sort flags, and now
  matches `sort()` and PHP's own sort functions

### Deprecated

These still work with no runtime notice, they're just no longer featured in
the docs - IDEs show a strikethrough with the replacement.

- `get($key, $default)` and `set($key, $value)` - use property access
  instead: `$row->name`, `$row->{'users.id'}` for keys property syntax can't
  type, `$row->name ?? 'n/a'` for missing-key defaults, and
  `$row->name = $value` to write. `get('')` and `set('', $value)` remain the
  only way to reach an empty-string key - the brace form is a fatal error.
- `each($callback)` - use a `foreach` loop instead, same behavior in plain PHP
- `sprintf($format)` - use `map()` with an inline format string instead:
  `$list->map(fn($v) => "<li>$v</li>")`. On SmartArrayHtml, convert to raw
  mode and encode explicitly so the finished HTML isn't re-encoded on output:
  `$row->asRaw()->map(fn($v) => "<td>" . htmlspecialchars((string)$v) . "</td>")->implode("\n")`
- `help()` - read the docs on GitHub instead; the guide pages and method
  reference replaced the built-in cheat sheet (`src/help.txt` is deleted).
  Until removed, `help()` prints links to both, and runtime messages point at
  "the SmartArray docs" rather than suggesting it.

### Removed

- `usingSmartStrings()` - use `instanceof SmartArrayHtml` to check the mode;
  the class is the mode. Never documented, no found uses.
- `setLoadHandler()` - pass the handler as the `loadHandler` constructor
  property, which is how the database layer (ZenDB) has always set it. It
  couldn't work on record sets anyway: rows snapshot the handler during
  construction, so one set afterward never reached them.

### Behavior changes

- `where()`, `whereNot()`, `whereInList()`, and `contains()` match values
  more precisely. Most code sees no difference: numbers still match numeric
  strings, so `where('id', 5)` matches `'5'` and `where('price', 1)` matches
  `'1.00'`. Three edge cases changed:
    - Two strings must match exactly: `'01'` no longer matches `'1'`, and
      `where('code', '0e123')` no longer matches `'0e999'` (PHP's loose `==`
      read both as numbers - a wrong-row risk for hash and token lookups)
    - null only matches null, like SQL IS NULL (it used to match `''`, 0,
      and false too)
    - true/false mean 1/0 (true used to match any truthy value, even `'abc'`)

  See [UPGRADING.md](UPGRADING.md).
- Row-only methods (`where()`, `whereNot()`, `whereInList()`, `sortBy()`,
  `indexBy()`, `groupBy()`, `column()`, `columnAt()`) throw
  `InvalidArgumentException` naming the offending element when the array
  mixes rows and scalar values, instead of silently skipping the scalars
  (`sortBy()` kept them). A scalar next to rows means the array was built
  wrong - usually a wrapped API response (`['count' => 5, 'items' => [...]]`)
  or a value assigned onto a result set - and skipping it hid the mistake.
  Database results and empty arrays are unaffected. See
  [UPGRADING.md](UPGRADING.md).
- A missing field stays a SmartNull through the whole chain instead of
  becoming an empty SmartString at the first method call. Same output as
  before (echoes `""`, `or()` still fires), but chains no longer dead-end:
  `$row->missing->trim()->implode(', ')` works where it previously threw.
  `map()` skips its callback on a missing key; NULL values in existing
  keys still run it.
- `isset()`, `empty()`, and `??` treat a stored null as missing, matching
  plain PHP arrays: on a NULL column `isset($row->field)` is now false and
  `$row->field ?? 'none'` returns `'none'`. Previously they answered "does
  the column exist", so in HTML mode `??` fallbacks never fired on NULL
  columns. Bracket syntax matches, and direct access is unchanged. See
  [UPGRADING.md](UPGRADING.md).
- Missing-key warnings fire only on rows inside a result set, where keys are
  column names and a miss is almost always a typo. Top-level and derived
  collections (`indexBy()`/`column()` maps, standalone arrays) render blank
  silently, so fallbacks chain cleanly:
  `$authorById->{$id}->or('Unknown Author')`. Method-argument checks
  (`where('typo')`) still warn everywhere.
- `SmartArray::new($data, true)` and `SmartArrayHtml::new($data, false)` throw
  like the constructors do, instead of silently ignoring the boolean - code
  passing `true` expecting auto-encoding was getting raw, unencoded values.
  Redundant booleans (`false` on SmartArray, `true` on SmartArrayHtml) log a
  deprecation and proceed. `SmartArrayRaw::new()` matches.
  See [UPGRADING.md](UPGRADING.md).
- All writes to a `SmartNull` throw "Cannot set values on SmartNull": property
  writes (previously created a silent dynamic property that shadowed
  chaining) and two-argument `->set($key, $value)` (previously discarded the
  value) now match the existing `['key'] =` guard. One-argument
  `->set($value)` is SmartString's set: not a write, it produces that value
  and ends the chain, like `or()`.
- Raw-mode arrays no longer answer SmartString methods on missing keys:
  `$row->missing->or('n/a')` on a raw array throws the standard
  undefined-method Error instead of returning an HTML-encoding SmartString.
  Raw fallbacks use `??`. HTML mode is unchanged.
- `set()`, `->key = $value`, and array assignment unwrap Smart values
  (SmartString, SmartArray, SmartNull) instead of throwing, so values copy
  between arrays in any mode without calling `->value()` first. SmartNull
  stores as null; nested SmartArrays convert to the target array's mode.
- `get()` and `at()` unwrap Smart arguments, so keys read from another array
  work directly: `$users->get($article->author_id)`. Unwrapped keys follow
  PHP array-key rules (null reads key `''`, bool/float truncate to int).
- `print_r()` and `var_dump()` show just the array data, like dumping a plain
  array - the injected pseudo-properties (the README help pointer and the
  `useSmartStrings` flag) are gone. Use `->debug()` for exact types and
  metadata. `SmartNull` dumps as `[value] =>`. Matches SmartString 3.0.0.
- Deprecated method names are real declared methods marked `@deprecated`
  instead of `__call()` shims, so IDEs show strikethroughs with the
  replacement and `method_exists()` reports them. Same behavior and
  deprecation notices as before.
- `orDie()` and `or404()` exit with status 1 instead of 0, so shell scripts
  and cron jobs see the failure. Output is unchanged. Matches SmartString.

### Fixed

- `column(null)` and `column(null, null)` match PHP's `array_column()`: whole
  rows renumbered from 0, instead of throwing "unexpected arguments"
- `sortBy()` no longer throws a bare `ValueError: Array sizes are
  inconsistent` when a row is missing the sort field. Missing fields sort
  first (treated as null for ordering, like MySQL ORDER BY); rows are
  returned unchanged.
- `indexBy()` no longer gives rows missing the index field a leftover numeric
  key that looks like a real field value. Null and missing values both index
  under `''`, duplicates last-wins.
- `indexBy()`, `groupBy()`, and `column($col, $indexKey)` throw
  `InvalidArgumentException` ("'price' has float values, convert them to
  strings first") when the key field holds floats, instead of PHP's
  float-to-int key truncation, which keyed `19.99` and `19.50` both as `19`
  (last row wins, one lost) and printed a PHP deprecation naming library
  internals. No float-to-key conversion is safe, so the caller picks the
  string format. Integer and boolean keys are unchanged (ints key as ints,
  bools as 1/0).
- `column($col, $indexKey)` keys rows missing the index field under `''`,
  same as `column(null, $indexKey)` and `indexBy()`, instead of
  `array_column()`'s auto-numbered keys that look like real field values.
- `asRaw()` and `asHtml()` on a row keep its position metadata, so
  `position()`, `isFirst()`, and `isLast()` answer the same before and after
  converting (conversion used to reset them to position 0 with both flags
  false).
- Method calls on a `SmartNull` keep the source's mysqli metadata, root
  pointer, and load handler, matching its `asRaw()`/`asHtml()`. Previously
  chains through a missing key answered `mysqli()` with `[]` and `root()`
  with a throwaway empty array.
- `$arr[null]` (deprecated `[]` syntax) reads key `''` like a plain PHP array,
  instead of printing a misleading `Replace []` suggestion and then throwing a
  TypeError that named library internals. `unset()` and `isset()` already
  worked this way.
- Array-syntax deprecation notices suggest one replacement style across reads,
  writes, and `unset()`: `->key` and `->key = $value` for property-safe
  names, `->{0}` for integer keys, `->{'users.id'}` for other keys.
  Null and `''` keys suggest `->get('')` / `->set('', $value)` - the brace
  form is a fatal error for an empty property name.
- Unknown methods on `SmartNull` throw the same `Error` as the rest of the
  library - method name, "did you mean" hint, caller's file and line -
  instead of `InvalidArgumentException("Method 'x' not found")`.
- Clearer messages for two exceptions: `load()` with no handler explains
  handlers come from the database layer (was "No loadHandler property is
  defined"), and writing to a `SmartNull` says the value came from a missing
  key or empty result. The unsupported-type message from `set()` no longer
  prefixes the library's internal method name.
- The string-conversion warning names the actual class (`Can't convert
  SmartArrayHtml to string` on HTML arrays) and the missing-brace hint ends
  with a newline so it no longer runs into the "Occurred in" trace.
- `load()` accepts a column literally named `"0"` (the blank-name check used
  `empty()`), and a handler returning the wrong shape throws `Load handler
  must return [rows, mysqliProperties] or false` before destructuring, so no
  stray "Undefined array key 1" PHP warning leaks from the library.
- `load()` with an invalid-character field name throws
  `InvalidArgumentException` (was `RuntimeException`), matching the
  empty-field-name check - both are argument problems. The other `load()`
  setup errors still throw `RuntimeException`.
- `debug(1)` no longer throws `Unsupported type: Closure` on arrays with a
  load handler - exactly the database results it exists to inspect. Callables
  and other objects in the properties block print as their type (`Closure,`)
  instead.
- `get($key, $default)` defaults act like stored values: Smart defaults
  (SmartString, SmartArray, SmartNull) unwrap and re-wrap for the array's
  mode. Previously a SmartNull default threw `InvalidArgumentException`, and
  cross-mode Smart defaults threw `TypeError` from the return declarations.
- `SmartNull->help()` wraps its output in `<xmp>` when no Content-Type header
  is set, same rule as every other `help()` and `debug()` - PHP's default
  response type is text/html. Previously it only wrapped when text/html was
  set explicitly via `header()`, so on typical pages it printed as collapsed,
  unformatted text.
- `help()` and `debug()` print plain text on the command line instead of
  wrapping output in literal `<xmp>` tags. Terminal detection checks
  `PHP_SAPI` plus two fallbacks (Windows console `SESSIONNAME`, missing
  `SCRIPT_NAME`) because some hosts' CGI builds misreport SAPI. Web responses
  are unchanged. Matches SmartString.
- `or404()` outputs `<html>` instead of `<html lang>` - an empty `lang` reads
  as an invalid value to accessibility checkers, and the message language is
  caller-supplied so it can't be declared. Matches SmartString.

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
