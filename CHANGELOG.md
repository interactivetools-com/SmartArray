# SmartArray Changelog

> **Upgrading?** Read each version section between your version and the
> target - tagged releases roll up every change since the previous tag.
> Versions bundled with CMS Builder are marked on their sections.

## [3.0.0] - [UNRELEASED]

### Security
- Missing-key warnings and array-syntax deprecation notices HTML-encode the key before echoing it. With a dynamic key (`->get($_GET['sort'])`, `$arr[$_GET['sort']]`), a key containing HTML reached the browser unencoded - a reflected XSS vector. The `trigger_error()` copy carries the same encoded key.
- `json_encode($smartArray)` also substitutes malformed UTF-8 in keys with � (U+FFFD), not just values - one corrupt byte in a key no longer makes it return false and lose the whole document.

### Added
- `at($index)` - new name for `nth()`: get an element by position, zero-based, negative indices count from the end. Matches JavaScript's `Array.at()`. `$row->key` is by key, `at()` is by position.
- `columnAt($index)` - new name for `pluckNth()`: get the column at a position from each row, ignoring key names. `column()` is by key, `columnAt()` is by position.

### Changed
- `get()` and `at()` now unwrap Smart arguments, so keys read from another array work directly: `$users->get($article->author_id)` no longer needs `->value()` first. Matches how `set()` and `get()` defaults already unwrap. Unwrapped keys follow PHP array-key rules (null reads key `''`, bool/float truncate to int).
- `$arr[null]` (deprecated `[]` syntax) now reads key `''` like a plain PHP array, instead of printing a misleading `Replace []` suggestion and then throwing a TypeError that named library internals. `unset()` and `isset()` already worked this way.
- All writes to a `SmartNull` now throw the same "Cannot set values on SmartNull" `RuntimeException`: property writes (previously created a silent dynamic property that shadowed chaining) and `->set()` (previously delegated to a throwaway empty array, silently discarding the value) now match the existing `['key'] =` guard.
- `SmartArrayRaw::new($data, true)` now throws like `SmartArray::new($data, true)` instead of silently dropping the boolean and returning raw, unencoded values.
- The string-conversion warning names the actual class (`Can't convert SmartArrayHtml to string` on HTML arrays) and the missing-brace hint ends with a newline so it no longer runs into the "Occurred in" trace.
- `load()` accepts a column literally named `"0"` (the blank-name check used `empty()`), and a handler returning the wrong shape throws `Load handler must return [rows, mysqliProperties] or false` before destructuring, so no stray "Undefined array key 1" PHP warning leaks from the library.
- `asRaw()` and `asHtml()` on a row keep its position metadata, so `position()`, `isFirst()`, and `isLast()` answer the same before and after converting. Previously conversion reset them (position 0, both flags false) - the same row, asked the same question, silently changed its answer.
- Method calls on a `SmartNull` keep the source's mysqli metadata, root pointer, and load handler, matching its `asRaw()`/`asHtml()`. Previously chains through a missing key answered `mysqli()` with `[]` and `root()` with a throwaway empty array.
- `print_r()` and `var_dump()` now show just the array data, like dumping a plain array. The injected pseudo-properties (the README help pointer and the `useSmartStrings` flag) are gone - the class name PHP prints on every dumped object already identifies the mode, and `var_dump()` rendered the fake entries as broken-looking keys. Use `->debug()` for exact types and metadata. `SmartNull` dumps as `[value] =>` instead of exposing its internal properties. Matches the same change in SmartString 3.0.0.
- Deprecated method names (`toRaw()`, `toHtml()`, `withSmartStrings()`, `enableSmartStrings()`, `noSmartStrings()`, `disableSmartStrings()`, `isMultipleOf()`, plus `smartMap()` and `chunk()`) are now real declared methods marked `@deprecated`, organized by deprecation stage in one `Deprecations` trait, instead of `__call()` shims. IDEs now show strikethroughs with the replacement, `method_exists()` reports them, and calls skip `__call()` dispatch. Same behavior and deprecation notices as before.
- `set()`, `->key = $value`, and array assignment now unwrap Smart values (SmartString, SmartArray, SmartNull) instead of throwing, so values can be copied between arrays in any mode without calling `->value()` first. SmartNull stores as null; nested SmartArrays convert to the target array's mode. Matches how `where()`, `contains()`, and `merge()` already treat Smart inputs.
- `SmartArray::new($data, true)` and `SmartArrayHtml::new($data, false)` now throw like the constructors do, instead of silently ignoring the boolean. Old code that passed `true` expecting auto-encoding was silently getting raw, unencoded values - now it fails at the call site with the class to use instead. Redundant booleans (`false` on SmartArray, `true` on SmartArrayHtml) log a deprecation and proceed.
- `sortBy()` second parameter renamed `$type` → `$flags` - it always held PHP sort flags, and now matches `sort()` and PHP's own sort functions. Affects named-argument calls only: `sortBy('name', flags: SORT_NATURAL)`.
- `column(null)` and `column(null, null)` now match PHP's `array_column()`: whole rows renumbered from 0, instead of throwing "unexpected arguments"
- Array-syntax deprecation notices suggest one replacement style across reads, writes, `isset()`, and `unset()`: `->key` and `->key = $value` for property-safe names, `->{0}` for integer keys, `->{'users.id'}` for other keys. Reads used to suggest `->get(0)` while existence checks suggested `->{0}`, so one `empty()` call printed two notices with different advice, and writes suggested the now-deprecated `->set()`. Null and `''` keys are the exception and suggest `->get('')` / `->set('', $value)` - the brace form is a fatal error for an empty property name.
- `isset($array['key'])` and `empty($array['key'])` now follow `$onOffsetAccess` like reads, writes, and `unset()` - notice by default, exception in `'throw'` mode. Existence checks were the one silent form of the deprecated `[]` syntax; if `[]` support is removed in a future version, `isset()` on the object would silently return false instead of erroring, so these call sites need migrating with the rest. Property-syntax checks (`isset($array->key)`) are unaffected and stay signal-free. Internal existence checks now call `array_key_exists()` directly, removing two method calls from every `get()`.
- `isset()`, `empty()`, and `??` treat a stored null as missing, matching plain PHP arrays: on a NULL column, `isset($row->field)` is now false and `$row->field ?? 'none'` returns `'none'`. Previously they answered "does the column exist", so in HTML mode `??` fallbacks never fired on NULL columns (the wrapped null echoed as `""`). Bracket syntax (`isset($row['field'])`) matches. Direct access is unchanged: `$row->field` still returns the stored null, wrapped in HTML mode, with no warning. Ask `$row->keys()->contains('field')` when you need "does the key exist, even if NULL". Note `??` substitutes its fallback before the library runs, so the fallback skips HTML encoding - use `->or()` for display fallbacks that carry user data. See UPGRADING.md.
- Array construction is ~2.9x faster (local benchmark, 25-row record set: 19.0 → 6.6 microseconds). Internal properties assign from a fixed key list instead of `property_exists()` checks per key; all-scalar rows (typical database rows) clone a shared template child and assign their data in one copy-on-write step instead of running the constructor and storing field by field; scalars store directly in the constructor loop instead of dispatching through `setElement()`. The fixed key list also means constructor `$properties` can no longer name arbitrary internal properties like `$data` - unknown keys are ignored, same as before. One visible side effect: deprecated `SmartArrayRaw` logs its constructor deprecation twice per result set (outer array + row template) instead of once per row.
- `toArray()` is ~3x faster: arrays with no child rows hand back internal data as one copy-on-write assignment instead of rebuilding element by element, and record sets get the same win per row inside the recursion (flat 4-field row: 81 → 26 ns; 25-row set: 2.0 → 0.6 microseconds, local benchmark). Callers can't reach internal storage - PHP copies on first write.
- `foreach` is 1.2-1.3x faster when nothing needs wrapping: `getIterator()` returns a C-level `ArrayIterator` in raw mode and for record sets where every value is a row, instead of stepping through a generator per element. HTML-mode arrays with scalar values keep the wrapping generator, so foreach yields SmartStrings exactly as before.
- `help()` and `debug()` print plain text on the command line instead of wrapping output in literal `<xmp>` tags. Terminal detection checks `PHP_SAPI` plus two fallbacks (Windows console `SESSIONNAME`, missing `SCRIPT_NAME`) because some hosts' CGI builds misreport SAPI. Web responses are unchanged. Matches SmartString.
- `or404()` outputs `<html>` instead of `<html lang>` - an empty `lang` reads as an invalid value to accessibility checkers, and the message language is caller-supplied so it can't be declared. Matches SmartString.
- `orDie()` and `or404()` now exit with status 1 instead of 0, so shell scripts and cron jobs see the failure. Output is unchanged. Matches SmartString.
- `load()` with an invalid-character field name throws `InvalidArgumentException` (was `RuntimeException`), matching the empty-field-name check - both are argument problems. The other `load()` setup errors (no handler, non-callable handler, called on a record set) still throw `RuntimeException`.
- Clearer messages for two exceptions: `load()` with no handler now explains handlers come from the database layer (was "No loadHandler property is defined"), and writing to a `SmartNull` now says the value came from a missing key or empty result (was "Cannot set values on SmartNull"). The unsupported-type message from `set()` no longer prefixes the library's internal method name.
- Raw-mode arrays no longer answer SmartString methods on missing keys: `$row->missing->or('n/a')` on a raw array throws the standard undefined-method Error instead of returning an HTML-encoding SmartString. Raw stored values never had these methods (chaining `->or()` on a stored string was already a fatal), so a miss was the one path that silently produced encoded output in a raw array. HTML mode is unchanged - SmartNull still delegates SmartString methods so chains through a missing key keep working. Raw fallbacks use `??`.
- Unknown methods on `SmartNull` now throw the same `Error` as the rest of the library - method name, "did you mean" hint, caller's file and line - instead of `InvalidArgumentException("Method 'x' not found")`. Chains from a missing key now fail with the same message quality as everything else.

