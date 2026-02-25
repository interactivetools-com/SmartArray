# Database Integration

SmartArray integrates with ZenDB to deliver HTML-safe access to database results, query metadata, lazy-loading of related records, and graceful handling of missing data.

## Working with ZenDB Results

When ZenDB runs a query, it returns a `SmartArrayHtml` collection. Rows are `SmartArrayHtml` objects and scalar values are `SmartString` objects that auto-encode on output.

```php
// ZenDB returns SmartArrayHtml -- values auto-encode on echo
$users = DB::select('users', "status = ?", 'active');

foreach ($users as $user) {
    echo "<li>$user->name -- $user->city</li>\n"; // safe, no htmlspecialchars needed
}
```

You do not need to call `asHtml()` on ZenDB results — they are already in HTML mode.

To process the data (sort, filter, transform), chain SmartArray methods directly on the result:

```php
// Chain methods on ZenDB results as usual
$sortedUsers = $users->sortBy('name');
$nameList    = $users->pluck('name')->implode(', ');
```

## Query Metadata — `mysqli()`

After a ZenDB query, the result carries mysqli metadata. Call `mysqli()` to access it.

Available keys: `affected_rows`, `insert_id`, `num_rows`, `errno`, `error`, `query`.

### Getting all metadata — `mysqli()`

Call `mysqli()` with no arguments to get all metadata as an associative array.

```php
// Get all metadata as an array
$result   = DB::insert('users', ['name' => "Jean O'Brien", 'city' => 'Ottawa']);
$metadata = $result->mysqli(); // ['insert_id' => 42, 'affected_rows' => 1, ...]
```

### Getting a specific value — `mysqli($key)`

Pass a key to get a single metadata value directly.

```php
// Get the new record's ID after an insert
$result = DB::insert('users', ['name' => "Jean O'Brien", 'city' => 'Ottawa']);
$newId  = $result->mysqli('insert_id');

// Check how many rows were updated
$result      = DB::update('users', ['city' => 'Toronto'], "id = ?", 1);
$rowsChanged = $result->mysqli('affected_rows');
```

## Handling Empty Results — Error Handlers

Use error handlers to fail fast when required data is missing. Each method returns `$this` unchanged if the array is not empty, so they chain cleanly.

### Send a 404 — `or404()`

Sends an HTTP 404 response and exits if the array is empty. Accepts an optional message string.

```php
// Exit with 404 if user not found
$user = DB::get('users', "id = ?", $userId)->or404();
```

### Die with a message — `orDie()`

Exits with a plain-text message if the array is empty.

```php
// Die with message if required setting is missing
$config = DB::get('settings', "key = ?", 'required_key')->orDie("Required setting missing.");
```

### Throw an exception — `orThrow()`

Throws `RuntimeException` if the array is empty. Use this inside try/catch blocks or when you want exception-based error handling.

```php
// Throw if product not found
$record = DB::get('products', "sku = ?", $sku)->orThrow("Product not found: $sku");
```

### Redirect if empty — `orRedirect()`

Sends an HTTP 302 redirect if the array is empty. **Requires that no output has been sent yet** — see [Troubleshooting](08-troubleshooting-and-gotchas.md) if you get a headers-already-sent error.

```php
// Redirect to login if session not found
$session = DB::get('sessions', "token = ?", $token)->orRedirect('/login');
```

## Lazy Loading — `load()` and `setLoadHandler()`

### What load handlers are

A load handler is a callback registered on a result set that fetches related data on demand. When you call `$row->load('columnName')`, SmartArray passes the row and column name to the handler, which returns the related records.

This is how ZenDB implements relationship loading without a full ORM.

### Registering a handler — `setLoadHandler($callable)`

The callback signature is:

```php
function(SmartArray $row, string $column): array|false
```

It must return a two-element array: `[$data, $mysqliMetadata]`. Return `false` if the column name is not recognized.

```php
// Register a load handler on the result set
$users = DB::select('users', 'status = ?', 'active');
$users->setLoadHandler(function(SmartArray $row, string $column): array|false {
    return match($column) {
        'orders' => [DB::select('orders', 'user_id = ?', $row->get('id')->value())->toArray(), []],
        default  => false, // column not handled
    };
});
```

### Loading related data — `load($column)`

Call `load()` on a single row (not the whole result set). Returns a SmartArray of the related records, or SmartNull if the handler returns `false`.

```php
// Load related records for each row
foreach ($users as $user) {
    $orders = $user->load('orders');
    echo "$user->name has {$orders->count()} orders\n";
}
```

**Warning:** `load()` can only be called on a single row. Calling it on a result set throws `RuntimeException: Cannot call load() on record set, only on a single row.`

## The Root Reference — `root()`

Every SmartArray holds a reference to the outermost SmartArray in the hierarchy. Call `root()` to access it. This is useful inside a load handler when you need information from the parent result set.

```php
// Access the root result set from inside a nested row
foreach ($users as $user) {
    $root      = $user->root(); // the $users result set
    $rootCount = $root->count();
    echo "$user->name is one of $rootCount active users\n";
}
```

## JSON Serialization

SmartArray implements `JsonSerializable`. Passing a SmartArray to `json_encode()` works automatically, outputting values as their raw PHP types — not SmartString objects.

```php
// JSON encode a SmartArray directly
$users = SmartArray::new($records);
echo json_encode($users); // standard JSON array
```

---

[← Back to README](../README.md) | [← Output & HTML](06-output-and-html.md) | [Next: Troubleshooting & Gotchas →](08-troubleshooting-and-gotchas.md)
