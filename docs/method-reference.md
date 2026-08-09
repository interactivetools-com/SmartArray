# Method Reference

Every method, grouped by what it returns. Each group heading links to the
guide page that teaches those methods.

### [Basic Usage](getting-started.md)

```php
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;

$users = SmartArrayHtml::new($records);  // HTML mode: fields encode themselves when echoed
$data  = SmartArray::new($records);      // raw mode: fields are plain PHP values

echo $users->first()->name;                                   // read fields with the arrow operator
$active = $users->where('status', 'Active')->sortBy('name');  // methods chain left to right
```

Both classes share every method below. "Field" means a
[SmartString](https://github.com/interactivetools-com/SmartString) in HTML
mode and a plain PHP value in raw mode; methods that return a collection
return it in the same mode they were called in.

### [Creation and Conversion](getting-started.md)

*These move between modes and plain arrays. Conversions return a collection
in the other mode and leave the original unchanged; the original values are
always preserved.*

| Method                            | Description                                                                                                              |
|-----------------------------------|--------------------------------------------------------------------------------------------------------------------------|
| `SmartArrayHtml::new($array)`     | Creates an HTML-mode collection; nested arrays become rows                                                               |
| `SmartArray::new($array)`         | Creates a raw-mode collection; nested arrays become rows                                                                 |
| `->asHtml()`                      | Returns the collection in HTML mode (the same object if already HTML)                                                    |
| `->asRaw()`                       | Returns the collection in raw mode (the same object if already raw)                                                      |
| `->toArray()`                     | Returns a plain nested PHP array with the original values                                                                |
| `SmartArray::getRawValue($value)` | Returns the original value when you don't know what you have: Smart objects convert, plain values pass through unchanged |

### [Single Elements](displaying-fields.md)

*These return one element: a row comes back as a collection, a value as a
field. When there's nothing there, they return a chainable `SmartNull` that
outputs `""` and returns null from `->value()`. A `SmartNull` is an object,
and objects are always truthy in PHP, so test for missing values with
`->value()`, `isset()`, or `??`, not a bare `if`.*

| Method         | Description                                                                                |
|----------------|--------------------------------------------------------------------------------------------|
| `->first()`    | Returns the first element                                                                  |
| `->last()`     | Returns the last element                                                                   |
| `->at($index)` | Returns the element at a position, ignoring keys: 0 is first, negatives count from the end |

### [Collection Checks](displaying-fields.md#showing-a-no-results-message)

*These return plain values, typically used in if statements.*

| Method               | Description                                                                                  |
|----------------------|----------------------------------------------------------------------------------------------|
| `->count()`          | Returns the number of elements                                                               |
| `->isEmpty()`        | Returns true when there are no elements                                                      |
| `->isNotEmpty()`     | Returns true when there are any elements                                                     |
| `->contains($value)` | Returns true when any element matches `$value` (same rules as `where()`, so `"5"` matches 5) |

### [Row Position](outputting-html.md#loop-layout-isfirst-islast-position)

*Rows inside a result set know where they sit; use these for separators,
wrappers, and loop layout.*

| Method         | Description                                 |
|----------------|---------------------------------------------|
| `->isFirst()`  | Returns true for the first row              |
| `->isLast()`   | Returns true for the last row               |
| `->position()` | Returns the row's position, counting from 1 |

### [Filtering and Sorting](filtering-and-sorting.md)

*These return a new collection and leave the original unchanged.*

| Method                          | Description                                                                                                                                                                                                                                  |
|---------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `->where($field, $value)`       | Keeps rows where `$field` matches `$value` (`'1'` matches 1, but two strings must match exactly, and null only matches null); chain calls to match several fields. With `$field` alone, keeps rows where it's non-empty (PHP `empty()` rule) |
| `->whereNot($field, $value)`    | Drops rows where `$field` matches `$value` (same rules as `where()`); rows without the field are kept. With `$field` alone, keeps rows where it's empty or missing                                                                           |
| `->whereInList($field, $value)` | Keeps rows whose tab-separated `$field` contains `$value` (CMS Builder checkbox and multi-select format); matches whole values, never substrings                                                                                             |
| `->filter($callback)`           | Keeps elements where `$callback` returns true (closures receive plain PHP values as `($value, $key)`); with no callback, removes falsy values (PHP falsy rule: `""`, `"0"`, 0, NULL, false); keys are kept                                   |
| `->sort($flags)`                | Sorts a flat list ascending by value, renumbering keys; `$flags` choose how values compare (default `SORT_REGULAR`); `SORT_ASC`/`SORT_DESC` throw, sort descending in SQL                                                                    |
| `->sortBy($field, $flags)`      | Sorts rows ascending by `$field`; pass `SORT_NATURAL` to sort numbers the way people read them                                                                                                                                               |
| `->unique()`                    | Removes duplicate values from a flat list, keeping the first of each and preserving keys (compares as text, so 1 and `'1'` match); chain `->values()` to renumber                                                                            |

### [Transforming and Grouping](transforming-and-grouping.md)

*These return a new collection too, except `implode()`, which returns a
string (a SmartString in HTML mode, so it still encodes on output).*

| Method                            | Description                                                                                                                                                                      |
|-----------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `->column($columnKey, $indexKey)` | Returns one field from every row, like `array_column()`; `$indexKey` keys the values by another field; pass null as `$columnKey` to keep whole rows (same keying as `indexBy()`) |
| `->columnAt($index)`              | Returns the column at a position from each row, ignoring names: 0 is first, -1 is last                                                                                           |
| `->indexBy($field)`               | Keys whole rows by `$field`; rows sharing a key keep only the last                                                                                                               |
| `->groupBy($field)`               | Groups rows by `$field`, one collection of rows per value                                                                                                                        |
| `->keys()`                        | Returns the keys as a collection                                                                                                                                                 |
| `->values()`                      | Returns the values with keys renumbered from 0                                                                                                                                   |
| `->map($callback)`                | Rebuilds each element from what `$callback` returns (closures receive plain PHP values as `($value, $key)`; PHP built-ins get `$value` only)                                     |
| `->merge(...$arrays)`             | Appends one or more arrays: numeric keys renumber, string keys overwrite                                                                                                         |
| `->implode($separator)`           | Joins values into one string with `$separator` between them                                                                                                                      |

### [Requiring Results](displaying-fields.md#requiring-data-the-guards)

*Use these when a record must exist. They stop the page when the collection
is empty and otherwise return it unchanged, so they chain inline. Messages
HTML-encode automatically, so interpolated user input is safe.*

| Method                  | Description                                                                    |
|-------------------------|--------------------------------------------------------------------------------|
| `->or404($text = null)` | Sends a 404 page with `$text` (default: standard not-found text) and stops     |
| `->orDie($text)`        | Prints `$text` and stops                                                       |
| `->orThrow($text)`      | Throws a `RuntimeException` with `$text`                                       |
| `->orRedirect($url)`    | Sends a 302 redirect to `$url` and stops (throws if headers were already sent) |

### Database Metadata

*For collections created from query results; ZenDB and CMS Builder set
these up automatically.*

| Method                | Description                                                                                                     |
|-----------------------|-----------------------------------------------------------------------------------------------------------------|
| `->mysqli($property)` | Returns query metadata: one value by name (`'affected_rows'`, `'insert_id'`, ...) or all of it with no argument |
| `->load($field)`      | Loads the related record or records for `$field` using the configured load handler                              |

### Debugging

| Method      | Description                                                                             |
|-------------|-----------------------------------------------------------------------------------------|
| `->debug()` | Prints contents, current mode, and query metadata; readable in browser and command line |

**Working with single values?** Fields in HTML mode are
[SmartString](https://github.com/interactivetools-com/SmartString) objects,
and SmartString's own
[method reference](https://github.com/interactivetools-com/SmartString/blob/main/docs/method-reference.md)
covers everything they can do: `or()`, `dateFormat()`, `numberFormat()`,
`textOnly()`, and the rest.

---

[← Documentation Index](README.md) | [← Prev: Common Patterns](common-patterns.md) | [Next: Troubleshooting →](troubleshooting.md)
