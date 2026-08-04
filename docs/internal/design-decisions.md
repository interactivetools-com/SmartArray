# SmartArray Design Decisions

Settled decisions with their rationale. Check here before proposing a feature,
a rename, or a refactor: if it's listed below, it was already debated.
Decisions can be reopened, but reopen them against the reasons recorded here,
not from scratch.

Only the road-not-taken half lives here; current behavior is self-documenting
in signatures, docblocks, the changelog, and tests.

- **`SharedHelpers.php` is a twin of SmartString's copy** (identical except
  the namespace line), so fixes port by copying the file - never prune or
  edit one without the other, including branches only one library uses.

- **Missing keys and empty lookups return SmartNull, not an empty
  SmartArray**, because the caller may treat the result as a value or as a
  collection and SmartNull supports both: `->value()`/`->or()`/`== ''` on
  the value side, `foreach`/`count()`/`keys()` on the array side. An empty
  SmartArray from `first()` would fatal on `->value()`.

- **SmartNull propagates through SmartString transforms (2026-08-04).** In
  HTML mode, `__call` tries public SmartString methods first and classifies
  by result: a still-null result means nothing was produced, so the
  SmartNull itself returns and the chain stays open for a value or a
  collection ending. `map()` returns the SmartNull without running its
  callback, since a missing key has no value to pass (a NULL value in an
  existing key still runs it). Rejected alternatives: renaming `map()` back
  to `apply()` (dodges one collision but keeps the name-based routing that
  broke `map`/`htmlEncode`/`set`, and the next shared name re-breaks it);
  routing shared names to SmartString by class ("string wins" fixes the
  three methods but breaks `first()->map()` collection chains the same
  way); running map's callback with null, the v2 `apply()` behavior (typed
  callbacks throw TypeError, side effects fire for rows that don't exist,
  and a value-returning callback turns a collection-shaped SmartNull into
  a SmartString mid-chain). The classifier is `isNull()`, not
  `isMissing()`: `isMissing()` counts `""`, which would discard a produced
  empty string and wrongly re-fire `ifNull()` later in the chain. The
  `isPublic()` reflection check exists because `method_exists()` reports
  private methods - that false positive is how `htmlEncode()` broke.

- **Missing-key warnings are rows-only (2026-08-04).** Key access warns
  only on rows inside a result set, where keys are column names and a miss
  is almost always a typo; top-level, derived (indexBy()/column() maps),
  and standalone arrays render blank silently so fallbacks chain cleanly.
  Rejected alternatives: reintroducing get() as the no-warn reader (second
  read syntax), dropping the warning entirely (loses the typo net), and a
  data-keyed flag on collections (loses the standalone-record net for
  little gain over rows-only). Method-argument checks (where('typo'))
  still warn everywhere.

- **Single-argument `where($field)`/`whereNot($field)` follow PHP's
  `empty()` rule**, not the library's missing (null or "") convention -
  one consistent truthiness rule with filter()'s no-callback form, so
  `"0"` is empty and `"0.0"` is not. The two forms are strict complements:
  every row lands in exactly one.

- **foreach keys are never SmartStrings, even in HTML mode.**
  getIterator() wraps values but yields keys raw. Yielding encoded keys
  was rejected: it breaks lookups, ===, and array functions. The docs
  answer is keys() plus explicit encoding when outputting keys from user
  data.

- **`get()`/`set()` deprecated in favor of property syntax (2026-08-04).**
  They were a second documented way to read and write every element, so
  the docs had to teach both. One form for reads (`$row->name`), one for
  writes (`$row->name = $value`), and property access is 1.1-1.6x faster.
  `get('')`/`set('', $value)` survive as the only way to reach an
  empty-string key - the brace form is a fatal error for an empty
  property name.

- **`each()` and `sprintf()` deprecated (2026-08-04).** `each()` had no
  measured uses and a plain foreach is clearer and faster. `sprintf()`
  was a second formatting syntax whose only found uses were inside CMS
  Builder; `map()` with an inline format string covers it.

- **Raw-mode misses don't answer SmartString methods (2026-08-04).** On a
  raw array, `$row->missing->or('n/a')` throws instead of returning an
  HTML-encoding SmartString. Chaining `->or()` on a stored raw string was
  already fatal, so a miss was the one path that silently produced
  encoded output in a raw array. HTML mode still delegates, so chains
  through a missing key keep working there; raw fallbacks use `??`.

- **Runtime messages name the library, not a URL (2026-08-04).** The
  can't-convert-to-string warning and undefined-method errors say "the
  SmartArray docs" rather than linking. These can render on
  private-labeled production sites, where a vendor URL in the page is not
  ours to put there.
