# Performance: What SmartArray Costs vs Plain Arrays

SmartArray is extremely fast by default. At worst, wrapping a 25-row query
result and rendering it costs about 0.003 milliseconds more than the same
page written by hand with plain arrays and manual HTML encoding; at best
it comes out ahead, because SmartString often encodes faster than PHP's
built-in encoder (2.5x on the long-text detail page below). Memory
overhead is about 300 bytes per row no matter how large the fields are.

All times on this page are in milliseconds (ms), thousandths of a second.
For scale: response-time research puts the threshold where people start to
notice a delay at about 100 ms, and around 200 ms a page starts to feel
slow. For comparison, even a fast database query costs over ten times
more than wrapping its results, and a typical page's queries together
cost hundreds of times more.

The rest of this page is the measurements behind those claims, and the one
case where the overhead is worth thinking about.

## What a Page Costs

A news site with 25 records (60-char title, 300-char summary, 5KB content),
rendered two ways: SmartArrayHtml, and the same page written with plain
arrays and an `htmlspecialchars()` helper. Field text matches real-prose
character densities (shared with SmartString's benchmark corpus), and both
versions are verified to produce byte-identical HTML before timing:

| Scenario                                       | Plain array | SmartArray | Difference         |
|------------------------------------------------|-------------|------------|--------------------|
| List page (25 rows, encoded title+summary)     | 0.0277 ms   | 0.0305 ms  | +0.0028 ms (1.10x) |
| Detail page (1 row, encoded 5KB body)          | 0.0123 ms   | 0.0049 ms  | 2.5x FASTER        |
| Raw loop (plain SmartArray, create + 50 reads) | 0.0004 ms   | 0.0079 ms  | +0.0075 ms         |

SmartArray times include constructing the object from the plain records
array, the same work the database layer does when it returns results. The
first two rows use SmartArrayHtml with encoding on. The raw loop has no
output or encoding at all: it uses plain `SmartArray` (no HTML mode),
where field reads return plain strings and no SmartString objects are
created, so data-processing code skips the encoding layer entirely.

**Encoding is not where the time goes.** Render the same pages with no
encoding on either side (plain SmartArray against plain arrays echoed
raw) and the list page still measures +0.0073 ms: the overhead is
construction either way, and SmartString encodes as fast as or faster
than hand-encoding, so HTML mode adds nothing on net.

To put the list-page row in perspective: to lose a single millisecond on
one page load, your code would have to build and render that 25-row list
about 300 times. Same math for the raw-loop row: wrapping one 25-row query
result in a SmartArray and looping over it costs 0.0075 ms more than the
plain array, so a single request would have to create 135 separate
SmartArrays, 25 rows each, and loop over all of them - 3,375 rows - before
the total penalty reached one millisecond.

**Why the detail page is faster.** SmartString checks whether text is
plain ASCII and, when it is, swaps the five special characters with
`str_replace()` instead of running `htmlspecialchars()`'s full UTF-8
scan. On multi-KB content fields that saves more than construction costs.
See SmartString's
[performance page](https://github.com/interactivetools-com/SmartString/blob/main/docs/performance.md)
for the encoding measurements across platforms.

## Memory

| Data               | Payload size | SmartArray adds | Per record |
|--------------------|--------------|-----------------|------------|
| 25 news records    | ~133 KB      | +7.8 KB         | ~320 bytes |
| 1,000 news records | ~5.2 MB      | +294 KB         | ~301 bytes |

Field values are never copied: PHP strings are reference-counted, so a 5KB
content field is shared between the plain array and the SmartArray that
wraps it. The per-record overhead is the row object itself, so the bytes
per row are the constant, not a percentage: on these ~5 KB news records it
works out to 6%, on records with a 50 KB body it would be 0.6%, and on
lean three-column rows it could exceed 100% - while staying the same few
hundred bytes each time.

## When to Care

Almost never. Every number above is a fraction of a fraction of the
smallest delay a person can perceive. Write the code that's simplest to
read and work with - for query results headed to HTML, that's SmartArray -
and don't spend a line of it dodging these costs. If a page is genuinely
slow, benchmark it and fix what the benchmark points at: it will be a
query, a missing index, or an API call, not the array wrapper.

The exception is when you're deliberately optimizing for milliseconds - a
rendering budget of a few ms, or one request processing tens of thousands
of rows. For that case, here is where the cost lives. Construction is
nearly all of it; everything after is close to free:

| Operation (25 rows, plain SmartArray) | Cost        |
|---------------------------------------|-------------|
| Construct from plain records array    | 0.0053 ms   |
| Construct via `fromDatabaseRows()`    | 0.0037 ms   |
| foreach over all rows                 | 0.0007 ms   |
| Read a field (`$row->title`)          | 0.000046 ms |
| `toArray()` on the record set         | 0.0006 ms   |
| `toArray()` on one flat row           | 0.000026 ms |

ZenDB constructs its result sets with `fromDatabaseRows()`, the faster
construct row above: database rows are uniform (same columns in every row,
plain scalar values), so it skips the checks the general constructor runs
on arbitrary input and comes out about a third faster.

Internals that keep those numbers small: all-scalar rows are built by
cloning a shared template and assigning their data in one copy-on-write
step; `foreach` uses a C-level `ArrayIterator` whenever no SmartString
wrapping can happen (raw mode, or record sets where every value is a row);
and `toArray()` hands back internal data as-is when there are no child
rows to convert.

Two things follow from construction being the whole cost:

- **It's eager, per row fetched.** Query 500 rows to show 10 and you pay
  for 500. LIMIT in the query beats any amount of avoiding SmartArray.
- **Hot loops: unwrap once.** A report loop touching every field thousands
  of times can call `->toArray()` first (0.000026 ms on a flat row) and
  loop the plain array.

## Reproducing the Numbers

Every number on this page comes from one script, which builds the test
data, verifies both versions produce byte-identical HTML, and then times
them. Local runs are direction checks; numbers move a few percent between
runs and more between machines. Run with opcache on and xdebug off:

```bash
php -n -d zend_extension=opcache -d opcache.enable_cli=1 benchmarks/news-page.php
```

---

[← Documentation Index](README.md)
