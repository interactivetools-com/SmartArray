# Output and HTML

This page covers how SmartArray produces HTML-safe output — switching between processing and output modes, joining values, applying format strings, and using position metadata for layout control.

## Choosing Your Mode

SmartArray has two modes: raw (`SmartArray`) for data processing, and HTML (`SmartArrayHtml`) for output. In raw mode, accessing a value returns a plain PHP string. In HTML mode, accessing a value returns a `SmartString` that auto-encodes on echo.

**Call `asHtml()` once before your foreach loop or output pipeline, not inside it.** Switching modes inside a loop creates unnecessary object allocations and makes the conversion point hard to find when reading code.

```php
// Correct: convert once, then iterate
$safe = $users->asHtml();
foreach ($safe as $user) {
    echo "<li>$user->name</li>\n"; // encoded automatically
}

// Wrong: converting inside the loop
foreach ($users as $user) {
    echo "<li>" . htmlspecialchars($user->name) . "</li>\n"; // manual, error-prone
}
```

## Converting Between Modes

### Converting to HTML mode — `asHtml()`

Returns the same array in HTML mode. After this, all scalar values come back as `SmartString` objects that auto-encode when echoed or cast to string. The conversion is lazy: calling `asHtml()` on an array that is already in HTML mode returns the same object unchanged.

```php
$safe = $users->asHtml();
echo $safe->first()->name; // Jean O&apos;Brien (encoded automatically)
```

Transformations preserve mode: `$safe->where(['status' => 'active'])` returns a `SmartArrayHtml`.

### Converting to raw mode — `asRaw()`

Returns the array in raw mode. Useful when you have a `SmartArrayHtml` and need to do data processing on it without encoding side effects. Lazy: returns the same object if already raw.

```php
$raw = $safe->asRaw();
echo $raw->first()->name; // Jean O'Brien (plain string, no encoding)
```

## Joining Values — `implode()`

### Joining a flat array to a string — `implode($separator)`

Joins all values with a separator. On `SmartArray` (raw mode), returns a plain string. On `SmartArrayHtml`, returns a `SmartString`.

```php
// Raw mode: returns a plain string
$csv = $users->pluck('name')->implode(', ');
// -> "Jean O'Brien, Tom & Jerry Inc, Eve <admin>"

// HTML mode: returns SmartString with encoded values
$safeList = $users->asHtml()->pluck('name')->implode(', ');
// -> SmartString: "Jean O&apos;Brien, Tom &amp; Jerry Inc, Eve &lt;admin&gt;"
```

`implode()` only works on flat arrays. Call it after `pluck()`, `keys()`, `values()`, or `sprintf()` — not directly on a nested result set.


## Formatting with `sprintf()`

### Applying a format string to each element — `sprintf($format)`

Applies a format string to each element and returns a new `SmartArray` containing the formatted strings. Supports these placeholder styles:

| Placeholder | Meaning |
|-------------|---------|
| `{value}` | The element value (alias for `%1$s`); HTML-encoded when called on `SmartArrayHtml` |
| `{key}` | The element key (alias for `%2$s`); HTML-encoded when called on `SmartArrayHtml` |
| `%s` | Standard sprintf value placeholder |
| `%1$s` | Positional: value |
| `%2$s` | Positional: key |

```php
// Wrap each name in a list item
$items = $users->asHtml()->pluck('name')->sprintf("<li>{value}</li>");

// Build select options from a [value => label] map
$options = SmartArray::new(['us' => 'United States', 'ca' => 'Canada'])->asHtml()
    ->sprintf("<option value='{key}'>{value}</option>")
    ->implode("\n");
```

**`sprintf()` always returns a raw `SmartArray`, even when called on `SmartArrayHtml`.** The reason: the format string wraps values in HTML tags, and that resulting markup must not be re-encoded. Values from `SmartArrayHtml` are encoded inside the format string, but the result type reverts to raw so the tags themselves are preserved.

