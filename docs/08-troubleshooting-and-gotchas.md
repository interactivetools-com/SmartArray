# Troubleshooting and Gotchas

This page covers common exception messages, behavioral surprises, debugging techniques, and the migration path from deprecated syntax.

## Common Exception Messages

### "sort(): Expected a flat array, but got a nested array"

**What happened:** You called `sort()` on a nested array (a result set of rows). `sort()` only works on flat arrays — arrays whose values are scalars, not sub-arrays.

```php
// Wrong -- $users is a nested array (rows of records)
$sorted = $users->sort();
```

```php
// Right -- sortBy() is for nested arrays
$sorted = $users->sortBy('name');
```

**Fix:** Use `sortBy('column')` when your array contains rows. Use `sort()` only on flat lists of scalars.

### "sortBy(): Expected a nested array, but got a flat array"

**What happened:** You called `sortBy()` on a flat list. `sortBy()` expects a nested array so it can sort rows by a named column.

```php
// Wrong -- $names is a flat list of strings
$names  = ['Charlie', 'Alice', 'Bob'];
$sorted = SmartArray::new($names)->sortBy('name');
```

```php
// Right -- sort() works on flat arrays
$sorted = SmartArray::new($names)->sort();
```

**Fix:** Use `sort()` for flat arrays. Use `sortBy('column')` for nested arrays (result sets).

### "implode(): Expected a flat array, but got a nested array"

**What happened:** You called `implode()` on a nested array (a result set of rows). `implode()` only works on flat arrays of scalar values.

```php
// Wrong -- $users is a nested array
$list = $users->implode(', ');
```

```php
// Right -- pluck a column first, then implode
$list = $users->pluck('name')->implode(', ');
```

**Fix:** Extract the column you want with `pluck('column')` before calling `implode()`.

### "indexBy(): Expected a nested array, but got a flat array"

**What happened:** You called `indexBy()` on a flat list. `indexBy()` expects a nested array so it can re-key rows by a named column.

```php
// Wrong -- $names is a flat list
$names   = ['Alice', 'Bob', 'Charlie'];
$indexed = SmartArray::new($names)->indexBy('name');
```

```php
// Right -- indexBy() works on nested arrays (result sets)
$indexed = $users->indexBy('id'); // keys rows by their 'id' column
```

**Fix:** Use `indexBy()` on result sets (nested arrays), not on flat lists.

### "Cannot call load() on record set, only on a single row."

**What happened:** You called `load()` on the whole result set instead of on an individual row. `load()` fetches related records for a specific row.

```php
// Wrong -- calling load() on the result set
$orders = $users->load('orders');
```

```php
// Right -- call load() inside the loop on each individual row
foreach ($users as $user) {
    $orders = $user->load('orders');
    echo "$user->name has {$orders->count()} orders\n";
}
```

**Fix:** Call `load()` inside a `foreach` loop, on the individual row variable.

### "orRedirect(): headers already sent in {file} on line {line}"

**What happened:** Output (HTML, whitespace, or a BOM) was sent to the browser before `orRedirect()` was called. PHP cannot send headers after output has started.

```php
// Wrong -- output sent before the redirect check
echo "<html>";
$session = DB::get('sessions', "token = ?", $token)->orRedirect('/login');
```

```php
// Right -- call orRedirect() before any output
$session = DB::get('sessions', "token = ?", $token)->orRedirect('/login');
echo "<html>";
```

**Fix:** Move all `orRedirect()` calls before any output. Check for trailing whitespace or BOM characters at the top of your PHP files if output appears to start before your code does.

### "Chunk size must be greater than 0."

**What happened:** You passed zero or a negative number to `chunk()`.

```php
// Wrong -- chunk size must be at least 1
$pages = $items->chunk(0);
```

```php
// Right
$pages = $items->chunk(3);
```

**Fix:** Pass a positive integer to `chunk()`.

### "Cannot set values on SmartNull"

**What happened:** You tried to assign a value using array bracket syntax on a SmartNull — the object returned when a key does not exist. SmartNull is read-only; you cannot write to it.

```php
// Wrong -- $arr->missingKey returns SmartNull; array assignment on SmartNull throws
$arr = SmartArray::new(['name' => 'Alice']);
$arr->missingKey['subkey'] = 'value';
```

```php
// Right -- set values directly on the SmartArray using set() or property syntax
$arr->set('missingKey', 'value');
$arr->missingKey = 'value';
```

**Fix:** Set values on the SmartArray itself, not on a value returned from a missing-key lookup.

