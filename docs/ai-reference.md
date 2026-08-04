<!-- DIFFERENT FROM THE OTHER DOC PAGES: this file contains no zero-width spaces.
     The human doc pages insert a U+200B after "&" in example output like &apos; so
     PHPStorm's Markdown preview shows the entity instead of decoding it. This file
     is read by AI assistants as raw bytes, so it stays byte-exact: everything here
     is safe to copy into code and test assertions. Never add U+200B to this file. -->

# SmartArray AI Reference

This is a consolidated reference for AI coding assistants. It contains
everything needed to write correct SmartArray code in a single file, and
covers SmartArray 3.0. For human-friendly docs with tutorials and
explanations, see [Getting Started](getting-started.md).

Contents:

- What is SmartArray
- Class Hierarchy and Type Hints
- Creating Collections
- Reading and Writing Fields
- Missing Keys and SmartNull
- Iteration and Keys
- Mode Conversion and Plain Arrays
- Single Elements - first(), last(), at()
- Collection Checks - count(), isEmpty(), isNotEmpty(), contains()
- Row Position - isFirst(), isLast(), position()
- Filtering and Sorting - where(), whereNot(), whereInList(), filter(), sort(), sortBy(), unique()
- Transforming and Grouping - column(), columnAt(), indexBy(), groupBy(), keys(), values(), map(), merge(), implode()
- Guards - or404(), orDie(), orThrow(), orRedirect()
- Database Metadata - mysqli(), load()
- Debugging - debug(), help()
- Errors and Exceptions
- Deprecated Names
- Gotchas Quick Reference

---

## What is SmartArray

SmartArray wraps PHP arrays (usually database rows) in chainable collection
methods. Two concrete classes share one API; the ONLY difference is what a
field read returns:

- **`SmartArrayHtml`** (HTML mode): fields return `SmartString` objects that
  HTML-encode in every string context. Use whenever output is a web page.
  ZenDB and CMS Builder query results arrive in this mode.
- **`SmartArray`** (raw mode): fields return plain PHP values in their
  original types. Use for JSON, CSV, email, CLI, and data processing.

```php
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;

$users = SmartArrayHtml::new([
    ['name' => "Jean O'Brien", 'city' => 'Vancouver'],
    ['name' => 'Tom & Jerry Inc', 'city' => 'Ottawa'],
]);

foreach ($users as $user) {
    echo "<li>$user->name from $user->city</li>\n";  // <li>Jean O&apos;Brien from Vancouver</li>
}
echo $users->where('city', 'Ottawa')->first()->name;  // Tom &amp; Jerry Inc
```

Key definitions used throughout:

- **row** = a nested SmartArray inside a parent collection (created
  automatically for nested input arrays). Rows know their `position()`
  (1-based); top-level and derived collections report position 0.
- **field** = a scalar element read off a collection: `SmartString` in HTML
  mode, plain PHP value in raw mode. Data is stored raw and wrapped on
  access, never modified.
- **SmartNull** = chainable placeholder returned for missing keys and empty
  lookups. Echoes as `""`, counts as 0, iterates as nothing; SmartArray and
  SmartString methods on it keep working.
- Collection methods behave identically in both modes: callbacks, matching,
  and sorting always operate on original raw values. Methods returning a
  collection return it in the calling object's mode.
- Transformation methods return NEW collections; the original is never
  modified.

## Class Hierarchy and Type Hints

```
SmartBase (interface)     anything the library hands back
├── SmartArrayBase        abstract base - type-hint this to accept either mode
│   ├── SmartArray            raw mode
│   └── SmartArrayHtml        HTML mode
└── SmartNull             returned for missing keys and empty lookups
```

`SmartArrayHtml` is NOT instanceof `SmartArray`; they are siblings. Hint
`SmartArrayBase` for functions accepting either mode, `SmartBase` to also
accept `SmartNull`.

## Creating Collections

```php
SmartArrayHtml::new(array $array = [], array $properties = []): SmartArrayHtml
SmartArray::new(array $array = [], array $properties = []): SmartArray
```

- Nested arrays become child rows (recursively); scalars and null store
  as-is; Smart values unwrap; other objects/resources throw
  `InvalidArgumentException`.
- `$properties` is for database layers: `['mysqli' => [...metadata...],
  'loadHandler' => callable]`. Normal code omits it.
- `new SmartArray($data)` works too; `::new()` exists because
  `new SmartArray($data)->method()` is a syntax error before PHP 8.4.

## Reading and Writing Fields

```php
$row->name                    // property syntax is canonical
$row->{'users.id'}            // braces for keys property syntax can't type (dots, dashes, numeric)
$row->{0}                     // numeric keys
$row->name = 'Jean';          // writes use the same syntax
$row->{'sort-order'} = 5;
```

