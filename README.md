# SmartArray: Enhanced Arrays with Chainable Methods and Automatic HTML Encoding

SmartArray extends PHP arrays with automatic HTML encoding and chainable utility methods.
It adds powerful features for filtering, mapping, and data manipulation - making common
array operations simpler, safer, and more expressive.

## Table of Contents

<!-- TOC -->

* [SmartArray: Enhanced Arrays with Chainable Methods and Automatic HTML Encoding](#smartarray-enhanced-arrays-with-chainable-methods-and-automatic-html-encoding)
    * [Table of Contents](#table-of-contents)
    * [Quick Start](#quick-start)
    * [Usage Examples](#usage-examples)
        * [Accessing Elements by Position with nth()](#accessing-elements-by-position-with-nth)
        * [Looking Up Authors by ID with indexBy()](#looking-up-authors-by-id-with-indexby)
        * [Organizing Books by Genre with groupBy()](#organizing-books-by-genre-with-groupby)
        * [Extracting Unique Tags with pluck(), unique(), and implode()](#extracting-unique-tags-with-pluck-unique-and-implode)
        * [Building Dynamic HTML Tables with sprintf()](#building-dynamic-html-tables-with-sprintf)
        * [Debugging and Help](#debugging-and-help)
    * [Method Reference](#method-reference)
    * [Questions?](#questions)

<!-- TOC -->

## Quick Start

Install via Composer:

```bash
composer require itools/smartarray
```

Include the Composer autoloader and SmartArray class:

```php
<?php
require 'vendor/autoload.php';
use Itools\SmartArray\SmartArray;
```

Convert an array to a SmartArray and use the recommended workflow:

```php
$records = [
    ['id' => 10, 'name' => "John O'Connor",  'city' => 'New York'],
    ['id' => 15, 'name' => 'Xena "X" Smith', 'city' => 'Los Angeles'],
    ['id' => 20, 'name' => 'Tom & Jerry',    'city' => 'Vancouver'],
];

// Always start with raw data for processing
$users = SmartArray::new($records)
    ->asHtml()          // Make values HTML-safe (or use SmartArrayHtml::new() directly)
    ->sortBy('name');   // Sort alphabetically by name


// Now $users contains SmartStrings for safe output
foreach ($users as $user) {
    echo "Name: $user->name, ";   // Automatically HTML-encoded for safety
    echo "City: $user->city\n";
}

// Values are automatically HTML-encoded in string contexts to prevent XSS (see SmartString docs more details)
echo $users->first()->name; // Output: John O&apos;Connor

// Use chainable methods to transform data
$userIdAsCSV = $users->pluck('id')->implode(', '); // Output: "10, 20, 15"

// Easily convert back to arrays and original values
$usersArray = $users->toArray(); // Convert back to a regular PHP array and values

// Convert SmartStrings back to original values
// Note: The `value()` method returns the raw value from a `SmartString` object.
$userId = $users->first()->id->value(); // Returns 10 as an integer

// Note: If the key doesn’t exist, a SmartNull object is returned instead of throwing an error, so you can chain
// safely without checking for isset() first.

```    

See the [Method Reference](#method-reference) for more information on available methods.

## Usage Examples

### Accessing Elements by Position with nth()

The `nth()` method provides a convenient way to access elements by their position, supporting both positive and negative
indices. This is particularly useful for accessing specific items in ordered lists like search results, leaderboards, or
recent activity feeds.

```php
$topSellers = [
    ['rank' => 1, 'title' => 'The Great Gatsby', 'sales' => 25000000],
    ['rank' => 2, 'title' => '1984', 'sales' => 20000000],
    ['rank' => 3, 'title' => 'To Kill a Mockingbird', 'sales' => 18000000],
    ['rank' => 4, 'title' => 'The Catcher in the Rye', 'sales' => 15000000],
    ['rank' => 5, 'title' => 'The Hobbit', 'sales' => 14000000]
];

$books = SmartArray::new($topSellers)->asHtml();

// Get specific positions (0-based indexing)
echo $books->nth(0)->title;  // "The Great Gatsby" (first book)
echo $books->nth(2)->title;  // "To Kill a Mockingbird" (third book)

// Use negative indices to count from the end
echo $books->nth(-1)->title; // "The Hobbit" (last book)
echo $books->nth(-2)->title; // "The Catcher in the Rye" (second-to-last)

// Common use cases:

// Get podium finishers in a competition
$goldMedalist   = $results->nth(0);
$silverMedalist = $results->nth(1);
$bronzeMedalist = $results->nth(2);

// Display recent activity with the newest first
$mostRecent       = $activities->nth(0);
$secondMostRecent = $activities->nth(1);

// Show last few items in a feed
$latestLogEntry    = $log->nth(-1);
$secondLatestEntry = $log->nth(-2);
```

**Key Features:**

- Zero-based indexing: `nth(0)` returns the first element
- Negative indices: `nth(-1)` returns the last element
- Works with both indexed and associative arrays

### Looking Up Authors by ID with indexBy()

When working with collections of records, you often need to look up specific records by their ID or another unique
field. The `indexBy()` method makes this easy by creating an associative array using a specified field as the key:

```php
$authors = [
    ['author_id' => 101, 'name' => 'Jane Austen',    'genre' => 'Literary Fiction'],
    ['author_id' => 102, 'name' => 'George Orwell',  'genre' => 'Political Fiction'],
    ['author_id' => 103, 'name' => 'Isaac Asimov',   'genre' => 'Science Fiction'],
    ['author_id' => 104, 'name' => 'Agatha Christie','genre' => 'Mystery'],
];

// Create a lookup array indexed by author_id
$authorById = SmartArray::new($authors)->indexBy('author_id')->asHtml();

// Now you can quickly look up authors by their ID
echo $authorById->get(101)->name;  // Output: Jane Austen
echo $authorById->get(103)->genre; // Output: Science Fiction

// Particularly useful when joining data from multiple sources
$articles = [
    ['article_id' => 1, 'title' => 'Pride and Programming', 'author_id' => 101],
    ['article_id' => 2, 'title' => 'Digital Dystopia',      'author_id' => 102],
    ['article_id' => 3, 'title' => 'Robot Psychology',      'author_id' => 103],
];

// Display articles with author information
foreach (SmartArray::new($articles)->asHtml() as $article) {
    $author = $authorById->get($article->author_id);
    echo "Title: $article->title\n";
    echo "By: $author->name ($author->genre)\n\n";
}
```

Output:

```
Title: Pride and Programming
By: Jane Austen (Literary Fiction)

Title: Digital Dystopia
By: George Orwell (Political Fiction)

Title: Robot Psychology
By: Isaac Asimov (Science Fiction)
```

> **Important Notes:**
> - If multiple records have the same key value, later records will overwrite earlier ones
> - For collections with duplicate keys, use `groupBy()` instead to preserve all records
> - Keys are automatically converted to strings (as per PHP array key behavior)
> - Missing keys in the source data will be skipped with a warning

Need to preserve duplicate keys? Consider using `groupBy()` instead.

### Organizing Books by Genre with groupBy()

When working with data that has multiple items sharing the same key (like books by genre, products by category, or
employees by department), `groupBy()` helps organize them into logical groups while preserving all records:

```php
$books = [
    ['title' => 'Pride and Prejudice', 'author' => 'Jane Austen',   'genre' => 'Literary Fiction', 'year' => 1813],
    ['title' => '1984',                'author' => 'George Orwell', 'genre' => 'Science Fiction',  'year' => 1949],
    ['title' => 'Foundation',          'author' => 'Isaac Asimov',  'genre' => 'Science Fiction',  'year' => 1951],
    ['title' => 'Emma',                'author' => 'Jane Austen',   'genre' => 'Literary Fiction', 'year' => 1815],
    ['title' => 'I, Robot',            'author' => 'Isaac Asimov',  'genre' => 'Science Fiction',  'year' => 1950],
    ['title' => 'Persuasion',          'author' => 'Jane Austen',   'genre' => 'Literary Fiction', 'year' => 1818],
];

// Group books by genre
$booksByGenre = SmartArray::new($books)->groupBy('genre')->asHtml();

// Now you can work with each genre's books separately
foreach ($booksByGenre as $genre => $relatedBooks) {
    echo "\n$genre Books:\n";
    echo str_repeat('-', strlen($genre) + 7) . "\n";

    foreach ($relatedBooks as $book) {
        echo "- $book->title ($book->year)\n";
    }
}

// Group by author to analyze their work
$booksByAuthor = SmartArray::new($books)->groupBy('author')->asHtml();

foreach ($booksByAuthor as $author => $authorBooks) {
    $years = $authorBooks->pluck('year')->values()->sort();
    echo "\n$author published {$authorBooks->count()} books ({$years->first()}-{$years->last()}):\n";

    foreach ($authorBooks->sortBy('year') as $book) {
        echo "- $book->title ($book->year)\n";
    }
}
```

Output:

```
Literary Fiction Books:
------------------
- Pride and Prejudice (1813)
- Emma (1815)
- Persuasion (1818)

Science Fiction Books:
-------------------
- 1984 (1949)
- Foundation (1951)
- I, Robot (1950)

Jane Austen published 3 books (1813-1818):
- Pride and Prejudice
- Emma
- Persuasion

Isaac Asimov published 2 books (1950-1951):
- I, Robot
- Foundation

George Orwell published 1 book (1949):
- 1984
```

**Common Use Cases for groupBy():**

- Products by category or manufacturer
- Employees by department or location
- Sales by region or time period
- Students by grade level or class
- Events by date or venue
- Comments by post or user
- Tasks by status or priority

**Important Notes:**

- Each group contains a new SmartArray of all matching records
- Original record order is preserved within groups
- Groups are created in order of first appearance
- Missing or null values in the group key will be skipped with a warning
- Use `indexBy()` instead if you only need one record per key

### Extracting Unique Tags with pluck(), unique(), and implode()

When working with collections, you often need to extract a single field, remove duplicates, and produce a display string.
Here's how `SmartArray` chains these operations into a single expressive pipeline.

```php
$articles = [
    ['title' => 'Getting Started with PHP',  'tag' => 'PHP'],
    ['title' => 'Understanding Unit Tests',   'tag' => 'Testing'],
    ['title' => 'Data Handling Techniques',   'tag' => 'PHP'],
    ['title' => 'MySQL Best Practices',       'tag' => 'Databases'],
    ['title' => 'Advanced PHP Techniques',    'tag' => 'PHP'],
];

// Extract unique, sorted tags as a comma-separated display string
$tagList = SmartArray::new($articles)->pluck('tag')->unique()->sort()->implode(', ');

// Or for better readability, the same operation can be split across multiple lines:
$tagList = SmartArray::new($articles)
                ->pluck('tag')               // Extract tag column: ["PHP", "Testing", "PHP", "Databases", "PHP"]
                ->unique()                   // Remove duplicates: ["PHP", "Testing", "Databases"]
                ->sort()                     // Sort alphabetically: ["Databases", "PHP", "Testing"]
                ->implode(', ');             // Join as string: "Databases, PHP, Testing"

echo "Topics: $tagList"; // Output: "Topics: Databases, PHP, Testing"
```

### Building Dynamic HTML Tables with sprintf()

The `sprintf()` method applies formatting to each element, making it easy to wrap values in HTML tags.
Combined with `implode()`, you can build table rows in a single expression.

**Key Features:**

- Supports `{value}` and `{key}` as readable aliases for `%1$s` and `%2$s`
- Also works with standard sprintf formats: `%s`, `%1$s`, `%2$s`, etc.
- Values are automatically HTML-encoded for SmartArrayHtml (XSS-safe)
- Format string is applied to each element, then `implode()` joins them

```php
$rows = SmartArray::new([
    ['name' => "John O'Connor",  'city' => 'New York',    'status' => 'Active'],
    ['name' => 'Jane <script>',  'city' => 'Los Angeles', 'status' => 'Pending'],
    ['name' => 'Tom & Jerry',    'city' => 'Vancouver',   'status' => 'Active'],
])->asHtml();
?>

<table class='data-table'>
    <?php if ($rows->isNotEmpty()): ?>
        <thead>
            <tr><?= $rows->first()->keys()->sprintf("<th>{value}</th>")->implode("\n") ?></tr>
        </thead>
    <?php endif ?>

    <tbody>
        <?php foreach ($rows as $row): ?>
            <tr><?= $row->sprintf("<td>{value}</td>")->implode("\n") ?></tr>
        <?php endforeach ?>

        <?php if ($rows->isEmpty()): ?>
            <tr><td colspan="3">No records found</td></tr>
        <?php endif ?>
    </tbody>
</table>
```

Output:

```html

<table class='data-table'>
    <thead>
    <tr>
        <th>name</th>
        <th>city</th>
        <th>status</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>John O&apos;Connor</td>
        <td>New York</td>
        <td>Active</td>
    </tr>
    <tr>
        <td>Jane &lt;script&gt;</td>
        <td>Los Angeles</td>
        <td>Pending</td>
    </tr>
    <tr>
        <td>Tom &amp; Jerry</td>
        <td>Vancouver</td>
        <td>Active</td>
    </tr>
    </tbody>
</table>
```

Note how `O'Connor`, `<script>`, and `&` are safely HTML-encoded in the output.

**Using `{key}` for Select Options:**

```php
$countries = SmartArray::new(['us' => 'United States', 'ca' => 'Canada', 'mx' => 'Mexico'])->asHtml();
?>
<select name="country">
    <?= $countries->sprintf("<option value='{key}'>{value}</option>")->implode("\n") ?>
</select>
```

Output:

```html
<select name="country">
    <option value='us'>United States</option>
    <option value='ca'>Canada</option>
    <option value='mx'>Mexico</option>
</select>
```

### Debugging and Help

SmartArray provides helpful debugging tools to inspect your data structures and explore available methods.

**Debug View**
Call `print_r()` on any `SmartArray` to see a detailed view of its structure:

```php
$users = SmartArray::new([
    ['id' => 10, 'name' => "John O'Connor",  'city' => 'New York'],
    ['id' => 20, 'name' => 'Tom & Jerry',    'city' => 'Vancouver'],
]);
print_r($users);
```

The output shows the nested structure and metadata for each level:

```php
Itools\SmartArray\SmartArray Object
(
    [__DEBUG_INFO__] => // SmartArray debug view, call $var->help() for inline help

    SmartArray([
        SmartArray([
            id   => SmartString(10),
            name => SmartString("John O'Connor"),
            city => SmartString("New York"),
        ]),
        SmartArray([
            id   => SmartString(20),
            name => SmartString("Tom & Jerry"),
            city => SmartString("Vancouver"),
        ]),
    ]),
)
```

**Interactive Help**

Need a quick reference? Call `help()` on any `SmartArray` object to see available methods
and usage examples:

```php
$users->help();  // Displays comprehensive documentation and examples
```

## Method Reference

Note: All methods return a new `SmartArray` object unless otherwise specified.

| Category              |                          Method | Description                                                                                                                |
|-----------------------|--------------------------------:|----------------------------------------------------------------------------------------------------------------------------|
| Creation & Conversion |         SmartArray::new($array) | Create a SmartArray with raw PHP values for data processing. Arrays become SmartArrays, missing keys become SmartNulls     |
|                       |               $array->toArray() | Converts back to regular PHP array with original values                                                                    |
|                       |                $array->asHtml() | Return values as HTML-safe SmartString objects (lazy conversion - returns same object if already HTML-safe)                |
|                       |                 $array->asRaw() | Return values as raw PHP types (lazy conversion - returns same object if already using raw values)                         |
|                       |     SmartArrayHtml::new($array) | Create a SmartArray with HTML-safe SmartString values directly (equivalent to SmartArray::new()->asHtml())                 |
| Value Access          |                       $obj->key | Get a value using property syntax                                                                                          |
|                       |                       get($key) | Get a value by key (for numeric keys or keys with special characters)                                                      |
|                       |             get($key, $default) | Get a value with optional default if key not found                                                                         |
|                       |            set($key, $value) | Set a value by key (for numeric keys or keys with special characters)                                                      |
|                       |                         first() | Get the first element                                                                                                      |
|                       |                          last() | Get the last element                                                                                                       |
|                       |                     nth($index) | Get element by position, ignoring keys (0=first, -1=last)                                                                  |
|                       | SmartArray::getRawValue($value) | Converts SmartArray and SmartString objects to their original values while leaving other types unchanged                   |
| Array Information     |                         count() | Get the number of elements                                                                                                 |                                                                                                                                                             
|                       |                       isEmpty() | Returns true if array has no elements                                                                                      |
|                       |                    isNotEmpty() | Returns true if array has any elements                                                                                     |
|                       |               contains($value) | Returns true if array contains value                                                                                       |
| Sorting & Filtering   |                          sort() | Sorts elements by value (flat arrays only)                                                                                 |
|                       |                 sortBy($column) | Sorts rows by column value (nested arrays only)                                                                            |
|                       |                        unique() | Removes duplicate values (flat arrays only)                                                                                |
|                       |                        filter() | Removes falsey values ("", 0, empty array, etc)                                                                            |
|                       |               filter($callback) | Removes elements where callback returns false (callback receives raw values)                                               |
|                       |              where($conditions) | Removes rows not matching conditions like `['status' => 'active']` (uses loose comparison: '1' matches 1, false matches 0) |
|                       |           where($field, $value) | Shorthand: Removes rows where field doesn't match value (e.g., `where('status', 'active')`)                                |
| Array Transformation  |                       toArray() | Converts back to regular PHP array with original values                                                                    |
|                       |                          keys() | Gets array of keys, discarding the values                                                                                  |
|                       |                        values() | Gets array of values, discarding the keys                                                                                  |
|                       |                indexBy($column) | Indexes rows by column value, latest is kept if duplicates                                                                 |
|                       |                groupBy($column) | Groups rows by column value, preserving duplicates                                                                         |
|                       |                     pluck($key) | Gets array of column values from rows                                                                                      |
|                       |               pluckNth($index) | Gets array of values at position from rows                                                                                 |
|                       |             implode($separator) | Joins elements with separator into string                                                                                  |
|                       |                sprintf($format) | Applies sprintf formatting to each element. Supports `{value}` and `{key}` placeholders.                                   |
|                       |                  map($callback) | Transforms each element using callback (callback receives raw values)                                                      |
|                       |                 each($callback) | Call callback on each element as Smart objects. Used for side effects, doesn't modify array.                               |
|                       |               merge(...$arrays) | Merges with one or more arrays. Numeric keys are renumbered, string keys are overwritten by later values.                  |
| Database Operations   |                                 | The following optional methods may be available when using SmartArray with database results                                |
|                       |                        mysqli() | Get an array of all mysqli result metadata (set when creating array from DB result)                                        |
|                       |                    mysqli($key) | Get specific mysqli result metadata (errno, error, affected_rows, insert_id, etc)                                          |
|                       |                          load() | Loads related record(s) if available for column                                                                            |
| Error Handling        |                 or404($message) | Exits with 404 header and message if array is empty, default message: "404 Not Found"                                      |
|                       |                 orDie($message) | Exits with message if array is empty                                                                                       |
|                       |               orThrow($message) | Throws exception with message if array is empty                                                                            |
|                       |                orRedirect($url) | Redirects to URL if array is empty (HTTP 302)                                                                              |
| Debugging and Help    |                          help() | Displays help information about available methods                                                                          |
|                       |                         debug() | Show content of object as well as properties                                                                               |                                                                                                                                                                       
|                       |                   print_r($obj) | Show array contents of object (useful for debugging)                                                                       |     

**See Also:** For working with `SmartArray` values, check out the included companion
library `SmartString`, all `SmartArray` values are `SmartString` objects with
automatic HTML encoding and chainable methods:
https://github.com/interactivetools-com/SmartString?tab=readme-ov-file#method-reference

## Questions?

This library was developed for CMS Builder, post a message in our "CMS Builder" forum here:
[https://www.interactivetools.com/forum/](https://www.interactivetools.com/forum/)
