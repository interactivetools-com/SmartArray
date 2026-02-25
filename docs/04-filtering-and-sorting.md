# Filtering and Sorting

SmartArray provides distinct methods for narrowing and ordering collections — each suited to a different scenario.

```php
$records = [
    ['id' => 1, 'name' => "Jean O'Brien",    'city' => 'Ottawa',      'status' => 'active'],
    ['id' => 2, 'name' => 'Tom & Jerry Inc', 'city' => 'Vancouver',   'status' => 'active'],
    ['id' => 3, 'name' => 'Eve <admin>',     'city' => 'Los Angeles', 'status' => 'inactive'],
];
$users = SmartArray::new($records);
```

## Filtering by Value — `filter()`

### Removing falsey values — `filter()`

Called with no arguments, `filter()` removes any element that PHP considers falsey: `""`, `0`, `"0"`, `null`, `false`, `[]`.

```php
$mixed = SmartArray::new([1, 0, 'hello', '', null, 'world', false]);
$clean = $mixed->filter();
print_r($clean->toArray()); // [1, 'hello', 'world']
```

**Warning:** `filter()` with no callback removes `0` and `""`. In database records, a field containing `0` or `""` represents real data — an unpublished article, a zero-dollar discount, an empty nickname. Use `where()` to filter records by column conditions instead.

### Filtering with a callback — `filter($callback)`

Pass a callback to keep or discard elements based on your own condition. The callback receives raw values (not SmartString or SmartArray wrappers). Return `true` to keep an element, `false` to remove it.

```php
// Keep only users with names longer than 10 characters
$longNames = $users->filter(fn($row) => strlen($row['name']) > 10);
```

The callback receives both value and key as arguments:

```php
// Keep only elements at even keys
$evens = $users->filter(fn($value, $key) => $key % 2 === 0);
```

`filter()` returns a new SmartArray. The original is not modified.

## Filtering by Condition — `where()`

### Filtering rows by condition — `where()`

`where()` is designed for nested arrays (database records). Pass an associative array of conditions to keep only the rows that match all of them.

```php
// Keep rows where status matches 'active'
$active = $users->where(['status' => 'active']);
```

A two-argument shorthand is also available:

```php
$active = $users->where('status', 'active');
```

**Loose comparison:** `where()` uses `==` (not `===`) intentionally. This makes it tolerant of database and form data where string `'1'` should match integer `1`. If you need strict comparison, use `filter()` with a callback and `===`.

Filter by multiple conditions — all must match:

```php
// Must match ALL conditions (AND logic)
$results = $users->where(['status' => 'active', 'city' => 'Ottawa']);
```

Elements that are not arrays are automatically skipped. `where()` returns a new SmartArray; the original is not modified.

### `filter()` vs `where()` — which to use

| Situation | Use |
|-----------|-----|
| Remove empty/falsey values from a flat array | `filter()` |
| Filter by a custom condition | `filter($callback)` |
| Filter record rows by column values | `where()` |
| Strict equality required | `filter($callback)` with `===` |

## Sorting Flat Arrays — `sort()`

### Sorting by value — `sort()`

`sort()` sorts a flat array by value. Strings sort alphabetically, numbers sort numerically. Returns a new SmartArray; the original is not modified. Keys are not preserved — the result is always reindexed.

```php
$names = SmartArray::new(['Charlie', 'Alice', 'Bob']);
$sorted = $names->sort();
print_r($sorted->toArray()); // ['Alice', 'Bob', 'Charlie']
```

Pass a PHP sort flag as an optional argument for precise control:

```php
$sorted = $names->sort(SORT_STRING | SORT_FLAG_CASE); // case-insensitive
```

**Note:** `sort()` requires a flat array. Calling it on a nested array (a result set with rows) throws `InvalidArgumentException`. Use `sortBy()` for nested arrays.

## Sorting by Column — `sortBy()`

### Sorting records by a column — `sortBy()`

`sortBy()` is designed for nested arrays. Pass the column name to sort by.

```php
// Sort users alphabetically by name
$sorted = $users->sortBy('name');

// Sort numerically by id
$sorted = $users->sortBy('id');
```

Pass a PHP sort type constant as the optional second argument for precise comparison control:

```php
$sorted = $users->sortBy('name', SORT_STRING | SORT_FLAG_CASE); // case-insensitive
$sorted = $users->sortBy('id',   SORT_NUMERIC);
```

Accepted type constants include `SORT_STRING`, `SORT_NUMERIC`, `SORT_NATURAL`, `SORT_LOCALE_STRING`, and others from PHP's `array_multisort()`.

`sortBy()` returns a new SmartArray sorted ascending. The original is not modified.

**Note:** `sortBy()` requires a nested array. Calling it on a flat array throws `InvalidArgumentException`. Use `sort()` for flat arrays.

## Removing Duplicates — `unique()`

### Removing duplicate values — `unique()`

`unique()` removes duplicate values from a flat array, keeping only the first occurrence of each. Keys are preserved.

```php
$ids = SmartArray::new([1, 2, 2, 3, 1, 4]);
$unique = $ids->unique();
print_r($unique->toArray()); // [1, 2, 3, 4]
```

A common pattern — collect unique values from a column and join them:

```php
$cityList = $users->pluck('city')->unique()->implode(', ');
// Ottawa, Vancouver, Los Angeles
```

`unique()` requires a flat array. The original is not modified.

## Putting It Together

A realistic scenario: get active users sorted by name, with no duplicate cities.

```php
$users = SmartArray::new($records);

// Active users, sorted alphabetically, unique cities only
$result = $users
    ->where(['status' => 'active'])
    ->sortBy('name')
    ->pluck('city')
    ->unique();

foreach ($result->asHtml() as $city) {
    echo "<li>$city</li>\n";
}
```

What each step does:

- `where(['status' => 'active'])` — keeps only rows where `status` matches `'active'`
- `sortBy('name')` — orders those rows alphabetically by the `name` column
- `pluck('city')` — extracts just the city column as a flat array of strings
- `unique()` — removes any duplicate city names

Each method returns a new SmartArray, so the chain reads left-to-right like a pipeline.

---

[← Back to README](../README.md) | [← Accessing Values](03-accessing-values.md) | [Next: Transforming Collections →](05-transforming-collections.md)
