# Filtering and Sorting

How to narrow a loaded result down to the rows you want, and put them in
the right order.

Contents:

- [Matching Rows: where()](#matching-rows-where)
- [Excluding Rows: whereNot()](#excluding-rows-wherenot)
- [CMS Builder List Fields: whereInList()](#cms-builder-list-fields-whereinlist)
- [Custom Tests: filter()](#custom-tests-filter)
- [Sorting: sortBy() and sort()](#sorting-sortby-and-sort)
- [Duplicates and Membership: unique() and contains()](#duplicates-and-membership-unique-and-contains)

## Matching Rows: where()

Choosing which rows to load is the query's job (SQL's WHERE clause);
there's no point fetching rows you won't use. These methods are for the
result you already have: one loaded collection sliced different ways for
different parts of the page, with no extra queries.

Use `where()` to keep the rows where a field matches a value. Chain it to
match on more than one field:

```php
use Itools\SmartArray\SmartArrayHtml;

$users = SmartArrayHtml::new([
    ['name' => 'Jean', 'status' => 'Active',   'role' => 'admin',  'newsletter' => 1],
    ['name' => 'Tom',  'status' => 'Inactive', 'role' => 'admin',  'newsletter' => 0],
    ['name' => 'Sam',  'status' => 'Active',   'role' => 'editor', 'newsletter' => 1],
]);

$active = $users->where('status', 'Active');                        // Jean and Sam
$admins = $users->where('status', 'Active')->where('role', 'admin');  // just Jean
```

Databases and forms often hand numbers back as strings, so `where()`
compares loosely: `'1'` matches `1`. When you need a strict match,
`filter()` (below) takes a callback where you can compare with `===`.

With just a field name, `where()` keeps the rows where that field has a
truthy value, following PHP's `empty()` rules (NULL, false, 0, `"0"`, and
`""` don't count), which fits checkbox fields:

```php
$subscribed = $users->where('newsletter');  // Jean and Sam
```

## Excluding Rows: whereNot()

The `whereNot()` method is the inverse of `where()`: it drops the matching
rows and keeps everything else:

```php
$nonAdmins = $users->whereNot('role', 'admin');  // just Sam
```

It mirrors the single-argument form too: `whereNot('newsletter')` keeps
the rows where the field is empty by PHP's `empty()` rules, meaning NULL,
false, 0, `"0"`, `""`, or the field missing from the row entirely.

## CMS Builder List Fields: whereInList()

This one is specific to CMS Builder, which stores checkbox groups and
multi-select fields as tab-separated lists in a single column (a page shown
in two places stores `"\tmenu\tfooter\t"`). Use `whereInList()` to match
one value inside those fields; it matches whole values, never substrings.
The whole where-family chains on one loaded result:

```php
$pages = SmartArrayHtml::new([
    ['num' => 1, 'title' => 'Home',     'hidden' => 0, 'showIn' => "\tmenu\tfooter\t"],
    ['num' => 2, 'title' => 'About',    'hidden' => 0, 'showIn' => "\tmenu\t"],
    ['num' => 3, 'title' => 'Login',    'hidden' => 0, 'showIn' => "\tmenu\t"],
    ['num' => 4, 'title' => 'Old News', 'hidden' => 1, 'showIn' => "\tmenu\t"],
]);

$menuPages = $pages->where('hidden', 0)->whereNot('title', 'Login')->whereInList('showIn', 'menu');

foreach ($menuPages as $page) {
    echo "<li>$page->title</li>\n";
}
// <li>Home</li>
// <li>About</li>
```

## Custom Tests: filter()

When the test is more than field-equals-value, `filter()` takes a callback.
The callback receives plain PHP values (row arrays, strings, numbers), so
you write ordinary PHP inside it:

```php
$tables = SmartArrayHtml::new(['cms_accounts', 'wp_posts', 'cms_orders']);

$ours = $tables->filter(fn($name) => str_starts_with($name, 'cms_'));  // cms_accounts, cms_orders
```

Called with no callback, `filter()` removes empty values (`""`, `0`, NULL,
false). Keep in mind that `0` is removed even when it's real data (a $0
price, a sort order of 0); pass a callback when zeros should stay. Like
PHP's `array_filter()`, kept rows keep their original keys; chain
`values()` when you want them renumbered.

## Sorting: sortBy() and sort()

Use `sortBy()` to order rows by a field. It returns a new sorted
collection and never touches the original (PHP's own `sort()` modifies
arrays in place; SmartArray methods don't), so your result stays in query
order and can be sorted different ways for different spots on the page:

```php
$funds = SmartArrayHtml::new([
    ['name' => 'Fund 10'],
    ['name' => 'Fund 2'],
    ['name' => 'Fund 1'],
]);

$textOrder = $funds->sortBy('name');                // Fund 1, Fund 10, Fund 2
$realOrder = $funds->sortBy('name', SORT_NATURAL);  // Fund 1, Fund 2, Fund 10
```

Plain text sorting compares character by character, which puts "Fund 10"
before "Fund 2"; `SORT_NATURAL` sorts numbers the way people read them.

For flat lists (no rows, just values), use `sort()`:

```php
$tags = SmartArrayHtml::new(['PHP', 'MySQL', 'Apache']);

echo $tags->sort()->implode(', ');  // Apache, MySQL, PHP
```

## Duplicates and Membership: unique() and contains()

Use `unique()` to drop repeated values from a flat list, keeping the first
of each, and `contains()` to ask whether a value is in the list at all.
Both compare loosely, so `1` and `'1'` count as the same value:

```php
$tags = SmartArrayHtml::new(['PHP', 'MySQL', 'PHP', 'Apache']);

echo $tags->unique()->implode(', ');   // PHP, MySQL, Apache
var_export($tags->contains('MySQL'));  // true
```

---

[← Documentation Index](README.md) | [Next: Transforming and Grouping →](transforming-and-grouping.md)
