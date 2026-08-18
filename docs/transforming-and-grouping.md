# Transforming and Grouping

How to reshape a loaded result into the structure a template needs.

Contents:

- [Pulling One Column: column()](#pulling-one-column-column)
- [Keying Rows for Lookups: indexBy()](#keying-rows-for-lookups-indexby)
- [Using Fields as Lookup Keys](#using-fields-as-lookup-keys)
- [Grouping Rows: groupBy()](#grouping-rows-groupby)
- [Reshaping Rows: map()](#reshaping-rows-map)
- [Keys and Values](#keys-and-values)

## Pulling One Column: column()

`column()` returns a flat list of one field from every row, like PHP's
`array_column()` (including the keyed form below):

```php
use Itools\SmartArray\SmartArrayHtml;

$authors = SmartArrayHtml::new([
    ['author_id' => 7,  'name' => 'Alice Munro',   'city' => 'Wingham'],
    ['author_id' => 12, 'name' => 'Bob Gibson',    'city' => 'Toronto'],
    ['author_id' => 15, 'name' => 'Carol Shields', 'city' => 'Winnipeg'],
]);

echo $authors->column('name')->implode(', ');  // Alice Munro, Bob Gibson, Carol Shields
```

With a second argument, another field supplies the keys, which builds
value-to-value maps in one call:

```php
$nameById = $authors->column('name', 'author_id');  // [7 => 'Alice Munro', 12 => 'Bob Gibson', 15 => 'Carol Shields']

echo $nameById->{7};  // Alice Munro
```

## Keying Rows for Lookups: indexBy()

`indexBy()` keys whole rows by a field, turning "loop until you find it" into
a direct lookup:

```php
$authorsById = $authors->indexBy('author_id');

echo $authorsById->{12}->name;  // Bob Gibson
```

Rows keep all their fields; only the keys change. If two rows share the
same key value, the later row wins, so index by unique fields like ids.

## Using Fields as Lookup Keys

The lookup key usually comes from another record, like a book row holding
its author's id. Fields are SmartStrings, so take the plain value with
`value()` and use that as the key:

```php
$book     = SmartArrayHtml::new(['title' => 'Runaway', 'author_id' => 7]);
$authorId = $book->author_id->value();

echo $authorsById->{$authorId}->name;  // Alice Munro
```

## Grouping Rows: groupBy()

Where `indexBy()` keeps one row per key, `groupBy()` collects all of them,
which is the shape section-by-section page layouts want:

```php
use Itools\SmartString\SmartString;

$articles = SmartArrayHtml::new([
    ['title' => 'Fall Fair Sept 20-21', 'category' => 'Events'],
    ['title' => 'New Trail Maps',       'category' => 'Parks'],
    ['title' => 'Winter Markets',       'category' => 'Events'],
]);

foreach ($articles->groupBy('category') as $category => $stories) {
    $category = SmartString::new($category);  // foreach keys come back plain; this makes them encode like fields
    echo "<h3>$category</h3>\n";
    foreach ($stories as $story) {
        echo "   <li>$story->title</li>\n";
    }
}
// <h3>Events</h3>
//    <li>Fall Fair Sept 20-21</li>
//    <li>Winter Markets</li>
// <h3>Parks</h3>
//    <li>New Trail Maps</li>
```

The `SmartString::new()` line is there because foreach hands keys back as
plain values, never encoded (see
[Keys Are Never Encoded](outputting-html.md#keys-are-never-encoded)).
Wrapping the key makes it encode on echo like any field. Skip it when the
keys are trusted values you defined yourself.

## Reshaping Rows: map()

`map()` rebuilds each element with a callback. Like `filter()`, the callback
receives plain PHP values, so you write ordinary PHP inside it:

```php
$labels = $authors->map(fn($a) => "$a[name] ($a[city])");

echo $labels->implode(', ');  // Alice Munro (Wingham), Bob Gibson (Toronto), Carol Shields (Winnipeg)
```

## Keys and Values

Two small helpers round out the set: `keys()` returns the keys as a
collection (they encode on output, which is how you display keys safely),
and `values()` drops the keys and renumbers from 0, which fixes the
key-gaps that `filter()` leaves when you need a clean list:

```php
echo $authorsById->keys()->implode(', ');  // 7, 12, 15

$json = json_encode($authors->filter(fn($a) => $a['city'] !== 'Toronto')->values());  // a JSON array, not an object
```

---

[← Documentation Index](README.md) | [Next: Using SmartArray Without SmartStrings →](without-smartstrings.md)
