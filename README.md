# SmartArray: Enhanced Arrays with Chainable Methods and Automatic HTML Encoding

SmartArray extends PHP arrays with automatic HTML encoding and chainable utility methods.
It preserves familiar array syntax while adding powerful features for filtering, mapping,
and data manipulation - making common array operations simpler, safer, and more expressive.

## Table of Contents

<!-- TOC -->

* [SmartArray](#smartarray-enhanced-arrays-with-chainable-methods-and-automatic-html-encoding)
    * [Table of Contents](#table-of-contents)
    * [Quick Start](#quick-start)
    * [Usage Examples](#usage-examples)
        * [Highlighting Recent Articles with position()](#highlighting-recent-articles-with-position)
        * [Accessing Elements by Position with nth()](#accessing-elements-by-position-with-nth)
        * [Looking Up Authors by ID with indexBy()](#looking-up-authors-by-id-with-indexby)
        * [Organizing Books by Genre with groupBy()](#organizing-books-by-genre-with-groupby)
        * [Building Safe MySQL ID Lists with pluck(), map(), and implode()](#building-safe-mysql-id-lists-with-pluck-map-and-implode)
        * [Creating Grid Layouts with isFirst(), isLast(), isMultipleOf() and chunk()](#creating-grid-layouts-with-isfirst-islast-ismultipleof-and-chunk)
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

Convert an array to a SmartArray:

```php
$records = [
    ['id' => 10, 'name' => "John O'Connor",  'city' => 'New York'],
    ['id' => 15, 'name' => 'Xena "X" Smith', 'city' => 'Los Angeles'],
    ['id' => 20, 'name' => 'Tom & Jerry',    'city' => 'Vancouver'],
];

$users = SmartArray::new($records)->withSmartStrings(); // Convert to SmartArray of SmartStrings

// Foreach over a SmartArray just like a regular array 
foreach ($users as $user) {
    echo "Name: {$user['name']}, ";  // use regular array syntax
    echo "City: $user->city\n";      // or cleaner object syntax (no curly braces needed)
}

// Values are automatically HTML-encoded in string contexts to prevent XSS (see SmartString docs more details)
echo $users->first()->name; // Output: John O&apos;Connor
    
// Use chainable methods to transform data
$userIdAsCSV = $users->pluck('id')->implode(', '); // Output: "10, 15, 20"

// Easily convert back to arrays and original values
$usersArray = $users->toArray(); // Convert back to a regular PHP array and values

// Convert SmartStrings back to original values
// Note: The `value()` method returns the raw value from a `SmartString` object.
$userId = $users->first()->id->value(); // Returns 10 as an integer

```    

See the [Method Reference](#method-reference) for more information on available methods.

## Usage Examples

### Highlighting Recent Articles with position()

The `position()` method returns an element's position within its parent array (starting from 1), making it easy to give
special treatment to important items like featured articles based on their position:

```php
$articles = [
    ['title' => 'Astronomers Photograph Distant Galaxy for First Time'],
    ['title' => 'New Species of Butterfly Found in Amazon Rainforest'],
    ['title' => 'Ocean Expedition Maps Unexplored Deep Sea Valleys'],
    ['title' => 'Ancient Star Charts Discovered in Mountain Cave'],
    ['title' => 'Rare Rainbow Clouds Spotted in Nordic Skies'],
    ['title' => 'Desert Expedition Reveals Hidden Oasis Ecosystem'],
    ['title' => 'Mountain Observatory Captures Meteor Shower Images']
];

$news = SmartArray::new($articles);

// Create a news listing with featured articles
echo "<div class='news-list'>\n";
foreach ($news as $article) {
    // First 3 articles get heading treatment
    if ($article->position() <= 3) {
        echo "<h1>$article->title</h1>\n";
    } else {
        // Remaining articles as regular text
        echo "$article->title<br>\n";
    }
}
echo "</div>\n";
```

Output:

```html

<div class='news-list'>
    <h1>Astronomers Photograph Distant Galaxy for First Time</h1>
    <h1>New Species of Butterfly Found in Amazon Rainforest</h1>
    <h1>Ocean Expedition Maps Unexplored Deep Sea Valleys</h1>
    Ancient Star Charts Discovered in Mountain Cave<br>
    Rare Rainbow Clouds Spotted in Nordic Skies<br>
    Desert Expedition Reveals Hidden Oasis Ecosystem<br>
    Mountain Observatory Captures Meteor Shower Images<br>
</div>
```

**Key Features:**

- One-based indexing: First element is position 1
- No manual counters needed

**Common Use Cases:**

- Featured latest articles on news sites
- Create design elements based on position
- Top stories in categories

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

$books = SmartArray::new($topSellers);

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
$authorById = SmartArray::new($authors)->indexBy('author_id');

// Now you can quickly look up authors by their ID
echo $authorById[101]->name;  // Output: Jane Austen
echo $authorById[103]->genre; // Output: Science Fiction

// Particularly useful when joining data from multiple sources
$articles = [
    ['article_id' => 1, 'title' => 'Pride and Programming', 'author_id' => 101],
    ['article_id' => 2, 'title' => 'Digital Dystopia',      'author_id' => 102],
    ['article_id' => 3, 'title' => 'Robot Psychology',      'author_id' => 103],
];

// Display articles with author information
foreach (SmartArray::new($articles) as $article) {
    $author = $authorById[$article->author_id];
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
    ['title' => 'Emma',                'author' => 'Jane Austen',   'genre' => 'Literary Fiction', 'year' => 181],
    ['title' => 'I, Robot',            'author' => 'Isaac Asimov',  'genre' => 'Science Fiction',  'year' => 1950],
    ['title' => 'Persuasion',          'author' => 'Jane Austen',   'genre' => 'Literary Fiction', 'year' => 1818],
];

// Group books by genre
$booksByGenre = SmartArray::new($books)->groupBy('genre');

// Now you can work with each genre's books separately
foreach ($booksByGenre as $genre => $relatedBooks) {
    echo "\n$genre Books:\n";
    echo str_repeat('-', strlen($genre) + 7) . "\n";

    foreach ($relatedBooks as $book) {
        echo "- $book->title ($book->year)\n";
    }
}

// Group by author to analyze their work
$booksByAuthor = SmartArray::new($books)->groupBy('author');

foreach ($booksByAuthor as $author => $books) {
    $years = $books->pluck('year')->values()->sort();
    echo "\n$author published {$books->count()} books ({$years->first()}-{$years->last()}):\n";

    foreach ($books->sortBy('year') as $book) {
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

### Building Safe MySQL ID Lists with pluck(), map(), and implode()

When working with database records, you may need to create a comma-separated list of IDs for use in SQL `IN` clauses.
Here's how `SmartArray` simplifies this process.

```php
$articles = [
   ['article_id' => 1, 'title' => 'Introduction to Testing',  'author_id' => 104],
   ['article_id' => 2, 'title' => 'Understanding Mockups',    'author_id' => 102], 
   ['article_id' => 3, 'title' => 'Data Handling in PHP',     'author_id' => 103],
   ['article_id' => 4, 'title' => 'Best Practices for MySQL', 'author_id' => 104],
   ['article_id' => 5, 'title' => 'Advanced PHP Techniques',  'author_id' => 105],
];

// Convert ResultSet to MySQL-safe ID list in one expressive line
$authorIdCSV = SmartArray::new($articles)->pluck('author_id')->map('intval')->unique()->implode(',')->ifBlank('0')->value();

// Or for better readability, the same operation can be split across multiple lines:
$authorIdCSV = SmartArray::new($articles)    // Convert ResultSet to SmartArray (nested arrays become SmartArrays)
                ->pluck('author_id')         // Extract just the author_id column: [104, 102, 103, 104, 105]
                ->map('intval')              // Ensure all IDs are integers: [104, 102, 103, 104, 105] 
                ->unique()                   // Remove duplicate IDs: [104, 102, 103, 105]
                ->implode(',')               // Create comma-separated list: "104,102,103,105" (returns SmartString)
                ->ifBlank('0')               // Handle empty ResultSets safely with "0" (SmartString method) 
                ->value();                   // Convert SmartString to raw string value: "104,102,103,105"

// Use in MySQL query - $authorIdCSV is now "104,102,103,105" (or "0" if empty)
$sql = "SELECT * FROM authors WHERE author_id IN ($authorIdCSV)";
```

### Creating Grid Layouts with isFirst(), isLast(), isMultipleOf() and chunk()

SmartArray makes it easy to create grid layouts by providing methods like `isFirst()`, `isLast()`, and `isMultipleOf()`
for position-based operations. Here's a simple example creating a table layout:

```php
$records = [
    ['id' => 10, 'name' => "John O'Connor",  'city' => 'New York'],
    ['id' => 15, 'name' => 'Xena "X" Smith', 'city' => 'Los Angeles'],
    ['id' => 20, 'name' => 'Tom & Jerry',    'city' => 'Vancouver'],
];
$users = SmartArray::new($records);

foreach ($users as $user) {
    if ($user->isFirst())       { echo "<table border='1' cellpadding='10' style='text-align: center'>\n<tr>\n"; }
    echo "<td><h1>$user->name</h1>$user->city</td>\n"; // values are automatically html encoded by SmartString
    if ($user->isMultipleOf(2)) { echo "</tr>\n<tr>\n"; }
    if ($user->isLast())        { echo "</tr>\n</table>\n"; }
}
```

Or using the `chunk()` method which splits the array into smaller arrays of a specified size:

```php
if ($users->isNotEmpty()) {
    echo "<table border='1' cellpadding='10' style='text-align: center'>\n";
    foreach ($users->chunk(2) as $row) {
        echo "<tr>\n";
        foreach ($row as $user) {
            echo "<td><h1>$user->name</h1>$user->city</td>\n"; // values are automatically html encoded by SmartString
        }
        echo "</tr>\n";
    }
    echo "</table>\n";
}
```

Both approaches produce the same output:

```html

<table border='1' cellpadding='10' style='text-align: center'>
    <tr>
        <td><h1>John O&apos;Connor</h1>New York</td>
        <td><h1>Xena &quot;X&quot; Smith</h1>Los Angeles</td>
    </tr>
    <tr>
        <td><h1>Tom &amp; Jerry</h1>Vancouver</td>
    </tr>
</table>
```

Note how special characters in the data (apostrophes, quotes, ampersands) are automatically HTML encoded by
`SmartString` to prevent XSS attacks while the table structure remains intact.
See the [Method Reference](#method-reference) for more information on available methods.

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

    SmartArray([        // Metadata: isFirst: false, isLast: false, position: 0
        SmartArray([    // Metadata: isFirst: true,  isLast: false, position: 1
            id   => SmartString(10),
            name => SmartString("John O'Connor"),
            city => SmartString("New York"),
        ]),
        SmartArray([    // Metadata: isFirst: false, isLast: true,  position: 2
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

| Category             |                          Method | Description                                                                                               |
|----------------------|--------------------------------:|-----------------------------------------------------------------------------------------------------------|
| Basic Usage          |         SmartArray::new($array) | Create a new SmartArray. Values stay as-is, nested arrays become SmartArrays                              |
|                      |              withSmartStrings() | Enable SmartStrings for array values, on output values become HTML-encoded SmartStrings                   |
|                      |                noSmartStrings() | Disable SmartStrings for array values                                                                     |
| Value Access         |                     $obj['key'] | Get a value using array syntax                                                                            |
|                      |                       $obj->key | Get a value using object syntax                                                                           |
|                      |                       get($key) | Get a value using method syntax                                                                           |
|                      |             get($key, $default) | Get a value with optional default if key not found                                                        |
|                      |                         first() | Get the first element                                                                                     |
|                      |                          last() | Get the last element                                                                                      |
|                      |                     nth($index) | Get element by position, ignoring keys (0=first, -1=last)                                                 |
|                      | SmartArray::getRawValue($value) | Converts SmartArray and SmartString objects to their original values while leaving other types unchanged  |
| Array Information    |                         count() | Get the number of elements                                                                                |                                                                                                                                                             
|                      |                       isEmpty() | Returns true if array has no elements                                                                     |
|                      |                    isNotEmpty() | Returns true if array has any elements                                                                    |
|                      |                      contains() | Returns true if array contains value                                                                      |
| Position & Layout    |                       isFirst() | Returns true if first element in parent array                                                             |
|                      |                        isLast() | Returns true if last element in parent array                                                              |
|                      |                      position() | Gets position in parent array (starting from 1)                                                           |
|                      |            isMultipleOf($value) | Returns true if position is multiple of value (useful for grids)                                          |
|                      |                    chunk($size) | Splits array into smaller arrays of the specified size (for grid layouts)                                 |
| Sorting & Filtering  |                          sort() | Sorts elements by value (flat arrays only)                                                                |
|                      |                 sortBy($column) | Sorts rows by column value (nested arrays only)                                                           |
|                      |                        unique() | Removes duplicate values (flat arrays only)                                                               |
|                      |                        filter() | Removes falsey values ("", 0, empty array, etc)                                                           |
|                      |               filter($callback) | Removes elements where callback returns false (callback receives raw values)                              |
|                      |              where($conditions) | Removes rows not matching conditions like `['status' => 'active']`                                        |
| Array Transformation |                       toArray() | Converts back to regular PHP array with original values                                                   |
|                      |                          keys() | Gets array of keys, discarding the values                                                                 |
|                      |                        values() | Gets array of values, discarding the keys                                                                 |
|                      |                indexBy($column) | Indexes rows by column value, latest is kept if duplicates                                                |
|                      |                groupBy($column) | Groups rows by column value, preserving duplicates                                                        |
|                      |                     pluck($key) | Gets array of column values from rows                                                                     |
|                      |                  pluckNth($key) | Gets array of values at position from rows                                                                |
|                      |             implode($separator) | Joins elements with separator into string                                                                 |
|                      |                  map($callback) | Transforms each element using callback (callback receives raw values)                                     |
|                      |             smartMap($callback) | Transforms each element as a SmartString or nested SmartArray                                             |
|                      |                 each($callback) | Call callback on each element as Smart objects. Used for side effects, doesn't modify array.              |
|                      |               merge(...$arrays) | Merges with one or more arrays. Numeric keys are renumbered, string keys are overwritten by later values. |
| Database Operations  |                                 | The following optional methods may be available when using SmartArray with database results               |
|                      |                        mysqli() | Get an array of all mysqli result metadata (set when creating array from DB result)                       |
|                      |                    mysqli($key) | Get specific mysqli result metadata (errno, error, affected_rows, insert_id, etc)                         |
|                      |                          load() | Loads related record(s) if available for column                                                           |
| Error Handling       |                 or404($message) | Exits with 404 header and message if array is empty, default message: "404 Not Found"                     |
|                      |                 orDie($message) | Exits with message if array is empty                                                                      |
|                      |               orThrow($message) | Throws exception with message if array is empty                                                           |
| Debugging and Help   |                          help() | Displays help information about available methods                                                         |
|                      |                         debug() | Show content of object as well as properties                                                              |                                                                                                                                                                       
|                      |                   print_r($obj) | Show array contents of object (useful for debugging)                                                      |     

**See Also:** For working with `SmartArray` values, check out the included companion
library `SmartString`, all `SmartArray` values are `SmartString` objects with
automatic HTML encoding and chainable methods:  
https://github.com/interactivetools-com/SmartString?tab=readme-ov-file#method-reference

## Questions?

This library was developed for CMS Builder, post a message in our "CMS Builder" forum here:
[https://www.interactivetools.com/forum/](https://www.interactivetools.com/forum/)