### Deprecated
- `nth($index)` - renamed to `at()`, same behavior. Still works with no runtime notice - IDEs show a strikethrough with the replacement.
- `pluckNth($index)` - renamed to `columnAt()`, same behavior. Still works with no runtime notice - IDEs show a strikethrough with the replacement.
- `pluck($valueField, $keyField)` - use `column()` instead, same arguments and behavior: `->column('name')`, `->column('name', 'id')`. One name per behavior, and `column()` matches PHP's `array_column()`. Still works with no runtime notice - IDEs show a strikethrough with the replacement; removed from README and help().
- `each($callback)` - use a `foreach` loop instead, same behavior in plain PHP. Still works with no runtime notice - IDEs show a strikethrough with the replacement; removed from help(). It had no measured uses and a foreach is clearer and faster.
- `sprintf($format)` - use `map()` with an inline format string instead: `$list->map(fn($v) => "<li>$v</li>")`. On SmartArrayHtml, encode explicitly and convert to raw mode first so the finished HTML isn't re-encoded on output: `$row->asRaw()->map(fn($v) => "<td>" . htmlspecialchars((string)$v) . "</td>")->implode("\n")`. The method still works unchanged with no runtime notice - IDEs show a strikethrough with the replacement; removed from README and help(). It was a second formatting syntax that only saw use inside CMS Builder.
- `get($key, $default)` - use property access instead: `$row->name`, `$row->{'users.id'}` for keys property syntax can't type, and `$row->name ?? 'n/a'` for missing-key defaults. Still works unchanged with no runtime notice, including the default parameter - IDEs show a strikethrough with the replacement; removed from README and help(). It was a second documented way to read every element; the docs now teach one form, and property access is 1.1-1.6x faster. `get('')` remains the only way to read an empty-string key - the brace form is a fatal error.
- `set($key, $value)` - use property assignment instead: `$row->name = $value`, or `$row->{'users.id'} = $value` for keys property syntax can't type. Still works unchanged with no runtime notice - IDEs show a strikethrough with the replacement; removed from README and help(). Deprecated together with `get()` - one form for reads, one for writes. `set('', $value)` remains the only way to write an empty-string key.

