<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Troubleshooting

Common error messages and how to fix them, plus the gotchas that don't
produce an error at all. Headings quote the message or symptom so you can
find them by search.

Contents:

- [empty() and if() checks on fields don't work as expected](#empty-and-if-checks-on-fields-dont-work-as-expected)
- [A === null check never matches](#a--null-check-never-matches)
- [Warning: Can't convert SmartArrayHtml to string](#warning-cant-convert-smartarrayhtml-to-string)
- [Warning: some_field is undefined in listings.php:12](#warning-some_field-is-undefined-in-listingsphp12)
- [Casting with (array) returns internal object data](#casting-with-array-returns-internal-object-data)
- [json_encode() returns an object instead of an array](#json_encode-returns-an-object-instead-of-an-array)
- [A lookup using a field as the key renders blank](#a-lookup-using-a-field-as-the-key-renders-blank)

### empty() and if() checks on fields don't work as expected

In HTML mode a field is an object wrapping your value, and PHP's `empty()`
answers by object rules, not by what's stored. Missing values pass, but a
stored `""` or `0` is an object, and objects are always truthy:

```php
use Itools\SmartArray\SmartArrayHtml;

$user = SmartArrayHtml::new([['name' => 'Jean', 'nickname' => '', 'logins' => 0, 'phone' => null]])->first();

empty($user->phone);     // true  - stored NULL reads as missing
empty($user->missing);   // true  - keys that don't exist do too
empty($user->nickname);  // false - "" is stored, so the check sees an object, and objects are truthy
empty($user->logins);    // false - same for 0
```

Ask the field instead; the answers are about the value:

```php
$user->nickname->isEmpty();  // true - "", NULL, 0, and "0" all count as empty
$user->phone->isMissing();   // true - NULL or "" (zero counts as present)
```

The same goes for comparisons: `$user->logins > 5` compares the object,
not the number. Unwrap with `value()` (or `int()`, `float()`) first.
SmartString's
[troubleshooting page](https://github.com/interactivetools-com/SmartString/blob/main/docs/troubleshooting.md)
covers every variation of this.

Raw mode has one version of the same trap: existing fields are plain
values, but a missing or misspelled field returns a `SmartNull`
placeholder, which is an object and therefore truthy, so a bare
`if ($user->is_admin)` runs when the field doesn't exist. `isset()` and
`??` treat missing keys as missing, so `$user->is_admin ?? 0` is a safe
guard. Comparing without one isn't: `$user->is_admin == 1` is true for a
missing field too, because PHP casts the placeholder object to `1`.

### A === null check never matches

In HTML mode, fields are always objects: a stored NULL comes back as a
SmartString wrapping null, and a missing key comes back as a `SmartNull`
placeholder. Neither is PHP's null:

```php
$user->phone === null;           // false - the field is an object
$user->phone->value() === null;  // true  - the original value is null
$user->phone->isMissing();       // true  - NULL or "" (usually what you meant)
```

In raw mode (`SmartArray`), stored values come back as plain PHP types, so
a stored NULL there really is null and `===` works directly.

### Warning: Can't convert SmartArrayHtml to string

Something echoed a whole collection or row where a single value belongs.
The two usual causes: echoing the collection itself, or calling a method
inside a double-quoted string without curly braces:

```php
$cities = SmartArrayHtml::new(['Vancouver', 'Ottawa']);

echo "Cities: $cities";                   // WRONG - prints the warning above and nothing for the array
echo "Cities: {$cities->implode(', ')}";  // RIGHT - Cities: Vancouver, Ottawa
```

### Warning: some_field is undefined in listings.php:12

A row from your results was asked for a column it doesn't have. Usually
one of two things: the column name is misspelled, or the query didn't
select that column, so check the spelling first and the SELECT list
second. For a key that legitimately may not exist, read it with `??`,
which never warns: `$row->some_field ?? ''`. Two gotchas ride along with
`??`: it doesn't fire on a stored `""` (an empty string is a value), and
the fallback skips HTML encoding, so keep fallbacks plain text.

Only rows inside a result set warn. Lookups on keyed maps (from
`indexBy()` or `column()`) and standalone arrays render blank silently,
because a miss there is a normal no-match.

### Casting with (array) returns internal object data

PHP's `(array)` cast exposes an object's internal properties (the engine
offers no way to override it), so the result is mangled keys instead of
your data. Use `toArray()`:

```php
$plain = (array)$cities;      // WRONG - internal properties with unusable keys
$plain = $cities->toArray();  // RIGHT - plain array, original values

$flat = [...$cities];         // spread works for flat lists but keeps element mode: SmartStrings in HTML mode
```

### json_encode() returns an object instead of an array

Filtering keeps original keys, like PHP's `array_filter()`, and JSON
turns any array with key gaps into an object. Chain `values()` to
renumber first:

```php
header('Content-Type: application/json');
$json = json_encode($cities->filter(fn($c) => $c !== 'Vancouver'));            // {"1":"Ottawa"}
$json = json_encode($cities->filter(fn($c) => $c !== 'Vancouver')->values());  // ["Ottawa"]
```

### A lookup using a field as the key renders blank

Inside braces, a field converts to its HTML-encoded text, so a text key
with an apostrophe or ampersand encodes into something that doesn't match.
Pass the original with `value()`:

```php
$facts = SmartArrayHtml::new(["St. John's" => 'Oldest city in North America']);
$city  = SmartArrayHtml::new(['city' => "St. John's"])->city;

echo $facts->{$city};           // WRONG - looks up "St. John&​apos;s", renders blank
echo $facts->{$city->value()};  // RIGHT - Oldest city in North America
```

Numeric ids aren't affected (digits encode to themselves), which is why id
lookups work either way.

---

[← Documentation Index](README.md) | [← Prev: Method Reference](method-reference.md) | [Next: AI Reference →](ai-reference.md)
