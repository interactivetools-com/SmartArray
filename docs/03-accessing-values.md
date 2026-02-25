# Accessing Values

SmartArray provides multiple ways to read elements, check for their existence, and work safely with data that may have missing keys.

```php
$records = [
    ['id' => 1, 'name' => "Jean O'Brien",    'city' => 'Ottawa',      'status' => 'active'],
    ['id' => 2, 'name' => 'Tom & Jerry Inc', 'city' => 'Vancouver',   'status' => 'active'],
    ['id' => 3, 'name' => 'Eve <admin>',     'city' => 'Los Angeles', 'status' => 'inactive'],
];
$users = SmartArray::new($records);
```

## Property Access — `$arr->key`

The preferred syntax for reading values is `$arr->key`. It reads naturally, works with IDE autocomplete, and requires no extra punctuation.

```php
$user = $users->first();

// Access named string keys as properties
echo $user->name; // Jean O'Brien
echo $user->city; // Ottawa
echo $user->id;   // 1
```

For numeric keys or keys that contain spaces, dots, or other special characters, use `get()` instead — PHP does not allow those as property names.

```php
// Numeric key requires get()
echo $users->get(0)->name; // Jean O'Brien
```

## Method Access

### Getting a value — `get()`

`get($key)` returns the element at the given key, or a `SmartNull` if the key does not exist.

```php
$user = $users->get(0);
echo $user->name; // Jean O'Brien
```

Pass a second argument to return a default value when the key is missing:

```php
// Default scalar
echo $users->first()->get('nickname', 'anonymous'); // anonymous

// Default array (returned as a new SmartArray)
$fallback = $users->first()->get('address', ['city' => 'Unknown']);

// Default SmartArray
$empty = SmartArray::new([]);
$result = $users->get('nonexistent', $empty);
```

The default can be a scalar, `null`, an array, or an existing `SmartArray`. Passing any other type throws `InvalidArgumentException`.

### Getting the first element — `first()`

`first()` returns the conceptual first element of the array, regardless of what keys it uses.

```php
echo $users->first()->name; // Jean O'Brien
```

If the array is empty, `first()` returns `SmartNull` so you can chain further without a null check.

### Getting the last element — `last()`

`last()` returns the conceptual last element of the array.

```php
echo $users->last()->name; // Eve <admin>
```

Returns `SmartNull` if the array is empty.

### Getting by position — `nth()`

`nth()` retrieves an element by its zero-based position, counting from the start of the array regardless of what keys it uses.

```php
echo $users->nth(0)->name;  // Jean O'Brien  (first)
echo $users->nth(2)->name;  // Eve <admin>   (third)
echo $users->nth(-1)->name; // Eve <admin>   (last)
echo $users->nth(-2)->name; // Tom & Jerry Inc (second-to-last)
```

Negative indices count from the end. Returns `SmartNull` if the index is out of range.

`first()` is equivalent to `nth(0)`, and `last()` is equivalent to `nth(-1)`.

## Checking What's There

### Checking element count — `count()`

`count()` returns the number of elements in the array.

```php
echo $users->count(); // 3

// Works with flat arrays too
$cities = $users->pluck('city');
echo $cities->count(); // 3
```

### Checking for empty/not-empty — `isEmpty()` and `isNotEmpty()`

`isEmpty()` returns `true` when the array has no elements. `isNotEmpty()` returns `true` when it has at least one.

```php
if ($users->isNotEmpty()) {
    // render the list
}

$filtered = $users->where(['status' => 'inactive']);
if ($filtered->isEmpty()) {
    echo "No inactive users.";
}
```

`isNotEmpty()` is equivalent to `count() > 0` but reads more naturally in conditionals.

### Checking for a value — `contains()`

`contains()` checks whether a specific value exists anywhere in a flat array.

```php
// Extract the status column first, then check
$statuses = $users->pluck('status');
echo $statuses->contains('active');   // true
echo $statuses->contains('pending');  // false
```

`contains()` works on the values of a flat array. To filter nested arrays (record rows) by a column value, use `where()` instead.

### Detecting array shape — `isFlat()` and `isNested()`

`isFlat()` returns `true` when no elements are arrays. `isNested()` returns `true` when at least one element is an array.

```php
$users->isNested();                  // true  (rows are arrays)
$users->pluck('name')->isFlat();     // true  (just strings)
```

These are useful when you need to confirm the shape before calling a method that requires one form. For example, `sort()` requires a flat array and `sortBy()` requires a nested array — checking first gives a clear error path.

## Detecting Your Mode — `usingSmartStrings()`

`usingSmartStrings()` returns `true` if the array is in HTML mode (`SmartArrayHtml`), or `false` if it is in raw mode (`SmartArray`).

```php
$raw  = SmartArray::new($records);
$html = SmartArrayHtml::new($records);

$raw->usingSmartStrings();  // false
$html->usingSmartStrings(); // true
```

This is mainly useful when debugging: if values are not being HTML-encoded the way you expect, `usingSmartStrings()` tells you immediately which mode you are in.

## SmartNull: Safe Access for Missing Keys

When you access a key that does not exist, SmartArray does not return `null` or throw an exception. It returns a `SmartNull` object.

`SmartNull` implements the same interfaces as `SmartArray` and `SmartString`. All method calls on it return safe empty values, and you can chain it indefinitely without errors.

```php
$missing = $users->get('nonexistent');

// All of these are safe -- SmartNull handles them gracefully
echo $missing;           // ""  (empty string)
echo $missing->count();  // 0
echo $missing->isEmpty();// true
foreach ($missing as $item) {} // does nothing, no iteration

// Chain as deep as you want -- SmartNull keeps returning SmartNull
echo $missing->name->value(); // ""
```

`SmartNull` does not allow assignment:

```php
// Wrong -- throws RuntimeException: Cannot set values on SmartNull
$missing['some_key'] = 'value';

// Right -- assign on the actual array using set()
$users->set('some_key', 'value');
```

To check whether you received a `SmartNull`:

```php
use Itools\SmartArray\SmartNull;

$val = $users->get('nonexistent');

// Check by type
if ($val instanceof SmartNull) {
    // key was missing
}

// Or check count -- both SmartNull and empty arrays return 0
if ($val->count() === 0) {
    // either SmartNull or an empty array
}
```

**Intentional warnings for missing keys:** Accessing an undefined key on a non-empty array may trigger a PHP warning depending on your error reporting settings. This helps catch typos in key names early.

## Getting Raw Data

### Converting to PHP array — `toArray()`

`toArray()` recursively converts the SmartArray — and all nested SmartArrays and SmartStrings — back to a plain PHP array of scalar values.

```php
$plain = $users->toArray();
// Returns a regular PHP array of arrays, suitable for json_encode(), array functions, etc.
```

Use this when passing data to code that expects a plain array.

---

[← Back to README](../README.md) | [← Philosophy & Design](02-philosophy-and-design.md) | [Next: Filtering & Sorting →](04-filtering-and-sorting.md)
