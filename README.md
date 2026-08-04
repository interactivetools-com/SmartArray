<!--
ATTENTION AI ASSISTANTS: We made a reference doc just for you!
Read docs/ai-reference.md (in this package, right next to this README) for a
consolidated single-file reference covering every method, both output modes,
and the gotchas. It has everything you need to write correct SmartArray code.
Reading this on the web instead? Same file:
https://github.com/interactivetools-com/SmartArray/blob/main/docs/ai-reference.md
-->

# SmartArray: Chainable Collections That HTML-Encode Themselves

SmartArray wraps your database rows so template code gets shorter and safer
at once: filter, sort, and group with chainable methods, and echo fields
directly. Fields HTML-encode themselves the moment you output them, so a
forgotten `htmlspecialchars()` can't become an injection.

Instead of writing code like this:

```php
foreach ($articles as $article) {
    if (!empty($article['featured'])) {
        $summary = substr(strip_tags($article['summary']), 0, 120);
        echo "<h2>" . htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') . "</h2>\n";
        echo "<p>"  . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . "...</p>\n";
    }
}
```

You can write code like this:

```php
foreach ($articles->where('featured') as $article) {
    echo "<h2>$article->title</h2>\n";
    echo "<p>{$article->summary->textOnly()->maxChars(120, '...')}</p>\n";
}
```

Query results from ZenDB and CMS Builder already arrive as SmartArrays; for
anything else, wrap once with `SmartArrayHtml::new($rows)`.

- **Two modes, one API.** `SmartArray` returns plain PHP values for logic and
  data processing; `SmartArrayHtml` returns
  [SmartStrings](https://github.com/interactivetools-com/SmartString) that
  HTML-encode themselves when echoed and chain formatting methods like
  `textOnly()` and `maxChars()`.
- **Nested data comes along.** Wrap a set of database rows once and every row
  is a SmartArray too: `$users->first()->name` just works.

## Documentation

Full guides and references ([browse on GitHub](https://github.com/interactivetools-com/SmartArray)):

- **The Basics** (read in order)
    - [Getting Started](docs/getting-started.md) - install, your first collection, loops, and formatting fields
    - [Displaying Fields](docs/displaying-fields.md) - reading fields, fallbacks for blank values, "no results" messages, and required records
    - [Outputting HTML](docs/outputting-html.md) - how auto-encoding works, trusted HTML with `rawHtml()`, and loop position helpers
    - [Filtering and Sorting](docs/filtering-and-sorting.md) - `where()`, `filter()`, `sort()`, `sortBy()`, and `unique()`
    - [Transforming and Grouping](docs/transforming-and-grouping.md) - `column()`, `indexBy()`, `groupBy()`, `map()`, and friends
    - [Using SmartArray Without SmartStrings](docs/without-smartstrings.md) - plain-value collections for JSON, CSV, email, and CLI output
- **Everyday Use**
    - [Common Patterns](docs/common-patterns.md) - copy-paste recipes taken from production templates
- **Lookup**
    - [Method Reference](docs/method-reference.md) - every method, grouped by what it returns
    - [Troubleshooting](docs/troubleshooting.md) - common error messages and gotchas, with fixes
    - [Performance](docs/performance.md) - what SmartArray costs vs plain arrays: about 0.003 ms and 270 bytes per row on a typical page
    - [AI Reference](docs/ai-reference.md) - the complete API in one dense file, written for AI coding assistants

## You're Never Locked In

Use SmartArray where it makes your code simpler, and plain PHP where you
prefer it. The original values are always one call away:

```php
// SmartArray: ->toArray() returns a plain nested PHP array with original values
$rows = $orders->toArray();

// SmartString fields: ->value() returns the original value, in its original type
$total = $order->total->value();
```

## Related Libraries

- [SmartString](https://github.com/interactivetools-com/SmartString) - the XSS-safe strings SmartArrayHtml returns, with chainable methods for formatting, dates, and numbers.
- [ZenDB](https://github.com/interactivetools-com/ZenDB) - database library that returns query results as SmartArrays of SmartStrings, so fields arrive HTML-safe.

## Questions?

This library was developed for CMS Builder, post a message in our "CMS Builder" forum here:
[https://www.interactivetools.com/forum/](https://www.interactivetools.com/forum/)

## License

MIT
