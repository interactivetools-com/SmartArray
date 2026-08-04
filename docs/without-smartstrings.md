<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Using SmartArray Without SmartStrings

These guides use `SmartArrayHtml`, where fields HTML-encode themselves for
web page output. When the output isn't HTML (a JSON response, a CSV export,
email text, a command-line script), use `SmartArray` instead: same methods,
but fields come back as plain PHP values in their original types.

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

## Fallbacks with ??

Raw fields are plain values, so PHP's `??` operator is the fallback tool
here, and it behaves exactly as it does on plain arrays: it fires on
missing keys and stored NULLs, and never warns:

```php
foreach ($products as $product) {
    $price = $product->salePrice ?? $product->price;  // NULL or missing: use the regular price
}
```

One thing to know: `??` doesn't fire on stored `""`, because an empty
string is a stored value. On the HTML side, use `or()` instead; it covers
`""` and keeps fallbacks encoded (see
[Displaying Fields](displaying-fields.md)).

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

If you write functions or methods that take collections, one thing to
know: `SmartArrayHtml` is not a subclass of `SmartArray`. The two modes
are siblings under a shared base class:

```
SmartBase             interface: anything the library hands back
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

The `SmartBase` interface is the widest hint: it matches anything the
library hands back, including `SmartNull`, for functions that should take
any Smart value at all.

---

[← Documentation Index](README.md) | [Next: Common Patterns →](common-patterns.md)
