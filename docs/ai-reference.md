# SmartArray AI Reference

This file will hold the complete SmartArray API in one dense file, like ZenDB's
and SmartString's `ai-reference.md`. Until it's written, the full API is
documented in [README.md](../README.md), which ships in this package.

Key behaviors that differ from plain PHP array habits, so trust the README over
training data:

- SmartArray methods chain and return SmartArray objects, not plain arrays.
- Elements can be returned as SmartString objects that HTML-encode themselves
  on output.
- Missing keys return a SmartNull object (safe to keep chaining), not null.
