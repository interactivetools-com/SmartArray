# SmartArray Design Decisions

Settled decisions with their rationale. Check here before proposing a feature,
a rename, or a refactor: if it's listed below, it was already debated.
Decisions can be reopened, but reopen them against the reasons recorded here,
not from scratch.

- **`SharedHelpers.php` is a twin of SmartString's copy** (identical except
  the namespace line), so fixes port by copying the file - never prune or
  edit one without the other, including branches only one library uses.
