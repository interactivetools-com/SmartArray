# Philosophy and Design

SmartArray is built around one idea: the most readable way to work with collections should also be the safest way to output them.

## The Collection Problem

Working with PHP arrays for HTML output requires a lot of boilerplate. Consider a common task: filter active users, sort by name, and output their names HTML-encoded.

```php
// Filter active users
$active = array_filter($records, fn($row) => $row['status'] === 'active');

// Sort by name
usort($active, fn($a, $b) => strcmp($a['name'], $b['name']));

// Output encoded names
foreach ($active as $row) {
    echo '<li>' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</li>' . "\n";
}
```

The problems compound at scale:
- `array_filter`, `usort`, `array_column`, and `htmlspecialchars` are verbose and don't chain
- Every `htmlspecialchars` call is a manual responsibility — easy to skip on one output line
- The intent of the operation is buried under syntax and flags
- Each step recreates the array rather than building on a shared abstraction

## Fluent Pipelines

The same operation as a SmartArray chain:

```php
SmartArray::new($records)
    ->where(['status' => 'active'])
    ->sortBy('name')
    ->asHtml()
    ->pluck('name')
    ->sprintf("<li>{value}</li>")
    ->implode("\n");
```

The design principle behind this: every transformation method returns a new SmartArray. Nothing is modified in place. You can freely chain without worrying about mutating the original, and you can branch from any intermediate result.

## Two Modes: Processing and Output

**This is the most important architectural concept in SmartArray.**

Two concrete classes exist:

| Class | Use for | Scalar values return |
|-------|---------|----------------------|
| `SmartArray` | Data processing, APIs, internal logic | Plain PHP types: `string`, `int`, `float`, `bool`, `null` |
| `SmartArrayHtml` | HTML templates, output loops | `SmartString` objects that auto-encode on echo |

Use raw mode when you are computing, building queries, or processing data. Switch to HTML mode before you loop for output:

```php
// Process in raw mode -- values are plain PHP types
$ids = SmartArray::new($records)
    ->where(['active' => 1])
    ->pluck('id')
    ->implode(','); // "1,2,3"

// Switch to HTML mode before output -- values auto-encode
$display = SmartArray::new($records)->asHtml();
foreach ($display as $row) {
    echo "<li>$row->name</li>"; // auto HTML-encoded, no htmlspecialchars() needed
}
```

`asHtml()` and `asRaw()` are lazy: if you call them on an object that is already the correct type, they return the same object. Calling them repeatedly has no cost.

**The rule: process data in raw mode, switch to HTML mode before output.**

## Fail Quietly: The SmartNull Pattern

When you access a key that does not exist, SmartArray returns a `SmartNull` object instead of `null` or throwing an error. `SmartNull` is designed to be safe to chain:

- Every SmartArray method called on it returns an empty result
- Every SmartString method called on it returns `null` or an empty string
- Iterating it with `foreach` does nothing
- Echoing it outputs an empty string

```php
// Key doesn't exist -- no error, returns SmartNull
$name = $users->get('nonexistent'); // SmartNull

// Chain on SmartNull -- still no error
$value = $users->get('nonexistent')->toArray(); // []
echo $users->get('nonexistent');                // ""

// Deep chains on missing nested data are safe too
echo $row->address->city; // "" -- no error even if address doesn't exist
```

One exception: you cannot assign to a `SmartNull` via array syntax. `$arr['missing'] = 'value'` on a `SmartNull` throws `RuntimeException`. Use `$arr->set('key', 'value')` on the actual SmartArray instead.

## What SmartArray Guarantees

1. Missing keys always return `SmartNull`, never `null` or a fatal error
2. Transformations always return new instances — the original is never modified
3. `SmartArrayHtml` values HTML-encode automatically on output — no `htmlspecialchars()` needed
4. `sprintf()` always returns a raw `SmartArray`, even when called on `SmartArrayHtml` — pre-formatted HTML must not be re-encoded
5. Position metadata (`isFirst()`, `isLast()`, `position()`) is accurate for nested arrays at the moment of construction

## What SmartArray Does Not Do

1. Does not validate or sanitize data — it only encodes for HTML output
2. Does not make database queries — use ZenDB for that, which returns SmartArray results natively
3. Does not update position metadata after elements are added post-construction
4. Does not protect against double-encoding — if you mix raw strings with SmartString output, encoding is your responsibility

---

[← Back to README](../README.md) | [← Getting Started](01-quickstart.md) | [Next: Accessing Values →](03-accessing-values.md)
