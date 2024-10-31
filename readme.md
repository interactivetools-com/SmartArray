# SmartArray: PHP Arrays with Superpowers

SmartArray enhances PHP arrays to work as both traditional arrays and chainable objects.
You get all the familiar array features you know, plus powerful new methods for filtering,
mapping, grouping, and handling nested data - making array operations simpler while preserving
normal array syntax.

## Table of Contents

* [Quick Start](#quick-start)
* [Usage Examples](#usage-examples)
  * [Highlighting Recent Articles with position()](#highlighting-recent-articles-with-position)
  * [Accessing Elements by Position with nth()](#accessing-elements-by-position-with-nth)
  * [Looking Up Authors by ID with indexBy()](#looking-up-authors-by-id-with-indexby)
  * [Organizing Books by Genre with groupBy()](#organizing-books-by-genre-with-groupby)
  * [Building Safe MySQL ID Lists with pluck(), map(), and join()](#building-safe-mysql-id-lists-with-pluck-map-and-join)
  * [Creating Grid Layouts with isFirst(), isLast(), isMultipleOf() and chunk()](#creating-grid-layouts-with-isfirst-islast-ismultipleof-and-chunk)
  * [Debugging and Help](#debugging-and-help)
* [Method Reference](#method-reference)
* [Questions?](#questions)

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

$users = SmartArray::new($records); // Convert to SmartArray (values get converted to SmartStrings)

// Foreach over a SmartArray just like a regular array 
foreach ($users as $user) {
    echo "Name: {$user['name']}, ";  // use regular array syntax
    echo "City: $user->city\n";      // or cleaner object syntax (no curly braces needed)
}

// Values are automatically HTML-encoded in string contexts to prevent XSS (see SmartString docs more details)
echo $users->first()->name; // Output: John O&apos;Connor
    
// Use chainable methods to transform data
$userIdAsCSV = $users->pluck('id')->join(', '); // Output: "10, 15, 20"

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

### Building Safe MySQL ID Lists with pluck(), map(), and join()

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
$authorIdCSV = SmartArray::new($articles)->pluck('author_id')->map('intval')->unique()->join(',')->ifBlank('0')->value();

// Or for better readability, the same operation can be split across multiple lines:
$authorIdCSV = SmartArray::new($articles)    // Convert ResultSet to SmartArray (nested arrays become SmartArrays)
                ->pluck('author_id')         // Extract just the author_id column: [104, 102, 103, 104, 105]
                ->map('intval')              // Ensure all IDs are integers: [104, 102, 103, 104, 105] 
                ->unique()                   // Remove duplicate IDs: [104, 102, 103, 105]
                ->join(',')                  // Create comma-separated list: "104,102,103,105" (returns SmartString)
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

| Category             |                  Method | Description                                                                                                                                                                 |
|----------------------|------------------------:|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Basic Usage          | SmartArray::new($array) | Creates a new `SmartArray` from a regular PHP array. All nested arrays and values are converted to `SmartArray` and `SmartString` objects                                   |      
|                      |               toArray() | Converts a `SmartArray` back to a standard PHP array, converting all nested `SmartArray` and `SmartString` objects back to their original values                            |       
| Array Information    |                 count() | Returns the number of elements                                                                                                                                              |                                                                                                                                                             
|                      |               isEmpty() | Returns true if `SmartArray` contains **no** elements                                                                                                                       |
|                      |            isNotEmpty() | Returns true if `SmartArray` contains **any** elements                                                                                                                      |
|                      |               isFirst() | Returns true if this is the **first** element in its parent `SmartArray`                                                                                                    |
|                      |                isLast() | Returns true if this is the **last** element in its parent `SmartArray`                                                                                                     |
|                      |              position() | Gets this element's position in its parent `SmartArray` (starting from 1)                                                                                                   |
|                      |    isMultipleOf($value) | Returns true if this element's position is a multiple of $value (useful for grids)                                                                                          |
| Value Access         |                  [$key] | Get a value using array syntax, e.g., `$array['key']`                                                                                                                       |
|                      |                   ->key | Get a value using object syntax, e.g., `$array->key`                                                                                                                        |
|                      |               get($key) | Alternative method to get a value, e.g., `$array->get($key)`                                                                                                                |
|                      |                 first() | Get the first element                                                                                                                                                       |
|                      |                  last() | Get the last element                                                                                                                                                        |
|                      |             nth($index) | Get element by position, starting at 0, e.g., `->nth(0)` first, `->nth(1)` second, `->nth(-1)` last                                                                         |
| Array Transformation |                  keys() | Get just the keys as a new `SmartArray`, e.g., `['id', 'name', 'email']`                                                                                                    |
|                      |                values() | Get just the values as a new `SmartArray`, discarding the keys                                                                                                              |
|                      |                unique() | Get unique values (removes duplicates, preserves keys)                                                                                                                      
|                      |                  sort() | Sort elements in ascending order                                                                                                                                            |
|                      |         sortBy($column) | Sort elements by a specific column                                                                                                                                          |
|                      |        indexBy($column) | For nested arrays, create a new `SmartArray` using a column as the key, e.g., `indexBy('id')`. Duplicates use latest value.                                                 |
|                      |        groupBy($column) | Like `indexBy()` but returns values as a `SmartArray` to preserve duplicates.  e.g., `$usersByCity = $users->groupBy('city')`                                               |
|                      |        join($separator) | Combine values into a `SmartString`, e.g., `$users->pluck('id')->join(', ')` creates `"23, 51, 72"`                                                                         |
|                      |          map($callback) | Transform each element using a callback function, returning a new `SmartArray`.  The callback function receives the original value or array as an argument for each element |
|                      |             pluck($key) | Extract one column from a nested `SmartArray`, e.g., `$users->pluck('name')` returns a `SmartArray` with all names                                                          |
|                      |            chunk($size) | Returns a `SmartArray` of smaller `SmartArray`s of the specified size (for grid layouts)                                                                                    |
|                      |  **Debugging and Help** |                                                                                                                                                                             |
|                      |                  help() | Displays help information about available methods                                                                                                                           |

**See Also:** For working with `SmartArray` values, check out the included companion
library `SmartString`, all `SmartArray` values are `SmartString` objects with
automatic HTML encoding and chainable methods:  
https://github.com/interactivetools-com/SmartString?tab=readme-ov-file#method-reference

## Questions?

This library was developed for CMS Builder, post a message in our "CMS Builder" forum here:
[https://www.interactivetools.com/forum/](https://www.interactivetools.com/forum/)
