# SmartArray Documentation

Welcome to the SmartArray docs. SmartArray wraps your database rows so
template code gets shorter and safer at once: filter, sort, and group with
chainable methods, and echo fields directly. Fields HTML-encode themselves
the moment you output them.

New to SmartArray? Read the first two pages in order; together they cover
everything a typical list-plus-detail template needs. The rest are
standalone: open whichever matches your task.

## The Basics (read in order)

1. [Getting Started](getting-started.md) - Install, your first collection, loops, single rows, and formatting fields with SmartString methods.
2. [Displaying Fields](displaying-fields.md) - Reading fields, fallbacks for blank values with `or()`, "no results" messages, and the guards that stop the page for required records.
3. [Outputting HTML](outputting-html.md) - How auto-encoding works, trusted HTML with `rawHtml()`, loop layout with `isFirst()`/`isLast()`/`position()`, and the raw-keys gotcha.
4. [Filtering and Sorting](filtering-and-sorting.md) - Narrowing loaded results with `where()`, `whereNot()`, `whereInList()`, and `filter()`, plus `sortBy()`, `sort()`, and `unique()`.
5. [Transforming and Grouping](transforming-and-grouping.md) - Reshaping results with `column()`, `indexBy()`, `groupBy()`, `map()`, `merge()`, and `implode()`.
6. [Using SmartArray Without SmartStrings](without-smartstrings.md) - Plain-value collections for JSON, CSV, email, and CLI output, converting between modes, and type hints.

## Everyday Use

- [Common Patterns](common-patterns.md) - Copy-paste recipes for real template tasks: related names without a join, select dropdowns, grouped headings, id lists for SQL.

## Lookup

- [Method Reference](method-reference.md) - Every method in one place, grouped by what it returns.
- [Troubleshooting](troubleshooting.md) - Common error messages and behavior gotchas, with fixes.
- [Performance](performance.md) - What SmartArray costs vs plain arrays: about 0.003 ms and 270 bytes per row on a typical page.
- [AI Reference](ai-reference.md) - The complete API in one dense file, written for AI coding assistants.

---

[← Back to main README](../README.md)
