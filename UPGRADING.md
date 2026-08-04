# Upgrading SmartArray

Most old code keeps working after an upgrade:

- **Renamed methods are removed slowly, never silently.** Old names keep
  working while they step through deprecation stages over multiple releases
  (IDE strikethrough, logged notice, visible notice, then a clear Error),
  always naming their replacement.
- **Breaking changes produce clear errors.** Removed features and wrong named
  arguments throw an Error with your file and line, and deprecated calls raise
  a deprecation notice with their exact replacement, e.g. "Replace
  ->toRaw() with ->asRaw() in listings.php:14" - error handlers like
  CMS Builder's developer log catch these.
- **Only the silent changes need checking.** This file lists them per
  version, each with a search.

Full lists of what changed per release: [CHANGELOG.md](CHANGELOG.md).

---

## v3.0.0

*Follow this section when upgrading from SmartArray before v3.0.0
(or CMS Builder before 3.85).*

### Boolean argument to `new()` or the constructor

> Creating an array with a boolean that contradicts the class used to be
> silently ignored - `SmartArray::new($data, true)` looked like it asked for
> HTML-safe output but returned raw, unencoded values. It now throws at the
> call site:
>
> ```php
> $rows = SmartArray::new($records, true);   // throws: use SmartArrayHtml::new($data) instead
> $rows = SmartArrayHtml::new($records);     // correct - values HTML-encode on output
> ```
>
> Redundant booleans (`false` on SmartArray, `true` on SmartArrayHtml) raise a
> deprecation notice and keep working.
>
> Fix:
>
> - Search for creation calls passing a boolean and use the class that matches
>   the intent instead
>
> Regex: `(new SmartArray\w*|SmartArray\w*::new)\([^)]*(true|false)\)` - also search `useSmartStrings`

### Removed methods

> Undocumented methods with no found uses were removed:
>
> - `usingSmartStrings()` - check the class instead; it is the mode:
>   `$arr instanceof SmartArrayHtml`
> - `setLoadHandler()` - pass the handler as a constructor property:
>   `new SmartArray($data, ['loadHandler' => $handler])`. Setting it after
>   construction never worked on record sets - rows are built during
>   construction and never saw a handler set later.
>
> Search: `usingSmartStrings|setLoadHandler`

### sortBy() parameter renamed for named arguments

> `sortBy(string $field, int $flags = SORT_REGULAR)` - the second parameter
> was named `$type` but always held PHP sort flags. It now matches `sort()`
> and PHP's own sort functions. Only named-argument calls are affected:
>
> ```php
> $rows->sortBy('name', type: SORT_NATURAL);   // before
> $rows->sortBy('name', flags: SORT_NATURAL);  // after
> ```
>
> Regex: `->sortBy\([^)]*type:`

### `isset()`, `empty()`, and `??` treat stored NULL as missing

> These now match plain PHP arrays: a column whose value is NULL reads as
> missing, so `isset()` answers false, `empty()` answers true, and `??`
> returns its fallback. Previously they answered "does the column exist",
> which meant `??` fallbacks never fired on NULL columns in HTML mode - the
> wrapped null echoed as `""`:
>
> ```php
> $row = SmartArrayHtml::new(['nickname' => null]);
>
> echo $row->nickname ?? 'none';  // before: "" - after: none
> isset($row->nickname);          // before: true - after: false
> empty($row->nickname);          // before: false - after: true
> ```
>
> Bracket syntax (`isset($row['field'])`) changes the same way. Direct
> access is unchanged: `$row->nickname` still returns the stored null
> (wrapped in HTML mode) with no warning.
>
> Fix:
>
> - Check templates using `??` on nullable columns - they print the fallback
>   where they used to print nothing. That's usually the intent; if the
>   fallback carries user data, use `->or()` instead, which HTML-encodes.
>   A `??` fallback skips encoding because PHP substitutes it before the
>   library runs.
> - When migrating deprecated `get($key, $default)` calls to
>   `$row->key ?? $default`: `get()` returns a stored NULL instead of the
>   default, the `??` form returns the default. Same results everywhere
>   except NULL columns.
> - To ask "does the key exist, even if NULL", use
>   `$row->keys()->contains('field')`.
>
> Regex: `->\w+ \?\?` - also search `isset(` and `empty(` on row fields

### Silent changes

> - `print_r()` and `var_dump()` show just the array data, like dumping a
> plain array - the injected pseudo-entries (the README help pointer and the
> `useSmartStrings` flag) are gone. Use `->debug()` for exact types and
> metadata.
> - `indexBy()` keys rows missing the index field under `""` (same as rows
> where the field is null) instead of leaving a numeric key that looked like
> a real field value. Duplicates still last-wins.
> - `sortBy()` sorts rows missing the sort field first, like MySQL ORDER BY
> sorts nulls, instead of throwing `ValueError: Array sizes are inconsistent`.
> - `set()`, `->key = $value`, and `get()` defaults now accept Smart values
> (SmartString, SmartArray, SmartNull) - they unwrap and re-wrap for the
> array's mode instead of throwing, so values copy between arrays without
> calling `->value()` first. Only affects code that relied on those throws.

## v2.7.0

*Follow this section when upgrading from SmartArray before v2.7.0
(or CMS Builder before 3.85).*

