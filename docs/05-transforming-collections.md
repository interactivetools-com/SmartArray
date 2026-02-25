# Transforming Collections

These methods reshape collections — extracting specific columns, reindexing by a key, iterating with side effects, or building new structures from existing data.

```php
$records = [
    ['id' => 1, 'name' => "Jean O'Brien",    'city' => 'Ottawa',      'status' => 'active'],
    ['id' => 2, 'name' => 'Tom & Jerry Inc', 'city' => 'Vancouver',   'status' => 'active'],
    ['id' => 3, 'name' => 'Eve <admin>',     'city' => 'Los Angeles', 'status' => 'inactive'],
];
$users = SmartArray::new($records);
```


## Extracting a Column — `pluck()`

### Extracting column values — `pluck($column)`

Extracts a single column from a nested array as a flat array. Essential for building ID lists, name lists, and similar extractions.

```php
// Extract all names as a flat array
$names = $users->pluck('name');
// -> ["Jean O'Brien", 'Tom & Jerry Inc', 'Eve <admin>']
```

### Extracting as a key => value map — `pluck($column, $keyColumn)`

Pass a second argument to use another column as keys. Creates a lookup map in one operation.

```php
// Build a [id => name] map
$nameById = $users->pluck('name', 'id');
// -> [1 => "Jean O'Brien", 2 => 'Tom & Jerry Inc', 3 => 'Eve <admin>']

// Look up a name by ID
echo $nameById->get(2); // Tom & Jerry Inc
```

Common pattern — building a CSV for use in a SQL `IN()` clause:

```php
$idCsv = $users->pluck('id')->map('intval')->unique()->implode(',');
// Use in: "SELECT * FROM users WHERE id IN ($idCsv)"
```


## PHP Array Column — `column()`

A full mirror of PHP's `array_column()`. Takes `$colKey` and an optional `$indexKey`.

```php
// Same as pluck() for simple extraction
$names = $users->column('name');

// Same as pluck() with a key column
$nameById = $users->column('name', 'id');
```

Use `pluck()` for SmartArray-idiomatic code; use `column()` when you specifically want `array_column()` semantics or are working from existing PHP habits.


## Reindexing by Column — `indexBy()`

### Rekey a nested array by column — `indexBy($column)`

Transforms a sequentially-indexed nested array into a lookup table indexed by a column value.

```php
// Index users by their ID
$usersById = $users->indexBy('id');

// Now look up by ID directly
echo $usersById->get(1)->name; // Jean O'Brien
echo $usersById->get(3)->city; // Los Angeles
```

**Warning:** If multiple rows share the same column value, `indexBy()` keeps the last one. Use `groupBy()` to preserve all rows when duplicates are possible.


## Grouping by Column — `groupBy()`

### Group rows by a column value — `groupBy($column)`

Groups rows into sub-arrays by a column value, preserving all rows including duplicates.

```php
// Group users by status
$byStatus = $users->groupBy('status');

foreach ($byStatus as $status => $group) {
    echo "$status: {$group->count()} users\n";
}
// active: 2 users
// inactive: 1 users
```

The result is a nested structure: the outer array is keyed by group value, and each inner array contains all matching rows.

Use `indexBy()` when you only need one record per key; use `groupBy()` when there can be multiple rows per key.


## Transforming Elements — `map()` and `smartMap()`

### Transforming with raw values — `map($callback)`

Calls a callback for each element and builds a new array from the returned values. The callback receives raw PHP values (strings, arrays — not SmartString or SmartArray wrappers).

```php
// Convert IDs to integers
$intIds = $users->pluck('id')->map('intval');
// -> [1, 2, 3]

// Build a custom label per element
$labels = $users->map(fn($row, $key) => "{$row['name']} ({$row['city']})");
// -> ["Jean O'Brien (Ottawa)", 'Tom & Jerry Inc (Vancouver)', 'Eve <admin> (Los Angeles)']
```