### Removed
- `usingSmartStrings()` - use `instanceof SmartArrayHtml` to check the mode; the class is the mode. The method was never documented and had no found uses.
- `setLoadHandler()` - the handler is passed as the `loadHandler` constructor property, which is how the database layer (ZenDB) has always set it. The setter had no known callers, and it couldn't work on record sets anyway: rows are built during construction and snapshot the handler at that moment, so a handler set afterward never reached them.

### Fixed
- `SmartNull->help()` wraps its output in `<xmp>` when no Content-Type header is set, same rule as every other `help()` and `debug()` - PHP's default response type is text/html. Previously it only wrapped when text/html was explicitly set via `header()`, so on typical pages (which never call `header()`) it printed as collapsed, unformatted text.
- `debug(1)` no longer throws `Unsupported type: Closure` on arrays with a load handler - exactly the database results it exists to inspect. Callables and other objects in the properties block print as their type (`Closure,`) instead.
- `get($key, $default)` defaults now act like stored values: Smart defaults (SmartString, SmartArray, SmartNull) unwrap and re-wrap for the array's mode. Previously a SmartNull default threw `InvalidArgumentException`, and cross-mode Smart defaults (a SmartString default on SmartArray, a raw SmartArray default on SmartArrayHtml) threw `TypeError` from the return declarations.
- `sortBy()` no longer throws a bare `ValueError: Array sizes are inconsistent` when a row is missing the sort field. Missing fields sort first (treated as null for ordering, like MySQL ORDER BY); rows are returned unchanged.
- `indexBy()` no longer gives rows missing the index field a leftover numeric key that looks like a real field value. Null and missing values now both index under `''` (matching how null field values were already handled), duplicates last-wins.

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