### Parameter renames (named arguments only)

> PHP lets you write a parameter's name right in the call - the `text:`
> part in `->orDie(text: 'Not found')`. If you never do this, skip this
> check. If you do, one parameter name changed, and calls using the old
> name fail with a clear "Unknown named parameter" Error:
>
> ```php
> ->orDie('Not found')              // no parameter name - nothing changes
> ->orDie(message: 'Not found')     // before (same for or404, orThrow)
> ->orDie(text: 'Not found')        // after
> ```
>
> Fix:
>
> - Search `message:` and replace with `text:` on or404/orDie/orThrow calls
>
> Regex: `->(orDie|or404|orThrow)\(\s*message:`

### Silent changes

> - `json_encode($smartArray)` substitutes malformed UTF-8 bytes with �
> (U+FFFD) instead of returning false - one corrupt byte no longer breaks
> the whole page, and code checking for a false return no longer sees one

## v2.6.7

*Follow this section when upgrading from SmartArray before v2.6.7
(or CMS Builder before 3.83).*

### `SmartArray` type hints receiving HTML-mode arrays

> SmartArrayHtml used to extend SmartArray, so it passed `SmartArray` type
> hints. Both classes are now siblings under `SmartArrayBase`, so passing an
> HTML-mode array to a `SmartArray` hint is a fatal TypeError naming your
> function:
>
> ```php
> function formatRows(SmartArray $rows): SmartArray { ... }          // TypeError when passed SmartArrayHtml
> function formatRows(SmartArrayBase $rows): SmartArrayBase { ... }  // works for both modes
> ```
>
> Fix:
>
> - Search `SmartArray` in parameter and return types; use `SmartArrayBase`
>   wherever either mode can arrive

### `$array['key']` access prints a deprecation notice

> Bracket access still works but is deprecated, and by default it now echoes
> a visible "Deprecated:" notice into the page in addition to
> `trigger_error()`. Each notice names your file and line:
>
> ```php
> echo $row['name'];         // works, but prints a Deprecated: notice into the page
> echo $row->name;           // correct
> echo $row->{'users.id'};   // correct - for keys property syntax can't type
> ```
>
> Fix:
>
> - Follow the file and line in each notice and switch to `->key` or
>   `->{'key'}`
> - Sites mid-migration can silence the echo (notices still reach error
>   logs): `SmartArrayBase::$onOffsetAccess = 'log';`

### Removed settings (added v2.2.2, removed v2.6.7)

> All three settings are gone, and leftovers fail loudly ("Access to
> undeclared static property") - if your pages load, you're clean.
>
> Fix:
>
> - Search `$warnIfMissing`, `$warnIfDeprecated`, and `$logDeprecations` -
>   remove them; deprecation notices always trigger now, and error handlers
>   decide what to show

### Optional renames

No required changes: the old names still work and raise a deprecation notice
naming their replacement (visible in error handlers like CMS Builder's
developer log). Renaming is optional cleanup.

| Old name (still works) | Current name                          |
|------------------------|---------------------------------------|
| `->toRaw()`            | `->asRaw()`                           |
| `->toHtml()`           | `->asHtml()`                          |
| `->smartMap()`         | `->map()`                             |
| `SmartArrayRaw` class  | `SmartArray`                          |
| `->chunk()`            | deprecated, no replacement planned    |
| `->isMultipleOf($n)`   | `->position() % $n === 0`             |

## v2.4.0

*Follow this section when upgrading from SmartArray before v2.4.0
(or CMS Builder before 3.80). Requires PHP 8.1+.*

### Silent changes

> - `where()` matches loosely (==, so `"5"` matches 5) instead of strictly -
> rows that fell out of results because a database value was a string and
> the condition was a number (or vice versa) now match. Review `where()`
> calls only if you relied on that type mismatch to exclude rows.
> - `enableSmartStrings()` and `disableSmartStrings()` are deprecated - use
> `->asHtml()` and `->asRaw()` (the old names still work and raise a notice)

## v2.0.1

*Follow this section when upgrading from SmartArray before v2.0.1
(or CMS Builder before 3.75).*

### Values are raw by default (no automatic HTML-encoding)

> Before v2.0, every value came back as a SmartString and HTML-encoded itself
> on output. Creation now returns raw PHP values, so a template echoing them
> outputs unencoded data:
>
> ```php
> $user = SmartArray::new(['name' => "Jean O'Brien <script>"]);
> echo $user->name;    // unencoded - fine for data processing, not for HTML output
>
> $user = SmartArrayHtml::new(['name' => "Jean O'Brien <script>"]);
> echo $user->name;    // HTML-encoded (v2.0 spelled this SmartArray::newSS())
> ```
>
> Fix:
>
> - Search `new SmartArray(` and `SmartArray::new(` - anywhere the values
>   are echoed into HTML, create with `SmartArrayHtml::new()` instead
> - ZenDB query results are unaffected: they already come back HTML-safe
>
> Also in this release: `join()` renamed to `implode()` (old name still works).

---

*End of upgrade notes. There is nothing older to check: SmartArray was first
bundled with CMS Builder v3.74 (as v1.2.0), and v1.x needs only the sections
above.*