`map()` accepts both closures and any callable (e.g., `'intval'`, `'strtolower'`, `'trim'`). When passed a non-Closure callable, only the value is passed — not the key — to avoid accidentally passing the key as a second argument to functions like `intval($value, $base)`.

### Transforming with Smart objects — `smartMap($callback)`

> **Deprecation Notice:** `smartMap()` is deprecated and will be removed in a future version. Use `map()` with `asHtml()` for HTML-safe transformations instead.

Like `map()`, but the callback receives SmartString and SmartArray wrappers instead of raw values. Only accepts a `Closure` (not arbitrary callables).

```php
// Access values as SmartString inside the callback
$labels = $users->asHtml()->smartMap(fn($row, $key) => "$row->name ($row->city)");
```

Use `smartMap()` when you want to work with encoded values inside the callback. Use `map()` for pure data transformation.

**`map()` vs `smartMap()` — key differences:**

| | `map()` | `smartMap()` |
|---|---------|-------------|
| Callback receives | raw PHP values | SmartString/SmartArray |
| Accepts callable | any callable | Closure only |
| Use when | transforming data | formatting output |


## Side Effects — `each()`

### Iterating for side effects — `each($callback)`

Calls a callback on each element but does NOT build a new array. Return values from the callback are ignored. Returns `$this` so it can appear in a chain, but the array itself is unchanged.

```php
// Echo each row without building anything new
$users->asHtml()->each(fn($user) => print "<li>$user->name</li>\n");
```

`each()` is for output and logging, not transformation. If you need a new array, use `map()` or `smartMap()` instead.


## Keys, Values, and Merging

### Getting just the keys — `keys()`

Returns a flat SmartArray of the array's keys.

```php
$user = $users->first();
echo $user->keys()->implode(', '); // id, name, city, status
```

### Getting just the values — `values()`

Returns a flat SmartArray of the values, reindexed from 0.

```php
$row = $users->first()->values();
// -> [1, "Jean O'Brien", 'Ottawa', 'active']
```

### Merging arrays — `merge()`

Merges with one or more arrays or SmartArrays. Numeric keys are renumbered; string keys from later arrays overwrite earlier ones.

```php
$all = $usersA->merge($usersB, $usersC);
```


## Splitting into Chunks — `chunk()`

### Split into fixed-size groups — `chunk($size)`

> **Deprecation Notice:** `chunk()` is deprecated and will be removed in a future version. Use PHP's `array_chunk()` directly instead.

Splits a flat or nested array into sub-arrays of a given size. The last chunk may be smaller if the count is not evenly divisible.

```php
// Split 10 items into groups of 3
$pages = $items->chunk(3);
// -> [[item1, item2, item3], [item4, item5, item6], [item7, item8, item9], [item10]]
```

The primary use case is grid layouts. See [Output & HTML](06-output-and-html.md#fixed-size-chunks----chunk) for the full grid layout example comparing `chunk()` with `isMultipleOf()`.


## Putting It Together

Build a `<select>` dropdown from database records in one pipeline:

```php
// Build a select dropdown for user assignment
$options = SmartArray::new($records)
    ->where(['status' => 'active'])
    ->sortBy('name')
    ->asHtml()
    ->pluck('name', 'id')
    ->sprintf("<option value='{key}'>{value}</option>")
    ->implode("\n");

echo "<select name='assigned_to'>\n$options\n</select>";
```

What each step does:

- `where(['status' => 'active'])` — only active users are valid assignees
- `sortBy('name')` — alphabetical order for the dropdown
- `asHtml()` — values will encode on output
- `pluck('name', 'id')` — creates an `[id => name]` map
- `sprintf(...)` — formats each entry as an option element; values are HTML-encoded by `SmartArrayHtml`
- `implode("\n")` — joins all options into a single string

---

[← Back to README](../README.md) | [← Filtering & Sorting](04-filtering-and-sorting.md) | [Next: Output & HTML →](06-output-and-html.md)