## Gotchas

**1. `filter()` removes zeros and empty strings**

`filter()` with no callback uses PHP's default truthiness check, which removes `0`, `0.0`, `""`, `"0"`, `null`, and `false`. If you have database records where a field value of `0` is meaningful (e.g., `quantity = 0`, `sort_order = 0`), use `where()` instead.

```php
// Wrong -- removes rows where quantity is 0
$inStock = $products->filter();

// Right -- where() keeps rows explicitly matching your condition
$inStock = $products->where(['active' => 1]);
```

**2. `where()` uses loose `==` comparison**

`where(['active' => 1])` matches both integer `1` and string `'1'`. This is intentional for database tolerance — query results often return integers as strings depending on the driver. If you need strict matching, use `filter()` with an explicit callback.

```php
// where() -- loose comparison, matches both 1 and '1'
$activeUsers = $users->where(['active' => 1]);

// filter() -- strict comparison when you need it
$activeUsers = $users->filter(fn($row) => $row['active'] === 1);
```

**3. `sprintf()` always returns raw `SmartArray`**

Even when called on `SmartArrayHtml`, `sprintf()` returns a plain `SmartArray`. Values are HTML-encoded inside the format string, but the resulting array is raw — you cannot chain SmartString methods on result values.

```php
// The result of sprintf() is SmartArray, not SmartArrayHtml
$items = $users->asHtml()->pluck('name')->sprintf("<b>{value}</b>");
// $items is SmartArray -- values are pre-formatted HTML strings
$html = $items->implode("\n"); // string, not SmartString
```

**4. `position()` returns 0 for root arrays**

`position()`, `isFirst()`, and `isLast()` only have meaning for rows nested inside a result set. Calling these on the root result set returns `0` or `false` respectively.

```php
// Wrong -- calling position() on the result set
$users->position(); // returns 0

// Right -- position() is meaningful on individual rows
foreach ($users as $user) {
    echo $user->position(); // returns 1, 2, 3, ...
}
$users->first()->position(); // returns 1
```

**5. Position metadata is not updated after construction**

If you add an element to a SmartArray after creating it (e.g., `$arr->newKey = 'value'`), the new element has no position metadata (`position()` returns `0`, `isFirst()` and `isLast()` return `false`). To refresh position metadata, recreate the array from its current contents.

```php
// Position metadata won't be set for elements added after construction
$arr->extraRow = ['name' => 'Extra'];

// Recreate to refresh position metadata
$arr = SmartArray::new($arr->toArray());
```

**6. `map()` and `smartMap()` have different callback signatures**

`map()` accepts any callable and passes `($value, $key)` for Closures, or only `($value)` for non-Closure callables (to avoid misinterpreting the key as a function argument like a numeric base). `smartMap()` only accepts `Closure` and always passes `($smartValue, $key)` where the value is a SmartString or SmartArray.

```php
// map() -- works with any callable; non-Closures only receive $value
$upper = $names->map('strtoupper');               // non-Closure: only value passed
$upper = $names->map(fn($v, $k) => strtoupper($v)); // Closure: value and key passed

// smartMap() -- Closure only; value is SmartString or SmartArray
$formatted = $names->asHtml()->smartMap(fn($name, $k) => $name->upper());
```

**7. `pluck()` has an optional `$keyColumn` second argument**

`pluck('name')` returns a flat list of name values. `pluck('name', 'id')` returns a `[id => name]` map. The second argument is easy to miss if you have only ever seen the one-argument form.

```php
// Flat list
$names  = $users->pluck('name');            // [0 => 'Alice', 1 => 'Bob', ...]

// Keyed map
$byId   = $users->pluck('name', 'id');      // [1 => 'Alice', 2 => 'Bob', ...]
```

**8. `each()` ignores callback return values**

`each()` is designed for side effects — echoing output, logging, dispatching events. Return values from the callback are silently discarded. If you need a transformed result, use `map()` or `smartMap()`.

```php
// Wrong -- return value is discarded; $results stays empty
$results = [];
$users->each(fn($user) => $user->name->value()); // return is discarded

// Right -- use map() to collect transformed values
$names = $users->map(fn($row) => $row['name']);
```

**9. `__toString()` outputs a warning instead of fataling**

If a SmartArray ends up in a string context (e.g., `echo $users`), PHP would normally throw a fatal error for non-stringable objects. SmartArray catches this and outputs an empty string with a PHP warning instead. This means type bugs can hide silently — if output is mysteriously empty, check for SmartArray objects where strings are expected.