- HTML mode wraps scalar reads in `SmartString`; nested rows come back as
  collections (never wrapped). Raw mode returns everything as-is.
- Writes unwrap Smart values (SmartString stores its raw value, SmartNull
  stores null, SmartArray children convert to the target's mode).
- SmartString fields used inside braces stringify to their HTML-ENCODED
  output. Digits are unaffected, so numeric id keys work as-is; for text
  keys pass the original: `$map->{$field->value()}`.
- `isset($row->key)` / `empty($row->key)` / `$row->key ?? $default` use
  plain-array semantics: stored NULL reads as missing (so `??` fires on
  missing keys AND stored NULLs) and none of them ever warn.
- The `??` fallback is a plain value, output with NO encoding: keep `??`
  fallbacks to literals. For display fallbacks use the field's `or()`,
  which also covers `""` and keeps the result encoded.
- Empty-string keys exist but property syntax can't reach them; only the
  deprecated `get('')`/`set('')` can (and `$arr[null]` reads key `''`).
- `unset($row->key)` removes a key.

## Missing Keys and SmartNull

Reading a key that doesn't exist returns a `SmartNull`. Warning behavior
depends on WHERE you read (changed in 3.0):

- **Rows inside a result set** (position 1+): echoes
  `Warning: keyname is undefined in file.php:LINE` and triggers
  `E_USER_WARNING` (caller's file:line, key HTML-encoded, plus a
  wrap-methods-in-braces hint when the key matches a method name). Row keys
  are column names, so a miss is treated as a typo.
- **Everywhere else** (top-level collections, `indexBy()`/`column()` lookup
  maps, standalone arrays, empty collections): silent. A miss is a normal
  no-match, so fallbacks chain cleanly: `$authorById->{$id}->or('Unknown')`.

SmartNull behavior: `echo` → `""`; `value()` → null; `count()` → 0;
`foreach` iterates zero times; `toArray()` → `[]`; `json_encode()` → null;
SmartArray methods return empty results; SmartString methods (HTML mode)
behave as on null; guards (`or404()` etc.) FIRE (empty = missing); any
write to it throws `RuntimeException` ("Cannot set values on SmartNull").
It carries the source's mysqli metadata and load handler.

## Iteration and Keys

- `foreach ($collection as $key => $value)`: values follow the mode (rows
  as collections, scalars as fields); **keys are always raw plain values,
  never encoded**, in both modes. When keys came from user data
  (`groupBy()` on a user-entered field) and get echoed, iterate
  `$collection->keys()` instead - keys() returns them as fields that encode
  on output.
- Iteration order is insertion/array order. Nested rows yield as-is.
- `foreach` over a `SmartString` field throws; over `SmartNull` yields
  nothing.

## Mode Conversion and Plain Arrays

| Method                                            | Returns                                                                                                                      |
|---------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------|
| `asHtml(): SmartArrayHtml`                        | Collection in HTML mode; same object if already HTML, else a new collection (original unchanged). Rows keep position metadata |
| `asRaw(): SmartArray`                             | Collection in raw mode; same object if already raw                                                                           |
| `toArray(): array`                                | Plain nested PHP array, original values, both modes                                                                          |
| `SmartArray::getRawValue(mixed $value)` (static)  | SmartString → value, SmartArray → array, SmartNull → null, scalar/null/array pass through (arrays unwrapped recursively); other objects throw `InvalidArgumentException` |

- `json_encode($collection)` (JsonSerializable) emits RAW original values
  in both modes (JSON is a data format; HTML encoding is output-only).
  Malformed UTF-8 in keys or values becomes � (U+FFFD) instead of
  returning false.
- `(array)$collection` exposes internal object properties (PHP has no cast
  hook) - never use it; use `toArray()`. Spread `[...$collection]` works
  for flat lists (top level only).

## Single Elements

```php
first(): row|field|SmartNull                     // first element
last(): row|field|SmartNull                      // last element
at(int|SmartString $index): row|field|SmartNull  // by position, ignoring keys: 0 first, -1 last
```

All three return `SmartNull` silently when there is no such element
(empty collection, out-of-range index).

## Collection Checks

```php
count(): int                  // also works via count($collection) (Countable)
isEmpty(): bool               // no elements
isNotEmpty(): bool            // any elements
contains(mixed $value): bool  // any element == $value (loose; Smart args unwrap)
```

Field-level checks (`isMissing()`, `isEmpty()`, `or()`, ...) are SmartString
methods, available on fields in HTML mode; see the
[SmartString AI reference](https://github.com/interactivetools-com/SmartString/blob/main/docs/ai-reference.md).

## Row Position

```php
isFirst(): bool    // true for the first row in its parent collection
isLast(): bool     // true for the last row
position(): int    // 1-based position in parent; 0 on top-level/derived collections
```

Set at construction on nested rows; preserved through `asHtml()`/`asRaw()`.
Rows in a DERIVED collection (a `where()` result) get fresh positions in
the new collection.

## Filtering and Sorting

All return a new collection; nested-only methods throw
`InvalidArgumentException` on flat arrays and vice versa.

| Method                                                     | Behavior                                                                                                                                                                |
|------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `where(string $field, mixed $value = null): static`        | Nested only. Keeps rows where `$field == $value` (loose; `'1'` matches 1; Smart args unwrap). Rows without the field are dropped. Chain calls for AND. Warns when `$field` is missing from the first row. Single-arg `where($field)` keeps rows where the field is non-empty (PHP `empty()` rule: NULL, false, 0, "0", "", missing are empty). NOTE: `where($f)` and `where($f, null)` differ - the latter is a loose == null match |
| `whereNot(string $field, mixed $value = null): static`     | Nested only. Drops rows where `$field == $value`; rows WITHOUT the field are kept. Single-arg `whereNot($field)` keeps rows where the field is empty or missing (exact complement of `where($field)`)     |
| `whereInList(string $field, mixed $value): static`         | Nested only. Keeps rows where tab-separated `$field` contains `$value` as a whole value (`"\tmenu\tfooter\t"` format, CMS Builder checkbox/multi-select fields) or equals it as a plain single value. Never substring matching |
| `filter(?callable $callback = null): static`               | Both shapes. Callback receives raw `($value, $key)`, keeps on true. No callback: removes falsy (`""`, 0, null, false). Keys preserved like `array_filter()` - chain `values()` for a clean JSON array |
| `sort(int $flags = SORT_REGULAR): static`                  | Flat only. Sorts by value, renumbers keys                                                                                                                               |
| `sortBy(string $field, int $flags = SORT_REGULAR): static` | Nested only. Ascending by `$field`; rows missing the field sort first (like MySQL ORDER BY) and are kept unchanged. Numeric row keys renumber, string keys preserved. `SORT_NATURAL` for human number order |
| `unique(): static`                                         | Flat only. Removes duplicates keeping the first, keys preserved; compares as strings (`array_unique()`), so 1 and `'1'` are duplicates                                  |

## Transforming and Grouping

| Method                                                                             | Behavior                                                                                                                     |
|------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| `column(int\|string\|null $columnKey, int\|string\|null $indexKey = null): static` | Like `array_column()`: one field per row; `$indexKey` keys results by another field; `column(null, $indexKey)` keys whole rows |
| `columnAt(int $index): static`                                                     | The column at a position from each row, ignoring key names (0 first, -1 last)                                                |
| `indexBy(string $field): static`                                                   | Whole rows keyed by `$field`; duplicate keys keep the LAST row                                                               |
| `groupBy(string $field): static`                                                   | Rows grouped by `$field`: one child collection per distinct value                                                            |
| `keys(): static`                                                                   | The keys as a new collection (encode on output in HTML mode)                                                                 |
| `values(): static`                                                                 | The values, keys renumbered from 0                                                                                           |
| `map(callable $callback): static`                                                  | New collection from `$callback($rawValue)` per element; rows arrive as plain arrays; returned arrays become rows again       |
| `merge(array\|SmartArrayBase ...$arrays): static`                                  | Appends: numeric keys renumber, string keys overwrite (later wins)                                                           |
| `implode(string $separator = ''): SmartString\|string`                             | Flat only. Joins values; returns `SmartString` in HTML mode (encodes on output), plain `string` in raw mode                  |

## Guards

Fire when the COLLECTION IS EMPTY (no rows/elements; contrast SmartString's
field guards, which fire on missing values). Non-empty: return `$this`
unchanged, so they chain inline. `$text` is HTML-encoded automatically
(messages often interpolate user input).

| Method                                | On empty                                                                                                 |
|---------------------------------------|----------------------------------------------------------------------------------------------------------|
| `or404(?string $text = null): static` | HTTP 404 + minimal HTML page + `exit(1)`. Default text "The requested URL was not found on this server." |
| `orDie(string $text): static`         | Echo encoded text + `exit(1)`                                                                            |
| `orThrow(string $text): static`       | `throw new RuntimeException($encodedText)`                                                               |
| `orRedirect(string $url): static`     | 302 + `Location: $url` + `exit`. Checks `headers_sent()` immediately (throws even when non-empty)        |

```php
$article = $articles->where('num', $num)->first()->or404('Article not found');
```

(`first()` on empty returns SmartNull; its `or404()` delegates and fires.)

## Database Metadata

Set via the `$properties` constructor argument; ZenDB and CMS Builder do
this automatically.

- `mysqli(?string $property = null): int|string|null|array` - all metadata
  as an array with no argument (`[]` when none), or one value by name
  (`'affected_rows'`, `'insert_id'`, `'query'`, `'baseTable'`, ...);
  unknown or unset properties return null.
- `load(string $field): static|SmartNull` - loads related records for
  `$field` via the configured `loadHandler`. Returns `SmartNull` when the
  collection is empty; throws `RuntimeException` when no handler is set or
  when called on a record set (call it on a row). Handler contract: return
  `[rows, mysqliProperties]` or `false` (anything else throws).

## Debugging

```php
$collection->debug();   // contents, current mode, mysqli metadata; debug(1) adds types and internals
$collection->help();    // prints the method cheat sheet (src/help.txt)
print_r($collection);   // element data only; the class name identifies the mode
```

Output is `<xmp>`-wrapped in the browser and plain text on the command line.

## Errors and Exceptions

- **InvalidArgumentException**: unsupported value types in constructor or
  writes (objects/resources), flat/nested shape mismatches (`sort()` on
  nested, `where()` on flat), `getRawValue()` on unsupported objects,
  invalid `load()` field names.
- **RuntimeException**: `orThrow()` (message HTML-encoded), `orRedirect()`
  with headers already sent, writes to `SmartNull`, `load()` without a
  handler or with a bad handler return.
- **Error** (PHP native): undefined method calls, with did-you-mean
  suggestions and the caller's file:line.
- **E_USER_WARNING** (echoed + trigger_error): missing key on a result-set
  row; string conversion of a collection (`echo "$users"` yields `""`, page
  continues, message suggests `"{$var->method()}"` braces).
- **E_USER_DEPRECATED**: deprecated names and `$arr['key']` array syntax
  (see below).

## Deprecated Names

Old names still work but log deprecation notices naming the replacement.
When reading old code, translate:

| Deprecated                                               | Use instead                                            |
|----------------------------------------------------------|--------------------------------------------------------|
| `$arr['key']`, `$arr['key'] = $v` (array syntax)         | `$arr->key`, `$arr->key = $v` (braces for odd keys)    |
| `get($key)` / `get($key, $default)`                      | `->key` / `->{'key'}`; `?? $default` for defaults (NOTE: `??` also fires on stored NULLs; `get()`'s default only fired on missing keys) |
| `set($key, $value)`                                      | `->key = $value` / `->{'key'} = $value`                |
| `pluck($field)` / `pluck($field, $keyField)`             | `column($field)` / `column($field, $keyField)`         |
| `pluckNth($index)`                                       | `columnAt($index)`                                     |
| `nth($index)`                                            | `at($index)`                                           |
| `toRaw()`, `noSmartStrings()`, `disableSmartStrings()`   | `asRaw()`                                              |
| `toHtml()`, `withSmartStrings()`, `enableSmartStrings()` | `asHtml()` or `SmartArrayHtml::new()`                  |
| `smartMap($callback)`                                    | `map($callback)`                                       |
| `each($callback)`                                        | a `foreach` loop                                       |
| `sprintf($format)`                                       | `map()` with an inline format string                   |
| `where(['field' => $value, ...])` (array arg)            | chained `where('field', $value)` calls                 |
| `isMultipleOf($n)`, `chunk($size)`                       | retired, no replacement                                |

## Gotchas Quick Reference

- `SmartArrayHtml` is not instanceof `SmartArray`; type-hint
  `SmartArrayBase` to accept both.
- Foreach KEYS are never encoded, even in HTML mode; output keys via
  `keys()` or encode manually.
- Comparisons on HTML-mode fields compare the object: unwrap with
  `->value()`/`->int()` first. `empty($row->field)` is false for stored
  `""`/`0` (objects are truthy); use `$row->field->isEmpty()`.
- A stored NULL is not PHP null in HTML mode (`$row->x === null` is false);
  use `->value() === null` or `->isMissing()`. Raw mode returns real null.
- Braces stringify SmartString keys HTML-encoded: `$map->{$field}` misses
  on text with `'`/`&`; use `$map->{$field->value()}`. Numeric ids are safe.
- `(array)$collection` returns internal object data; use `toArray()`.
- `filter()` keeps keys; `json_encode()` of a gapped array is an object,
  not an array - chain `values()`.
- Collection guards fire on EMPTY collections; SmartString field guards
  fire on missing VALUES. `$row->or404()` (row) vs `$row->num->or404()`
  (field) differ.
- `where()` drops rows missing the field; `whereNot()` keeps them.
- `implode()` in HTML mode returns a SmartString: interpolating it into a
  raw-SQL string would encode the joined text; call `->string()` first or
  use raw mode for SQL.
- Missing-key warnings fire only on result-set rows; map and standalone
  lookups are silent by design.
- `echo $collection` / `"$users"` never works (collections have no string
  form); echo fields or `implode()`.

---

[← Documentation Index](README.md) | [← Prev: Troubleshooting](troubleshooting.md)
