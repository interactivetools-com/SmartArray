# Performance: What SmartArray Costs vs Plain Arrays

At worst, wrapping a 25-row query
result and rendering it costs about 10 microseconds (0.00001 s) more than the
same page written by hand with plain arrays and manual HTML encoding; at best
it comes out ahead, because SmartString encodes long text faster than PHP's
built-in encoder (nearly 2x on the detail page below). Memory overhead is
under 300 bytes per row no matter how large the fields are.

All times on this page are in milliseconds (ms), thousandths of a second.
For scale: response-time research puts the threshold where people start to
notice a delay at about 100 ms, and around 200 ms a page starts to feel
slow. For comparison, even a fast database query costs over ten times
more than wrapping its results, and a typical page's queries together
cost hundreds of times more.

The rest of this page is the measurements behind those claims, and the one
case where the overhead is worth thinking about.

Contents:

- [What a Page Costs](#what-a-page-costs)
- [Memory](#memory)
- [When to Care](#when-to-care)
- [Reproducing the Numbers](#reproducing-the-numbers)

## What a Page Costs

A news site with 25 records (60-char title, 300-char summary, 5KB content),
rendered two ways: SmartArrayHtml, and the same page written with plain
arrays and an `htmlspecialchars()` helper. Field text matches real-prose
character densities (shared with SmartString's benchmark corpus), and both
versions are verified to produce byte-identical HTML before timing:

| Scenario                                       | Plain array | SmartArray | Difference         |
|------------------------------------------------|-------------|------------|--------------------|
| List page (25 rows, encoded title+summary)     | 0.0213 ms   | 0.0309 ms  | +0.0096 ms (1.45x) |
| Detail page (1 row, encoded 5KB body)          | 0.0094 ms   | 0.0050 ms  | **nearly 2x faster** |
| Raw loop (plain SmartArray, create + 50 reads) | 0.0006 ms   | 0.0077 ms  | +0.0072 ms         |

SmartArray times include constructing the object from the plain records
array, the same work the database layer does when it returns results. The
first two rows use SmartArrayHtml with encoding on. The raw loop has no
output or encoding at all: it uses plain `SmartArray` (no HTML mode),
where field reads return plain strings and no SmartString objects are
created, so data-processing code skips the encoding layer entirely.

**Construction is where the time goes.** Render the same pages with no
encoding on either side (plain SmartArray against plain arrays echoed
raw) and the list page still measures +0.007 ms. HTML mode adds about
0.003 ms on top for the 50 SmartString objects, and on longer fields
SmartString's faster encoding turns that around, which is why the detail
page wins.

To put the list-page row in perspective: to lose a single millisecond on
one page load, your code would have to build and render that 25-row list
about 100 times. Same math for the raw-loop row: wrapping one 25-row query
result in a SmartArray and looping over it costs 0.0072 ms more than the
plain array, so a single request would have to create about 140 separate
SmartArrays, 25 rows each, and loop over all of them - 3,500 rows - before
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
| 25 news records    | ~133 KB      | +6.4 KB         | ~260 bytes |
| 1,000 news records | ~5.2 MB      | +247 KB         | ~250 bytes |

Field values are never copied: PHP strings are reference-counted, so a 5KB
content field is shared between the plain array and the SmartArray that
wraps it. The per-record overhead is the row object itself, so the bytes
per row are the constant, not a percentage: on these ~5 KB news records it
works out to 5%, on records with a 50 KB body it would be 0.5%, and on
lean three-column rows it could exceed 100% - while staying the same few
hundred bytes each time.

## When to Care

Almost never. Every number above is a fraction of a fraction of the
smallest delay a person can perceive. Write the code that's simplest to
read and work with - for query results rendered as HTML, that's
SmartArrayHtml - and don't spend a line of it dodging these costs. If a page is genuinely
slow, benchmark it and fix what the benchmark points at: it will be a
query, a missing index, or an API call, not the array wrapper.

The exception is when you're deliberately optimizing for milliseconds - a
rendering budget of a few ms, or one request processing tens of thousands
of rows. For that case, here is the cost breakdown. Construction is
nearly all of it; everything after is close to free:

| Operation (25 rows, plain SmartArray) | Cost        |
|---------------------------------------|-------------|
| Construct from plain records array    | 0.0043 ms   |
| Construct via `fromDatabaseRows()`    | 0.0033 ms   |
| foreach over all rows                 | 0.0008 ms   |
| Read a field (`$row->title`)          | 0.000058 ms |
| `toArray()` on the record set         | 0.0009 ms   |
| `toArray()` on one flat row           | 0.000033 ms |

ZenDB constructs its result sets with `fromDatabaseRows()`, the faster
construct row above: database rows are uniform (same columns in every row,
plain scalar values), so it skips the checks the general constructor runs
on arbitrary input and comes out about 20% faster. The method is internal
(ZenDB plumbing, not a public API), so use `new()` in your own code.

Internals that keep those numbers small: all-scalar rows are built by
cloning a shared template and assigning their data in one copy-on-write
step; `foreach` uses a C-level `ArrayIterator` whenever no SmartString
wrapping can happen (raw mode, or record sets where every value is a row);
`toArray()` hands back internal data as-is when there are no child rows
to convert; and builtin calls like `is_scalar()` and `count()` are
imported with `use function`, so they compile to single opcodes instead
of runtime name lookups.

Two things follow from construction being the whole cost:

- **It's eager, per row fetched.** Query 500 rows to show 10 and you pay
  for 500. LIMIT in the query beats any amount of avoiding SmartArray.
- **Hot loops: unwrap once.** A report loop touching every field thousands
  of times can call `->toArray()` first (0.000033 ms on a flat row) and
  loop the plain array.

## Reproducing the Numbers

Every number on this page comes from one script, which builds the test
data, verifies both versions produce byte-identical HTML, and then times
them. The numbers above are from a dedicated Linux x64 server (Intel Xeon
E-2386G) on PHP 8.5 with opcache on and JIT off. The list-page gap tracks
how fast the platform's own `htmlspecialchars()` runs: on PHP 8.1, or on a
laptop, the plain-array side is slower and the gap narrows. Run it on your
own machine with opcache on and xdebug off:

```bash
php -d opcache.enable_cli=1 -d xdebug.mode=off benchmarks/news-page.php
```

---

[← Documentation Index](README.md) | [← Prev: Troubleshooting](troubleshooting.md) | [Next: AI Reference →](ai-reference.md)
