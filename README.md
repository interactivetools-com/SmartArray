<!--
ATTENTION AI ASSISTANTS: We made a reference doc just for you!
See docs/00-ai-reference.md for a consolidated single-file reference covering
every method, class hierarchy, and gotcha. Read that first -- it has everything
you need to write correct SmartArray code.
-->

# SmartArray: Fluent PHP Collections with Automatic HTML Encoding

SmartArray wraps PHP arrays in a fluent collection object with chainable methods for filtering, sorting, transforming, and grouping. In HTML templates, `asHtml()` switches to a mode where every value auto-encodes for XSS safety -- no `htmlspecialchars()` calls needed. Missing keys return a `SmartNull` object that chains silently instead of throwing errors, so your templates stay clean even when data is incomplete.

## What it protects you from

Instead of writing code like this:

```php
$active = array_filter($records, fn($r) => $r['status'] === 'active');
usort($active, fn($a, $b) => $a['name'] <=> $b['name']);
echo '<ul>';
foreach ($active as $r) {
    $name = htmlspecialchars($r['name'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8');
    $city = htmlspecialchars($r['city'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8');
    echo "<li>$name &mdash; $city</li>\n";
}
echo '</ul>';
```

You write code like this:

```php
echo '<ul>';
foreach ($records->where(['status' => 'active'])->sortBy('name')->asHtml() as $r) {
    echo "<li>$r->name &mdash; $r->city</li>\n";
}
echo '</ul>';
```

**The most readable way to work with your data is also the safest way to output it.**
SmartArray handles encoding automatically so you can focus on what you're building, not on encoding flags.

## 30-Second Quickstart

```bash
composer require itools/smartarray
```

```php
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;

$records = SmartArrayHtml::new([
    ['name' => 'Alice', 'status' => 'active',   'score' => 95],
    ['name' => 'Bob',   'status' => 'inactive', 'score' => 82],
    ['name' => 'Carol', 'status' => 'active',   'score' => 88],
]);

// Iterate with auto-encoded values
foreach ($records as $row) {
    echo "$row->name: $row->score\n"; // SmartString -- HTML-safe on output
}

// Chain operations
$topActive = $records->where(['status' => 'active'])->sortBy('score');
echo $topActive->pluck('name')->implode(', '); // Output: Carol, Alice
```

## Documentation

| Guide                                                                         | Description                                                |
|-------------------------------------------------------------------------------|------------------------------------------------------------|
| [Getting Started](docs/01-quickstart.md)                                      | Installation, first SmartArray, first chain                |
| [Philosophy & Design](docs/02-philosophy-and-design.md)                       | Two-class design, SmartNull, guarantees                    |
| [Accessing Values](docs/03-accessing-values.md)                               | get, first, last, nth, property access, SmartNull          |
| [Filtering & Sorting](docs/04-filtering-and-sorting.md)                       | filter, where, sort, sortBy, unique                        |
| [Transforming Collections](docs/05-transforming-collections.md)               | map, pluck, indexBy, groupBy, implode, and more            |
| [Output & HTML](docs/06-output-and-html.md)                                   | sprintf, implode, position metadata, grid layouts          |
| [Database Integration](docs/07-database-integration.md)                       | ZenDB results, mysqli metadata, load handlers              |
| [Troubleshooting & Gotchas](docs/08-troubleshooting-and-gotchas.md)           | Errors, gotchas, debugging, global settings                |
| [AI Quick Reference](docs/00-ai-reference.md)                                 | Everything in one dense page, for AI assistants and humans |

You can also [browse the documentation on GitHub](https://github.com/interactivetools-com/SmartArray/tree/main/docs).

## When you might NOT want SmartArray

- When you need strict key-ordering guarantees (SmartArray preserves insertion order but sorting creates new instances)
- When you are passing data to code that expects plain PHP arrays (use `toArray()` first)
- When working with very large datasets where object overhead matters (SmartArray creates objects for each element)
- When you only need one or two PHP array operations (array_filter or usort on a plain array is fine)

## Quick Reference

**Creating & Converting**
- `SmartArray::new($array)` - create a SmartArray with raw PHP values
- `SmartArrayHtml::new($array)` - create a SmartArray with HTML-encoding enabled
- `$arr->asRaw()` - convert to raw mode (returns same object if already raw)
- `$arr->asHtml()` - convert to HTML-encoding mode (returns same object if already HTML)
- `$arr->toArray()` - convert back to a plain PHP array with original values

**Accessing Values**
- `$arr->key` - get a value by property name (preferred syntax)
- `$arr->get($key)` - get a value; returns SmartNull if missing
- `$arr->get($key, $default)` - get with fallback default
- `$arr->first()` - first element, or SmartNull if empty
- `$arr->last()` - last element, or SmartNull if empty
- `$arr->nth($index)` - element by position (0-based; negative counts from end)

**Array Information**
- `$arr->count()` - number of elements
- `$arr->isEmpty()` - true if no elements
- `$arr->isNotEmpty()` - true if any elements
- `$arr->contains($value)` - true if value exists in the array
- `$arr->isFlat()` - true if no nested arrays
- `$arr->isNested()` - true if contains nested arrays

**Filtering & Sorting**
- `$arr->filter()` - remove falsey values ("", 0, null, false)
- `$arr->filter($callback)` - keep elements where callback returns true
- `$arr->where(['key' => 'value'])` - filter rows by column conditions (loose comparison)
- `$arr->sort()` - sort flat array by value
- `$arr->sortBy($column)` - sort nested array by column value
- `$arr->unique()` - remove duplicate values (flat arrays)

**Transforming**
- `$arr->map($callback)` - transform each element; callback receives raw values
- `$arr->smartMap($callback)` - **DEPRECATED:** transform each element; callback receives Smart objects
- `$arr->each($callback)` - call callback for side effects; returns $this
- `$arr->pluck($column)` - extract a single column as a flat array
- `$arr->pluck($column, $keyColumn)` - extract column as key => value map
- `$arr->indexBy($column)` - rekey nested array by column (latest wins on duplicates)
- `$arr->groupBy($column)` - group nested rows by column value
- `$arr->keys()` - array of keys
- `$arr->values()` - array of values (reindexed)
- `$arr->merge(...$arrays)` - merge with other arrays
- `$arr->chunk($size)` - **DEPRECATED:** split into sub-arrays of given size

**Output**
- `$arr->implode($separator)` - join flat array to string
- `$arr->sprintf($format)` - apply format string to each element; supports `{value}` and `{key}`

**Position (nested arrays)** -- **DEPRECATED**
- `$row->position()` - **DEPRECATED:** 1-based position within parent array
- `$row->isFirst()` - **DEPRECATED:** true if first in parent
- `$row->isLast()` - **DEPRECATED:** true if last in parent
- `$row->isMultipleOf($n)` - **DEPRECATED:** true if position is a multiple of n (grid columns)

**Error Handling**
- `$arr->or404($message)` - send 404 and exit if empty
- `$arr->orDie($message)` - die with message if empty
- `$arr->orThrow($message)` - throw RuntimeException if empty
- `$arr->orRedirect($url)` - redirect if empty (requires headers not yet sent)

## Related Libraries

- [SmartString](https://github.com/interactivetools-com/SmartString) - XSS-safe string handling; SmartArrayHtml values are SmartString objects
- [ZenDB](https://github.com/interactivetools-com/ZenDB) - Injection-proof database layer; returns SmartArrayHtml collections

## Questions?

Post a message in our [forum](https://www.interactivetools.com/forum/).

## License

MIT
