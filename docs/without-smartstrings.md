<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Using SmartArray Without SmartStrings

These guides use `SmartArrayHtml`, where fields HTML-encode themselves for
web page output. When the output isn't HTML (a JSON response, a CSV export,
email text, a command-line script), use `SmartArray` instead: same methods,
but fields come back as plain PHP values in their original types.

Contents:

- [Creating Raw Collections](#creating-raw-collections)
- [Fallbacks with the ?? Operator](#fallbacks-with-the--operator)
- [Getting Data Out: json_encode() and toArray()](#getting-data-out-json_encode-and-toarray)
- [Converting Between Modes](#converting-between-modes)
- [Type Hints That Accept Both Modes](#type-hints-that-accept-both-modes)

## Creating Raw Collections

```php
use Itools\SmartArray\SmartArray;

$products = SmartArray::new([
    ['sku' => 'A100', 'title' => 'Widget & Sons Kit', 'price' => 24.99, 'salePrice' => 19.99],
    ['sku' => 'B200', 'title' => 'Gadget Pro',        'price' => 89.99, 'salePrice' => null],
]);

echo $products->first()->title;                      // Widget & Sons Kit (unencoded)
$skuList = $products->column('sku')->implode(', ');  // "A100, B200" (a plain string)
$price   = $products->first()->price;                // 24.99 (a float, the original type)
```

Fields keep their original types, so values drop straight into math,
comparisons, and file formats with no unwrapping.

## Fallbacks with the ?? Operator

Raw fields are plain values, so PHP's `??` operator is the fallback tool
here, and it behaves exactly as it does on plain arrays: it fires on
missing keys and stored NULLs, and never warns:

```php
foreach ($products as $product) {
    $price = $product->salePrice ?? $product->price;  // NULL or missing: use the regular price
}
```

With database results, `??` is really for stored NULLs like the sale price
above: every selected column exists on every row, so a truly missing key
means a typo, and typos on result rows warn on their own. Missing keys as a
normal case only come up in arrays you assemble yourself.

The `??` operator doesn't fire on stored `""`, because an empty string is a
stored value. On the HTML side, use `or()` instead; it covers `""` and keeps
fallbacks encoded (see [Displaying Fields](displaying-fields.md)).

In hand-built arrays, stick with `??` rather than a truthiness check: a
key that doesn't exist at all comes back as a placeholder object so
chains don't crash, and objects are always truthy, so `if ($product->discount)`
passes on a missing key; `$product->discount ?? 0` falls back correctly.

## Getting Data Out: json_encode() and toArray()

Collections plug into `json_encode()` directly and always serialize the
original values, with nested rows as arrays:

```php
header('Content-Type: application/json');
echo json_encode($products->column('sku'));  // ["A100","B200"]
```

For everything else (CSV writers, sessions, code that expects arrays),
`toArray()` returns a plain nested array with the original values:

```php
$rows = $products->toArray();
```

That call is also the performance escape hatch: for report loops that read
every field of thousands of rows, convert once and loop the plain array,
and the per-field object work disappears.

## Converting Between Modes

Convert with `asHtml()` and `asRaw()`. Converting returns a collection in
the other mode and leaves the original unchanged; if the collection is
already in the requested mode, you get the same object back:

```php
foreach ($products->asHtml() as $product) {
    echo "<h3>$product->title</h3>\n";
}
// <h3>Widget &​amp; Sons Kit</h3>
// <h3>Gadget Pro</h3>
```

## Type Hints That Accept Both Modes

If you write functions or methods that take collections, note that
`SmartArrayHtml` is not a subclass of `SmartArray`. The two modes
are siblings under a shared base class:

```
SmartBase             interface: every collection type plus SmartNull
├── SmartArrayBase    both collection modes - hint this to accept either
│   ├── SmartArray        fields are plain values
│   └── SmartArrayHtml    fields are SmartStrings
└── SmartNull         returned for missing keys and empty lookups
```

A parameter typed `SmartArray` rejects HTML-mode collections, so type-hint
`SmartArrayBase` when a function should accept either mode:

```php
use Itools\SmartArray\SmartArrayBase;

function countActive(SmartArrayBase $rows): int
{
    return $rows->where('status', 'Active')->count();
}
```

The `SmartBase` interface is the widest hint: it matches both collection
modes plus `SmartNull`, for functions that should accept whatever a
lookup returned. Individual fields aren't part of this tree: HTML-mode
fields are `SmartString` objects with their own class.

---

[← Documentation Index](README.md) | [Next: Common Patterns →](common-patterns.md)
