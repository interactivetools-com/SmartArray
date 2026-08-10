# Common Patterns

Copy-paste recipes for tasks that come up on real sites. Every recipe is
built from methods covered on earlier pages, and the examples assume
database rows from [ZenDB](https://github.com/interactivetools-com/ZenDB)
or `SmartArrayHtml::new($records)`.

## Showing Related Names Without a Join

When rows reference another table by id, one keyed map replaces the join:
build it once with the two-argument `column()`, then look names up as you
loop:

```php
use Itools\SmartArray\SmartArrayHtml;

$authors = SmartArrayHtml::new([
    ['author_id' => 7,  'name' => 'Alice Munro'],
    ['author_id' => 12, 'name' => 'Bob Gibson'],
]);
$articles = SmartArrayHtml::new([
    ['title' => 'Runaway',      'author_id' => 7],
    ['title' => 'Local Trails', 'author_id' => 12],
]);

$authorById = $authors->column('name', 'author_id');  // [7 => 'Alice Munro', 12 => 'Bob Gibson']

foreach ($articles as $article) {
    $authorId   = $article->author_id->value();
    $authorName = $authorById->{$authorId}->or('Unknown Author');
    echo "<li>$article->title by $authorName</li>\n";
}
// <li>Runaway by Alice Munro</li>
// <li>Local Trails by Bob Gibson</li>
```

## Select Dropdowns from Query Results

Compare with the raw value to mark the current selection, echo the fields
for encoded output:

```php
$selectedId = (int)($_GET['author'] ?? 0);

echo "<select name='author'>\n";
foreach ($authors as $author) {
    $selected = $author->author_id->value() == $selectedId ? ' selected' : '';
    echo "<option value='$author->author_id'$selected>$author->name</option>\n";
}
echo "</select>\n";
// with ?author=12 this prints:
// <select name='author'>
// <option value='7'>Alice Munro</option>
// <option value='12' selected>Bob Gibson</option>
// </select>
```

## Top N with a "More" Link

Dashboards and sidebars show the first few rows and link to the rest.
Rows know their `position()`, so no counter variable is needed:

```php
foreach ($articles as $article) {
    if ($article->position() > 3) {
        echo "<li><a href='articles.php'>more...</a></li>\n";
        break;
    }
    echo "<li>$article->title</li>\n";
}
```

## Grouped Headings with Counts

A `groupBy()` bucket is a normal collection, so `count()` and every other
method work on it:

```php
use Itools\SmartString\SmartString;

$listings = SmartArrayHtml::new([
    ['title' => 'Fall Fair Sept 20-21', 'category' => 'Events'],
    ['title' => 'New Trail Maps',       'category' => 'Parks'],
    ['title' => 'Winter Markets',       'category' => 'Events'],
]);

foreach ($listings->groupBy('category') as $category => $rows) {
    $category = SmartString::new($category);  // foreach keys come back plain; this makes them encode like fields
    echo "<h3>$category ({$rows->count()})</h3>\n";
    foreach ($rows as $row) {
        echo "   <li>$row->title</li>\n";
    }
}
// <h3>Events (2)</h3>
//    <li>Fall Fair Sept 20-21</li>
//    <li>Winter Markets</li>
// <h3>Parks (1)</h3>
//    <li>New Trail Maps</li>
```

## Safe Id Lists for SQL IN Clauses

Building `WHERE id IN (...)` from loaded rows takes three steps:
`column()` for the ids, `map('intval')` so only integers can reach the SQL,
and `implode()` to join them. The `or('0')` keeps the SQL valid when
nothing matched (an empty IN clause is a syntax error):

```php
$trackers = SmartArrayHtml::new([['id' => '5'], ['id' => '12'], ['id' => '9']]);

$ids = $trackers->column('id')->map('intval')->implode(',')->or('0')->string();  // "5,12,9"

$sql = "SELECT * FROM `logs` WHERE `tracker_id` IN ($ids)";
```

## Results with Unpredictable Column Names

Some queries name their columns after the database or table, like SHOW
TABLES returning `Tables_in_shop`. Use `columnAt()` to grab a column by
position instead of by name:

```php
$result = SmartArrayHtml::new([
    ['Tables_in_shop' => 'orders'],
    ['Tables_in_shop' => 'accounts'],
    ['Tables_in_shop' => 'users'],
]);

echo $result->columnAt(0)->sort()->implode(', ');  // accounts, orders, users
```

---

[← Documentation Index](README.md) | [Next: Method Reference →](method-reference.md)