```php
// Silent bug -- $users is a SmartArray, not a string
echo "Users: $users"; // outputs warning + empty string, not a fatal error
// Should be:
echo "Users: {$users->pluck('name')->implode(', ')}";
```

**10. `sort()` returns a new array**

Like all SmartArray transformation methods, `sort()` does not modify in place. The original array is unchanged. Always assign the return value.

```php
// Wrong -- $users is unchanged
$users->sort();

// Right -- assign the sorted result
$sorted = $users->sort();
```

## Debugging Tips

**1. Inspect structure with `print_r()`**

Call `print_r($arr)` on any SmartArray to see a structured debug view showing nested elements, each element's type, position metadata, and a note that `->help()` is available.

**2. Show detailed internals with `debug()`**

`$arr->debug()` outputs detailed information about the array including data values, mysqli metadata (for database results), and which columns have load handlers available. Pass `1` for more detail including object IDs and internal properties.

```php
$users->debug();   // standard output
$users->debug(1);  // includes object IDs and internal properties
```

**3. List available methods with `help()`**

`$arr->help()` outputs a compact reference of all available methods with short descriptions. Call it any time you forget a method name or want to know what options are available.

```php
$arr->help(); // prints method reference inline
```

**4. Find deprecated usage with deprecation logging**

SmartArray logs deprecation warnings when deprecated methods are called. These appear in your PHP error log and help identify usage patterns that need updating.

## Migrating from Array Syntax

SmartArray originally used array bracket syntax (`$arr['key']`). This is now deprecated in favor of property access (`$arr->key`) and `get()`.

**Why it was deprecated:** Array bracket access (`$arr['key']`) triggers PHP's `offsetGet()` method, which goes through a separate code path that cannot benefit from the same optimizations and type safety as property access. Property access is more readable, consistent, and idiomatic for object-oriented code.

Finding usages to migrate:

```bash
# Find array bracket access on variables named like SmartArrays
grep -rn '\$[a-zA-Z_]\+\[' src/ templates/
```

What to change:

| Old (deprecated) | New (preferred) |
|-----------------|-----------------|
| `$arr['key']` | `$arr->key` or `$arr->get('key')` |
| `$arr['key'] = $val` | `$arr->key = $val` or `$arr->set('key', $val)` |

## Deprecated Methods Reference

The following methods and class names are deprecated. They still work but may log deprecation warnings to your PHP error log.

| Old (deprecated) | Replacement | Notes |
|-----------------|-------------|-------|
| `SmartArrayRaw` class | `SmartArray` class | Direct alias; triggers `E_USER_DEPRECATED` on construction |
| `SmartArrayRaw::new()` | `SmartArray::new()` | Triggers `E_USER_DEPRECATED` |
| `SmartArray::new($data, true)` | `SmartArrayHtml::new($data)` | Boolean second argument deprecated |
| `SmartArray::new($data, false)` | `SmartArray::new($data)` | Boolean second argument deprecated |
| `->exists()` | `->isNotEmpty()` | Renamed for clarity |
| `->firstRow()` | `->first()` | Renamed for consistency |
| `->getFirst()` | `->first()` | Renamed for consistency |
| `->getValues()` | `->values()` | Renamed to match PHP conventions |
| `->item($key)` | `->get($key)` | Renamed for clarity |
| `->join($sep)` | `->implode($sep)` | Renamed to match PHP conventions |
| `->raw()` | `->toArray()` | Renamed for clarity |
| `->toRaw()` | `->asRaw()` | Renamed for consistency |
| `->toHtml()` | `->asHtml()` | Renamed for consistency |
| `->withSmartStrings()` | `->asHtml()` or `SmartArrayHtml::new()` | Renamed for clarity |
| `->enableSmartStrings()` | `->asHtml()` or `SmartArrayHtml::new()` | Renamed for clarity |
| `->noSmartStrings()` | `->asRaw()` or `SmartArray::new()` | Renamed for clarity |
| `->disableSmartStrings()` | `->asRaw()` or `SmartArray::new()` | Renamed for clarity |
| `->getColumn()` | `->pluck()` or another method | Removed; use `pluck()` for column extraction |
| `SmartArray::newSS(...)` | `SmartArrayHtml::new(...)` | Static factory renamed |
| `SmartArray::rawValue(...)` | `SmartArray::getRawValue(...)` | Static method renamed |

---

[← Back to README](../README.md) | [← Database Integration](07-database-integration.md)
