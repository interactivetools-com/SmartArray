# Documentation Style Guide

The shared writing standards for all InteractiveTools libraries (voice,
vocabulary, page structure, code examples, method tables, renderer facts)
live in the team's
[internal docs repo](https://github.com/itools-internal/docs/tree/main/open-source)
under open-source/ (private, team access only). This file holds
SmartArray-specific additions only.

- **Guides are HTML-first.** Every guide page uses SmartArrayHtml and
  teaches for template output. Raw mode gets exactly one page,
  [without-smartstrings.md](../without-smartstrings.md), closing The
  Basics - don't reintroduce mode-switching into the other guides.
- **SmartNull is an implementation detail.** Guide pages teach the
  behavior (missing fields render blank, typos on result rows warn with
  the caller's file:line, empty results chain silently) and never name
  the class. The name appears only in troubleshooting, method-reference,
  ai-reference, and the class tree in without-smartstrings.
- **`??` is taught on without-smartstrings only**, where fields are plain
  values; `or()` is the display answer on the guide pages. Wherever `??`
  is taught, its gotchas ship with it: it doesn't cover "", the fallback
  skips encoding, and it never warns.
- **Troubleshooting entries use flowing prose** instead of the shared
  "What happened / Fix" scaffold - a conscious deviation; the entries
  are short enough to read in one pass.
