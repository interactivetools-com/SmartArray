# Getting Started

This guide walks you through installing SmartArray, wrapping your first array, accessing values safely, and building an HTML-safe output pipeline.

## Requirements

- PHP 8.1 or higher
- `ext-mbstring` extension
- Composer

## Installation

```bash
composer require itools/smartarray
```

Then add the use statements at the top of your file:

```php
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
```

## Your First SmartArray

Wrap any array with `SmartArray::new()`:

```php
$records = [
    ['id' => 1, 'name' => "Jean O'Brien",    'city' => 'Ottawa',      'status' => 'active'],
    ['id' => 2, 'name' => 'Tom & Jerry Inc', 'city' => 'Vancouver',   'status' => 'active'],
    ['id' => 3, 'name' => 'Eve <admin>',     'city' => 'Los Angeles', 'status' => 'inactive'],
];

$users = SmartArray::new($records);
```

`$users` is now a SmartArray where each nested array is also a SmartArray. You can chain methods freely. If you access a key that does not exist, you get a `SmartNull` — a safe chainable placeholder — instead of `null` or a fatal error.

## Your First Value Access

Use property syntax to access fields by name:

```php
// Access by property name -- returns raw string in raw mode
$name = $users->first()->name; // "Jean O'Brien"
```

Use `get()` for numeric keys or keys with special characters:

```php
$name = $users->get(0)->name; // "Jean O'Brien"
```

Navigate the collection with `first()`, `last()`, and `nth()`:

```php
$first  = $users->first();  // first row SmartArray
$last   = $users->last();   // last row SmartArray
$second = $users->nth(1);   // zero-based, so index 1 = second row
```

If you access a key that does not exist, you get `SmartNull` — a safe chainable null placeholder — instead of an error:

```php
$missing = $users->get('nonexistent'); // SmartNull, no error
echo $missing;                         // "" (empty string)
```

## Your First Filter

Use `where()` to filter rows by matching field values:

```php
// Keep only active users
$activeUsers = $users->where(['status' => 'active']);
```

`where()` compares using loose equality, which makes it tolerant of mixed types from form data and databases — for example, `'1'` matches `1`.

Note that `filter()` with no arguments removes falsey values like `0`, `""`, and `null`. That is useful for flat arrays, not for record filtering. For record sets, use `where()`.

## Your First HTML Output

This is the pivot point of the whole library. Before looping for output, call `asHtml()`:

```php
$safeUsers = $users->asHtml();

foreach ($safeUsers as $user) {
    echo "<li>$user->name -- $user->city</li>\n";
}
// Output: <li>Jean O&apos;Brien -- Ottawa</li>
//         <li>Tom &amp; Jerry Inc -- Vancouver</li>
//         <li>Eve &lt;admin&gt; -- Los Angeles</li>
```

Once you call `asHtml()`, every scalar value returned is a `SmartString` that HTML-encodes automatically on echo. You never need to call `htmlspecialchars()` by hand.

The original `$users` object is not affected. `asHtml()` returns a new `SmartArrayHtml` instance, leaving `$users` in raw mode.

## Your First Chain

Combine filtering, sorting, mode switching, and output in a single pipeline:

```php
// Filter active users, sort by name, output as HTML list items
$html = SmartArray::new($records)
    ->where(['status' => 'active'])
    ->sortBy('name')
    ->asHtml()
    ->pluck('name')
    ->sprintf("<li>{value}</li>")
    ->implode("\n");

echo "<ul>\n$html\n</ul>";
// Output:
// <ul>
// <li>Jean O&apos;Brien</li>
// <li>Tom &amp; Jerry Inc</li>
// </ul>
```

Each method returns a new SmartArray. Nothing is modified in place.

## Getting Help at Runtime

Three tools are available for inspecting SmartArray objects during development.

**`print_r()` shows the structure with a built-in hint:**

```php
print_r($users);
// SmartArray Object
// (
//     [__help__] => Call ->help() for usage examples and documentation, or ->debug() to view metadata
//     [0] => SmartArray Object
//         (
//             [id]     => 1
//             [name]   => Jean O'Brien
//             [city]   => Ottawa
//             [status] => active
//         )
//     [1] => SmartArray Object
//         ...
// )
```

**`->help()` lists all available methods with descriptions:**

```php
$users->help();
// Prints a formatted list of all methods, grouped by category,
// with descriptions and usage examples inline.
```

**`->debug()` shows full internal detail:**

```php
$users->debug();
// Prints internal properties including position metadata (isFirst, isLast, position)
// and mysqli metadata (affected_rows, insert_id) when working with database results.
```

---

[← Back to README](../README.md) | [Next: Philosophy & Design →](02-philosophy-and-design.md)
