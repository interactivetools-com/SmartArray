<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Displaying Fields

How to show fields in templates and handle data that isn't there: fallbacks
for blank values, "no results" messages, and required records that stop the
page.

## Reading Fields

A SmartArray is an object, not an array, so you read fields with the arrow
operator (`->`) instead of square brackets:

```php
use Itools\SmartArray\SmartArrayHtml;

$users = SmartArrayHtml::new([
    ['name' => "Jean O'Brien",    'city' => 'Vancouver', 'phone' => '604-555-0132'],
    ['name' => 'Tom & Jerry Inc', 'city' => 'Ottawa',    'phone' => null],
]);

$user = $users->first();

echo $user->name;  // Jean O&​apos;Brien (with a plain array this would be $user['name'])
```

The arrow syntax pays off in double-quoted strings: fields interpolate
directly, with no braces or quotes needed:

```php
// with a plain PHP array, every field needs braces and quotes
$plain = $user->toArray();
echo "<p>Contact {$plain['name']} in {$plain['city']} at {$plain['phone']}</p>";

// with a SmartArray, it's just the arrow
echo "<p>Contact $user->name in $user->city at $user->phone</p>";
// <p>Contact Jean O&​apos;Brien in Vancouver at 604-555-0132</p>
```

You can even run whole method chains inside a string, something plain PHP
values can't do. Wrap the expression in curly braces and it works:
`"Phone: {$user->phone->or('(no phone)')}<br>\n"`.

## Fallbacks with or()

Sometimes real data has missing values: a phone number nobody entered, a
nickname left blank. SmartArray calls a value **missing** when it's blank
(`""`) or NULL in the database, and `or()` shows a default in its place.
The result stays a SmartString, so even a fallback that came from data
encodes on output:

```php
$user = SmartArrayHtml::new(['name' => 'Jean', 'phone' => null, 'nickname' => '']);

echo $user->phone->or('(no phone)');    // (no phone) - phone is NULL
echo $user->nickname->or('Guest');      // Guest - nickname is blank ("")
echo $user->nickname->or($user->name);  // Jean (a data fallback encodes on output)
```

## Showing a "No Results" Message

Every list template needs the empty branch. Collections answer with
`isEmpty()`, `isNotEmpty()`, and `count()`:

```php
$matches = $users->where('city', 'Ottawa');

if ($matches->isEmpty()) {
    echo '<p>No users found.</p>';
} else {
    echo "<p>Found {$matches->count()} user(s):</p>\n";
    foreach ($matches as $user) {
        echo "<li>$user->name</li>\n";
    }
}
// <p>Found 1 user(s):</p>
// <li>Tom &​amp; Jerry Inc</li>
```

## When Data Is Missing

An empty result displays as nothing. You can chain and echo without
checking first:

```php
$users = DB::select('users');  // suppose the table is empty

echo $users->first()->name;    // prints nothing
```

Typos are another story. Misspell a column on a row from your results and
you get a warning naming the key and your file and line, so typos surface
the first time the page runs:

```php
$user = DB::select('users')->first();  // this time the table has rows

echo $user->nmae;
// Warning: nmae is undefined in listings.php:12
```

## Requiring Data: The Guards

When a record must exist, like an article looked up from the URL, guard
methods stop the page instead of rendering blanks. They fire when there's
no matching record (an empty result), and otherwise hand the result back
unchanged, so they chain inline:

```php
$articles = SmartArrayHtml::new([
    ['num' => 7, 'title' => 'Fall Fair Sept 20-21'],
]);
$num = (int)($_GET['num'] ?? 0);

$article = $articles->where('num', $num)->first()->or404('Article not found');

// past this line, $article is a real row
echo "<h1>$article->title</h1>";  // with ?num=7 this prints: <h1>Fall Fair Sept 20-21</h1>
```

| Method             | When there's no record                                                                               |
|--------------------|------------------------------------------------------------------------------------------------------|
| `or404($text)`     | Sends a 404 page with `$text` and stops (default: "The requested URL was not found on this server.") |
| `orDie($text)`     | Prints `$text` and stops                                                                             |
| `orThrow($text)`   | Throws `RuntimeException` with `$text`                                                               |
| `orRedirect($url)` | Sends a 302 redirect to `$url` and stops                                                             |

Messages HTML-encode automatically, so interpolating user input into them is
safe: `->orDie("No results for '$keyword'")`.

## Checking a Single Field

The guards work on single fields too:
[SmartString](https://github.com/interactivetools-com/SmartString) has the
same four methods, and on a field they fire when the value is missing
(blank or NULL) rather than when a record is missing:

```php
$user->phone->orDie('No phone number on file.');  // phone is NULL, so this stops the page
```

To check for a missing value without stopping the page, use `isMissing()`:

```php
if ($user->phone->isMissing()) {
    echo '<p>No phone on file</p>';
}
```

The full family (`isEmpty()`, `ifNull()`, `ifEquals()`, and more) is in
SmartString's
[Conditionals and Error Checking](https://github.com/interactivetools-com/SmartString/blob/main/docs/conditionals-and-error-checking.md)
page.

## Keys Property Syntax Can't Type

You won't need this on day one, but it comes up: some queries return keys
that aren't valid property names, most often table-prefixed columns from
SQL joins (`users.id`). Wrap those in braces:

```php
$row = SmartArrayHtml::new(['users.id' => 42, 'first-name' => 'Jean', 0 => 'zero']);

echo $row->{'users.id'};    // 42 (dotted keys, e.g. from SQL joins)
echo $row->{'first-name'};  // Jean (dashes)
echo $row->{0};             // zero (numeric keys)
```

SmartArrays are writable too, with the same syntax in reverse:
`$user->status = 'Active'`. Template code rarely needs it; see
[Writing Values](method-reference.md#writing-values) when you do.

---

[← Documentation Index](README.md) | [Next: Outputting HTML →](outputting-html.md)
