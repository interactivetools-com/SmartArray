<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Getting Started

SmartArray wraps arrays (usually database rows) in chainable collection
methods, with fields that HTML-encode themselves on output. This page covers
what you need to start using it, from installation to debugging.

Contents:

- [Installation](#installation)
- [Your First SmartArray](#your-first-smartarray)
- [Empty Results and Blank Fields](#empty-results-and-blank-fields)
- [The Mental Model](#the-mental-model)
- [Working with ZenDB and CMS Builder](#working-with-zendb-and-cms-builder)
- [Converting to Plain Arrays](#converting-to-plain-arrays)
- [Debugging](#debugging)

## Installation

**Using CMS Builder or [ZenDB](https://github.com/interactivetools-com/ZenDB)?**
SmartArray is already installed, and query results already arrive as
SmartArrays; skip to [Your First SmartArray](#your-first-smartarray) to see
how they behave.

```bash
composer require itools/smartarray
```

Requires PHP 8.1+ and
[SmartString](https://github.com/interactivetools-com/SmartString) 3.0+ (the
class behind the self-encoding fields; installs automatically as a
dependency).

## Your First SmartArray

Wrap an array of rows with `SmartArrayHtml::new()`, loop it with `foreach`,
and echo fields with property syntax. Every field HTML-encodes itself; you
never call `htmlspecialchars()`:

```php
use Itools\SmartArray\SmartArrayHtml;

$users = SmartArrayHtml::new([
    ['name' => "Jean O'Brien",    'city' => 'Vancouver', 'joined' => '2025-11-05'],
    ['name' => 'Tom & Jerry Inc', 'city' => 'Ottawa',    'joined' => '2024-03-14'],
    ['name' => 'Sam Smith',       'city' => 'Calgary',   'joined' => '2026-01-22'],
]);

foreach ($users as $user) {
    echo "<li>$user->name from $user->city</li>\n";
}
// <li>Jean O&​apos;Brien from Vancouver</li>
// <li>Tom &​amp; Jerry Inc from Ottawa</li>
// <li>Sam Smith from Calgary</li>
```

Nested arrays are wrapped automatically: `$users` is a SmartArrayHtml, and so
is each `$user` row inside it.

You can also pick out single rows without looping:

```php
echo $users->first()->name;  // Jean O&​apos;Brien
echo $users->last()->city;   // Calgary
echo $users->at(1)->name;    // Tom &​amp; Jerry Inc (by position: 0 is first, negatives count from the end)
```

Fields are [SmartString](https://github.com/interactivetools-com/SmartString)
objects, so their formatting methods chain right off the field. PHP even
lets you call them inside double-quoted strings by wrapping the call in
curly braces:

```php
$user = $users->first();
echo "Joined: {$user->joined->dateFormat('M j, Y')}";  // Joined: Nov 5, 2025
```

Every SmartString method is available this way: `textOnly()`, `maxChars()`,
`numberFormat()`, and the rest of the
[SmartString API](https://github.com/interactivetools-com/SmartString).

## Empty Results and Blank Fields

A result can come back empty, and individual fields can be blank. Collections
answer the first with `isEmpty()`; fields answer the second with `or()`, which
fires on NULL and `""`:

```php
$users = SmartArrayHtml::new([
    ['name' => "Jean O'Brien", 'city' => 'Vancouver', 'phone' => '604-555-1234'],
    ['name' => 'Sam Smith',    'city' => 'Calgary',   'phone' => ''],
]);

if ($users->isEmpty()) {
    echo "<p>No users found.</p>";
}

foreach ($users as $user) {
    echo "<li>$user->name - {$user->phone->or('(no phone)')}</li>\n";
}
// <li>Jean O&​apos;Brien - 604-555-1234</li>
// <li>Sam Smith - (no phone)</li>
```

No `else` is needed: a `foreach` over an empty collection runs zero times.

[Displaying Fields](displaying-fields.md) covers both in full, along with the
guards that stop the page when a required record is missing.

## The Mental Model

SmartArray is two classes with the same methods, and one question picks
between them: **what are you outputting?**

| Class            | Use when                                          | Fields return                             |
|------------------|---------------------------------------------------|-------------------------------------------|
| `SmartArrayHtml` | Output is a web page (the common case)            | SmartStrings that HTML-encode when echoed |
| `SmartArray`     | Output is anything else: JSON, CSV, email, CLI    | Plain PHP values (`string`, `int`, ...)   |

That's the whole decision. Collection methods behave identically in both
classes: callbacks, matching, and sorting always work on the original
unencoded values, so filtering, reports, and calculations work fine in
HTML mode:

```php
$local = $users->where('city', 'Vancouver')->sortBy('name');  // logic uses original values

foreach ($local as $user) {
    echo "<li>$user->name</li>\n";  // <li>Jean O&​apos;Brien</li> (output still auto-encodes)
}
```

This page and the rest of these guides use `SmartArrayHtml` throughout. When
your output isn't HTML, `SmartArray` works the same way with plain values;
see [Using SmartArray Without SmartStrings](without-smartstrings.md).

## Working with ZenDB and CMS Builder

With [ZenDB](https://github.com/interactivetools-com/ZenDB) and CMS Builder,
query results arrive as SmartArrays in HTML mode, so you may never call
`SmartArrayHtml::new()` at all:

```php
use Itools\ZenDB\DB;

$users = DB::select('users', ['status' => 'Active']);

foreach ($users as $user) {
    echo "$user->name from $user->city<br>\n";  // every field auto-encodes
}
```

## Converting to Plain Arrays

The `toArray()` method returns a plain nested PHP array with the original
values, in both modes. Nothing is altered by wrapping, so the round trip is
lossless:

```php
$plain = $users->toArray();
// [
//     ['name' => "Jean O'Brien",    'city' => 'Vancouver', 'joined' => '2025-11-05'],
//     ['name' => 'Tom & Jerry Inc', 'city' => 'Ottawa',    'joined' => '2024-03-14'],
//     ['name' => 'Sam Smith',       'city' => 'Calgary',   'joined' => '2026-01-22'],
// ]
```

On single fields in HTML mode, `value()` returns the original value in its
original type: `$user->name->value()`.

## Debugging

Call `debug()` on any SmartArray to see its contents, which mode it's in,
and (for query results) the mysqli metadata. It prints readable output in
the browser and plain text on the command line:

```php
$users->debug();  // contents, current mode, and query metadata
```

Plain `print_r($users)` works too and shows just the element data; the class
name in its output tells you the mode. Unlike `debug()`, its output isn't
`<xmp>`-wrapped, so in a browser it's raw and unformatted - prefer `debug()`.

---

[← Documentation Index](README.md) | [Next: Displaying Fields →](displaying-fields.md)