```php
$html = $users->asHtml()->pluck('name')->sprintf("<li>{value}</li>");
// $html is SmartArray (raw), not SmartArrayHtml
// Values inside ARE encoded:  <li>Jean O&apos;Brien</li>
// But $html itself is safe to echo or join without further encoding
echo $html->implode("\n"); // returns a plain string, ready to echo
```


## Position Metadata for Layouts

> **Deprecation Notice:** Position methods (`position()`, `isFirst()`, `isLast()`, `isMultipleOf()`) are deprecated and will be removed in a future version. Use standard iteration patterns or `array_chunk()` for grid layouts instead.

Position properties are set at construction time for nested arrays only. The root array (your full result set) has `position()` equal to 0. Rows inside it have `position()` starting at 1.

### Position within the parent — `position()`

Returns the 1-based position of a row within its parent array. Returns 0 for the root array itself.

```php
foreach ($users->asHtml() as $user) {
    echo $user->position() . ": $user->name\n";
}
// 1: Jean O'Brien
// 2: Tom & Jerry Inc
// 3: Eve <admin>
```

### First and last rows — `isFirst()` and `isLast()`

Returns true for the first or last element in the parent array. Useful for opening and closing HTML wrapper elements.

```php
foreach ($users->asHtml() as $user) {
    if ($user->isFirst()) { echo "<ul>\n"; }
    echo "  <li>$user->name</li>\n";
    if ($user->isLast())  { echo "</ul>\n"; }
}
```

### Grid column detection — `isMultipleOf($n)`

Returns true when the current `position()` is a multiple of `$n`. Use this to close and open row wrappers in a grid layout.

```php
// 3-column grid
foreach ($items->asHtml() as $item) {
    if ($item->isFirst())       { echo "<div class='grid'>\n<div class='row'>\n"; }
    echo "  <div class='col'>$item->name</div>\n";
    if ($item->isMultipleOf(3)) { echo "</div>\n<div class='row'>\n"; }
    if ($item->isLast())        { echo "</div>\n</div>\n"; }
}
```

### Fixed-size chunks — `chunk($size)`

> **Deprecation Notice:** `chunk()` is deprecated and will be removed in a future version. Use PHP's `array_chunk()` directly instead.

An alternative approach to grid layouts: split the array into sub-arrays first, then iterate. Lets PHP handle the row math instead of tracking position manually.

```php
// Same 3-column grid using chunk()
echo "<div class='grid'>\n";
foreach ($items->chunk(3)->asHtml() as $row) {
    echo "<div class='row'>\n";
    foreach ($row as $item) {
        echo "  <div class='col'>$item->name</div>\n";
    }
    echo "</div>\n";
}
echo "</div>\n";
```

**`isMultipleOf()` vs `chunk()` — when to use each:**

| Approach | Best when |
|----------|-----------|
| `isMultipleOf($n)` | Template-style PHP with inline conditions |
| `chunk($size)` | You want PHP to handle the row math for you |

Both produce identical HTML output.


## Putting It Together

A complete HTML table with dynamic headers, encoded content, and a "no results" fallback:

```php
$rows = SmartArray::new($records)->where(['status' => 'active'])->asHtml();
?>

<table>
    <?php if ($rows->isNotEmpty()): ?>
    <thead>
        <tr><?= $rows->first()->keys()->sprintf("<th>{value}</th>")->implode() ?></tr>
    </thead>
    <?php endif ?>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr><?= $row->sprintf("<td>{value}</td>")->implode() ?></tr>
        <?php endforeach ?>
        <?php if ($rows->isEmpty()): ?>
        <tr><td colspan="4">No records found.</td></tr>
        <?php endif ?>
    </tbody>
</table>
```

What each step does:

- `where(['status' => 'active'])` — only show active records
- `asHtml()` — all values auto-encode on output
- `$rows->first()->keys()->sprintf("<th>{value}</th>")` — dynamic headers pulled from the first row's keys
- `$row->sprintf("<td>{value}</td>")` — each row's values wrapped in `<td>` tags
- Values like `"Jean O'Brien"` output as `Jean O&apos;Brien` automatically

---

[← Back to README](../README.md) | [← Transforming Collections](05-transforming-collections.md) | [Next: Database Integration →](07-database-integration.md)
