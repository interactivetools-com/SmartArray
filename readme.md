# SmartArray: PHP Arrays with Superpowers

SmartArray enhances PHP arrays to work as both traditional arrays and chainable objects.
You get all the familiar array features you know, plus powerful new methods for filtering,
mapping, grouping, and handling nested data - making array operations simpler while preserving
normal array syntax.

## Table of Contents

* [Quick Start](#quick-start)
* [Creating Grid Layouts](#creating-grid-layouts)
* [Debugging and Help](#debugging-and-help)
* [Method Reference](#method-reference)
* [Questions?](#questions)

## Quick Start

Install via Composer:

```bash
composer require itools/smartarray
```

Include the Composer autoloader and SmartArray class:

```php
<?php
require 'vendor/autoload.php';
use Itools\SmartString\SmartString;
```

Convert an array to a SmartArray:

```php
$records = [
    ['id' => 10, 'name' => "John O'Connor",  'city' => 'New York'],
    ['id' => 15, 'name' => 'Xena "X" Smith', 'city' => 'Los Angeles'],
    ['id' => 20, 'name' => 'Tom & Jerry',    'city' => 'Vancouver'],
];

$users = SmartArray::new($records); // Convert to SmartArray (values get converted to SmartStrings)

// Foreach over a SmartArray just like a regular array 
foreach ($users as $user) {
    echo "Name: {$user['name']}, ";  // use regular array syntax
    echo "City: $user->city\n";      // or cleaner object syntax
}

// Values are automatically HTML-encoded in string contexts to prevent XSS (see SmartString docs more details)
echo $users->first()->name; // Output: John O&apos;Connor
    
// Use chainable methods to transform data
$userIdAsCSV = $users->pluck('id')->join(', '); // Output: "10, 15, 20"

// Easily convert back to arrays and original values
$usersArray = $users->toArray(); // Convert back to a regular PHP array and values

// Convert SmartStrings back to original values
$userId = $users->first()->id->value(); // Returns 10 as an integer
```    

See the [Method Reference](#method-reference) for more information on available methods.

## Creating Grid Layouts

SmartArray makes it easy to create grid layouts. You can easily show content
for the first and last elements, every N columns, and more.

Here's an old-school example of how to create a table with `SmartArray` methods:
`isFirst`, `isMultipleOf`, and `isLast`:

```php
$records = [
    ['id' => 10, 'name' => "John O'Connor",  'city' => 'New York'],
    ['id' => 15, 'name' => 'Xena "X" Smith', 'city' => 'Los Angeles'],
    ['id' => 20, 'name' => 'Tom & Jerry',    'city' => 'Vancouver'],
];
$users = SmartArray::new($records);

foreach ($users as $user) {
    if ($user->isFirst())       { echo "<table border='1' cellpadding='10' style='text-align: center'>\n<tr>\n"; }
    echo "<td><h1>$user->name</h1>$user->city</td>\n"; // values are automatically html encoded by SmartString
    if ($user->isMultipleOf(2)) { echo "</tr>\n<tr>\n"; }
    if ($user->isLast())        { echo "</tr>\n</table>\n"; }
}
```

And here's another way to do it using the `chunk` method which splits the array into smaller arrays:

```php
if ($users->isNotEmpty()) {
    echo "<table border='1' cellpadding='10' style='text-align: center'>\n";
    foreach ($users->chunk(2) as $row) {
        echo "<tr>\n";
        foreach ($row as $user) {
            echo "<td><h1>$user->name</h1>$user->city</td>\n"; // values are automatically html encoded by SmartString
        }
        echo "</tr>\n";
    }
    echo "</table>\n";
}
```

Both approaches create the following code:

```html

<table border='1' cellpadding='10' style='text-align: center'>
    <tr>
        <td><h1>John O&apos;Connor</h1>New York</td>
        <td><h1>Xena &quot;X&quot; Smith</h1>Los Angeles</td>
    </tr>
    <tr>
        <td><h1>Tom &amp; Jerry</h1>Vancouver</td>
    </tr>
</table>
```

See the [Method Reference](#method-reference) for more information on available methods.

## Debugging and Help

You can call `print_r()` on a `SmartArray` to show a compact debug view of the object:

```php
$users = SmartArray::new([
    ['id' => 10, 'name' => "John O'Connor",  'city' => 'New York'],
    ['id' => 20, 'name' => 'Tom & Jerry',    'city' => 'Vancouver'],
]);
print_r($users);

// Outputs: 
Itools\SmartArray\SmartArray Object
(
    [__DEBUG_INFO__] => // SmartArray debug view, call $var->help() for inline help

    SmartArray([        // Metadata: isFirst: false, isLast: false, position: 0
        SmartArray([    // Metadata: isFirst: true,  isLast: false, position: 1
            id   => SmartString(10),
            name => SmartString("John O'Connor"),
            city => SmartString("New York"),
        ]),
        SmartArray([    // Metadata: isFirst: false, isLast: true,  position: 2
            id   => SmartString(20),
            name => SmartString("Tom & Jerry"),
            city => SmartString("Vancouver"),
        ]),
    ]),
)
```

Or call the `->help()` method on any `SmartArray` object to see a list of available methods and examples:

```php
$users->help();
```

Outputs a list of available methods and examples:

```text
SmartArray: An Enhanced ArrayObject with Fluent Interface
========================================================
SmartArray extends ArrayObject, offering additional features and a chainable interface.
It supports both flat and nested arrays, automatically converting elements to SmartString or SmartArray.

Creating SmartArrays:
---------------------
$ids   = new SmartArray([1, 2, 3]);
$user  = new SmartArray(['name' => 'John', 'age' => 30]);
$users = new SmartArray(DB::select('users'));  // Nested SmartArray of SmartStrings

Continues...
````

## Method Reference

|          **Basic Usage** |                                                                                                                                                                             |
|-------------------------:|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
|  SmartArray::new($array) | Creates a new `SmartArray` from a regular PHP array. All nested arrays and values are converted to `SmartArray` and `SmartString` objects                                   |      
|                toArray() | Converts a `SmartArray` back to a standard PHP array, converting all nested `SmartArray` and `SmartString` objects back to their original values                            |       
|    **Array Information** |                                                                                                                                                                             |
|                  count() | Returns the number of elements                                                                                                                                              |                                                                                                                                                             
|                isEmpty() | Returns true if `SmartArray` contains **no** elements                                                                                                                       |
|             isNotEmpty() | Returns true if `SmartArray` contains **any** elements                                                                                                                      |
|                isFirst() | Returns true if this is the **first** element in its parent `SmartArray`                                                                                                    |
|                 isLast() | Returns true if this is the **last** element in its parent `SmartArray`                                                                                                     |
|               position() | Gets this element's position in its parent `SmartArray` (starting from 1)                                                                                                   |
|     isMultipleOf($value) | Returns true if this element's position is a multiple of $value (useful for grids)                                                                                          |
|         **Value Access** |                                                                                                                                                                             |
|                   [$key] | Get a value using array syntax, e.g., `$array['key']`                                                                                                                       |
|                    ->key | Get a value using object syntax, e.g., `$array->key`                                                                                                                        |
|                get($key) | Alternative method to get a value, e.g., `$array->get($key)`                                                                                                                |
|                  first() | Get the first element                                                                                                                                                       |
|                   last() | Get the last element                                                                                                                                                        |
|              nth($index) | Get element by position, starting at 0, e.g., `->nth(0)` first, `->nth(1)` second, `->nth(-1)` last                                                                         |
| **Array Transformation** |                                                                                                                                                                             |
|                   keys() | Get just the keys as a new `SmartArray`, e.g., `['id', 'name', 'email']`                                                                                                    |
|                 values() | Get just the values as a new `SmartArray`, discarding the keys                                                                                                              |
|                 unique() | Get unique values (removes duplicates, preserves keys)                                                                                                                      
|         indexBy($column) | For nested arrays, create a new `SmartArray` using a column as the key, e.g., `indexBy('id')`. Duplicates use latest value.                                                 |
|         groupBy($column) | Like `indexBy()` but returns values as a `SmartArray` to preserve duplicates.  e.g., `$usersByCity = $users->groupBy('city')`                                               |
|         join($separator) | Combine values into a `SmartString`, e.g., `$users->pluck('id')->join(', ')` creates `"23, 51, 72"`                                                                         |
|           map($callback) | Transform each element using a callback function, returning a new `SmartArray`.  The callback function receives the original value or array as an argument for each element |
|              pluck($key) | Extract one column from a nested `SmartArray`, e.g., `$users->pluck('name')` returns a `SmartArray` with all names                                                          |
|             chunk($size) | Returns a `SmartArray` of smaller `SmartArray`s of the specified size (for grid layouts)                                                                                    |
|   **Debugging and Help** |                                                                                                                                                                             |
|                   help() | Displays help information about available methods                                                                                                                           |

## Questions?

This library was developed for CMS Builder, post a message in our "CMS Builder" forum here:
[https://www.interactivetools.com/forum/](https://www.interactivetools.com/forum/)
