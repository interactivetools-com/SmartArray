# Benchmarks

`news-page.php` measures a realistic 25-record news page with SmartArrayHtml
against plain arrays with `htmlspecialchars()`; every number in
[docs/performance.md](../docs/performance.md) comes from it (the run command
is on that page).

SmartArray's per-operation speed benchmarks live in the SmartString
repository: one shared matrix covers both libraries, since SmartArray's hot
paths (field access, iteration) end in SmartString output.

In [interactivetools-com/SmartString](https://github.com/interactivetools-com/SmartString):

- **Results**: `.github/scripts/speed-results.md` - SmartArray tests have
  `arr-` prefixed ids (property access, `get()` vs property, foreach iteration)
- **Scripts**: `.github/scripts/speed-probe.php` (per-cell measurements) and
  `speed-merge.php` (combines cells into the results grid)
- **Workflow**: `.github/workflows/speed-matrix.yml` - dispatch it from the
  Actions tab to re-run; 25 cells (PHP 8.1-8.5 x linux-x64/linux-arm/
  windows-x64/macos-x64/macos-arm)
