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
