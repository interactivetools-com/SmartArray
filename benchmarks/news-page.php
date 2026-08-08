<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;

/*
 * News-site scenario: 25 records (title ~60 B, summary ~300 B, content ~5 KB).
 * List page outputs title+summary for every record; detail page outputs one
 * record's title + content. Compares SmartArrayHtml against plain arrays with
 * htmlspecialchars(), then probes where the overhead lives (construction,
 * iteration, reads, toArray) and memory. Every number on docs/performance.md
 * comes from this one script.
 *
 * Content is built from corpus units borrowed from SmartString's
 * .github/scripts/speed-page-table.php, where the densities are
 * corpus-measured: in real prose the apostrophe is the dominant special
 * character (with quoted phrases, ~1.3% of characters), and & < > are
 * essentially absent - they live in names and titles, not prose. Each record
 * rotates the unit so rows are distinct and CPU caches aren't flattered by
 * identical strings.
 */

if (extension_loaded('xdebug')) {
    fwrite(STDERR, "xdebug is loaded - it taxes every PHP call several-fold, numbers are invalid\n");
    exit(1);
}
if (!function_exists('opcache_get_status') || opcache_get_status(false) === false) {
    fwrite(STDERR, "note: opcache is off - production runs with it on (see docs/performance.md for the command)\n");
}

#region Test data

// Headline with one apostrophe (quotes in headlines are common; 50 bytes)
const UNIT_TITLE = "Mayor Says 'No' to Downtown Towers Plan This Year ";
// Prose with a quoted phrase and an apostrophe per ~220 chars (~1.3% specials),
// verbatim from SmartString's corpus
const UNIT_PROSE = "The company's third-quarter report shows steady growth in every region, and the board called the results \"very encouraging\" in its letter to shareholders. Management expects the same pace next year as new locations open. ";

/** ~$bytes of text from $unit, rotated by $shift chars so each record differs */
function fromUnit(string $unit, int $bytes, int $shift): string
{
    $shift = $shift % strlen($unit);
    $rot   = substr($unit, $shift) . substr($unit, 0, $shift);
    return substr(str_repeat($rot, intdiv($bytes, strlen($rot)) + 1), 0, $bytes);
}

function makeRecords(int $n): array
{
    $records = [];
    for ($i = 0; $i < $n; $i++) {
        $records[] = [
            'id'      => $i + 1,
            'title'   => fromUnit(UNIT_TITLE, 60, $i * 3),
            'summary' => fromUnit(UNIT_PROSE, 300, $i * 7),
            'content' => fromUnit(UNIT_PROSE, 5000, $i * 13),
        ];
    }
    return $records;
}

#endregion
#region Harness

// The baseline: the standard safe call wrapped once per project (Laravel's e(),
// Twig's escaper, your own helper) - same baseline as SmartString's benchmarks
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bestOf(callable $fn, int $iters): float
{
    $best = INF;
    for ($round = 0; $round < 7; $round++) {
        $t = hrtime(true);
        for ($i = 0; $i < $iters; $i++) {
            $fn();
        }
        $best = min($best, (hrtime(true) - $t) / $iters);
    }
    return $best; // ns per call
}

$GLOBALS['sink'] = 0;

#endregion
#region Page renderers

$records = makeRecords(25);

$plainList = function () use ($records): string {
    $out = '';
    foreach ($records as $r) {
        $out .= '<h2>' . e($r['title']) . "</h2>\n<p>" . e($r['summary']) . "</p>\n";
    }
    return $out;
};
$smartList = function () use ($records): string {
    $sa  = SmartArrayHtml::new($records); // per-request construction, like a DB layer returning results
    $out = '';
    foreach ($sa as $row) {
        $out .= "<h2>$row->title</h2>\n<p>$row->summary</p>\n";
    }
    return $out;
};

$one = $records[0];
$plainDetail = function () use ($one): string {
    return '<h1>' . e($one['title']) . '</h1>' . e($one['content']);
};
$smartDetail = function () use ($one): string {
    $row = SmartArrayHtml::new($one);
    return "<h1>$row->title</h1>$row->content";
};

// SmartString's encoding matches full-flag htmlspecialchars() (ENT_HTML5 writes
// ' as &apos;, the common helper writes &#039;) - verify against the full-flag
// call, time against the common helper, same as SmartString's benchmarks
$fullE      = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');
$verifyList = '';
foreach ($records as $r) {
    $verifyList .= '<h2>' . $fullE($r['title']) . "</h2>\n<p>" . $fullE($r['summary']) . "</p>\n";
}
$verifyDetail = '<h1>' . $fullE($one['title']) . '</h1>' . $fullE($one['content']);
if ($verifyList !== $smartList() || $verifyDetail !== $smartDetail()) {
    fwrite(STDERR, "output mismatch: SmartArrayHtml HTML differs from full-flag htmlspecialchars()\n");
    exit(1);
}
echo "verify: SmartArrayHtml output byte-identical to full-flag htmlspecialchars()\n\n";

#endregion
#region Memory

// Warm-up construction so one-time class loading isn't billed to the first measurement
$warm = SmartArrayHtml::new(makeRecords(1));
unset($warm);

foreach ([25, 1000] as $n) {
    $recs   = makeRecords($n);
    $before = memory_get_usage();
    $sa     = SmartArrayHtml::new($recs);
    $added  = memory_get_usage() - $before;
    $dataKb = strlen(serialize($recs)) / 1024; // rough payload size for context
    printf("memory %5d records (~%s data): SmartArrayHtml adds %6.1f KB (%.0f bytes/record)\n",
        $n, $dataKb >= 1024 ? sprintf('%.1f MB', $dataKb / 1024) : sprintf('%.0f KB', $dataKb),
        $added / 1024, $added / $n);
    unset($sa, $recs);
}
echo "\n";

#endregion
#region Page timings

$sinkList  = function (callable $fn): callable {
    return function () use ($fn): void {
        $GLOBALS['sink'] += strlen($fn());
    };
};

$a = bestOf($sinkList($plainList), 2000);
$b = bestOf($sinkList($smartList), 2000);
printf("list page   (25 rows, 2 fields): plain %7.4f ms, smart %7.4f ms, overhead %+8.4f ms (%.2fx)\n",
    $a / 1e6, $b / 1e6, ($b - $a) / 1e6, $b / $a);
$listOverheadNs = $b - $a;

$a = bestOf($sinkList($plainDetail), 5000);
$b = bestOf($sinkList($smartDetail), 5000);
printf("detail page (1 row, 5 KB body) : plain %7.4f ms, smart %7.4f ms, overhead %+8.4f ms (%.2fx)\n",
    $a / 1e6, $b / 1e6, ($b - $a) / 1e6, $b / $a);

// Same pages with no encoding on either side: plain arrays echoed raw vs plain SmartArray (raw mode)
$plainListRaw = function () use ($records): void {
    $out = '';
    foreach ($records as $r) {
        $out .= '<h2>' . $r['title'] . "</h2>\n<p>" . $r['summary'] . "</p>\n";
    }
    $GLOBALS['sink'] += strlen($out);
};
$smartListRaw = function () use ($records): void {
    $sa  = SmartArray::new($records);
    $out = '';
    foreach ($sa as $row) {
        $out .= "<h2>$row->title</h2>\n<p>$row->summary</p>\n";
    }
    $GLOBALS['sink'] += strlen($out);
};
$a = bestOf($plainListRaw, 2000);
$b = bestOf($smartListRaw, 2000);
printf("list page, no encoding         : plain %7.4f ms, smart %7.4f ms, overhead %+8.4f ms (%.2fx)\n",
    $a / 1e6, $b / 1e6, ($b - $a) / 1e6, $b / $a);

$plainDetailRaw = function () use ($one): void {
    $GLOBALS['sink'] += strlen('<h1>' . $one['title'] . '</h1>' . $one['content']);
};
$smartDetailRaw = function () use ($one): void {
    $row = SmartArray::new($one);
    $GLOBALS['sink'] += strlen("<h1>$row->title</h1>$row->content");
};
$a = bestOf($plainDetailRaw, 5000);
$b = bestOf($smartDetailRaw, 5000);
printf("detail page, no encoding       : plain %7.4f ms, smart %7.4f ms, overhead %+8.4f ms (%.2fx)\n",
    $a / 1e6, $b / 1e6, ($b - $a) / 1e6, $b / $a);

// Data processing, no output or encoding: create the record set and read 2 fields per row
$plainRaw = function () use ($records): void {
    foreach ($records as $r) {
        $GLOBALS['sink'] += strlen($r['title']) + strlen($r['summary']);
    }
};
$smartRaw = function () use ($records): void {
    $sa = SmartArray::new($records);
    foreach ($sa as $row) {
        $GLOBALS['sink'] += strlen($row->title) + strlen($row->summary);
    }
};
$a = bestOf($plainRaw, 5000);
$b = bestOf($smartRaw, 5000);
printf("raw loop    (create + 50 reads): plain %7.4f ms, smart %7.4f ms, overhead %+8.4f ms\n\n",
    $a / 1e6, $b / 1e6, ($b - $a) / 1e6);
$rawOverheadNs = $b - $a;

#endregion
#region Cost split

$constructNs = bestOf(function () use ($records): void {
    $GLOBALS['sink'] += SmartArray::new($records)->count();
}, 5000);

// Trusted construction: database rows are uniform, so validation is skipped
$constructDbNs = bestOf(function () use ($records): void {
    $GLOBALS['sink'] += SmartArray::fromDatabaseRows($records)->count();
}, 5000);

$prebuilt  = SmartArray::new($records);
$foreachNs = bestOf(function () use ($prebuilt): void {
    foreach ($prebuilt as $row) {
        $GLOBALS['sink'] += $row ? 1 : 0;
    }
}, 5000);

$firstRow = $prebuilt->first();
$readNs   = bestOf(function () use ($firstRow): void {
    $GLOBALS['sink'] += strlen($firstRow->title);
}, 100_000);

$toArraySetNs = bestOf(function () use ($prebuilt): void {
    $GLOBALS['sink'] += count($prebuilt->toArray());
}, 5000);
$toArrayRowNs = bestOf(function () use ($firstRow): void {
    $GLOBALS['sink'] += count($firstRow->toArray());
}, 100_000);

printf("cost split (25-row record set):\n");
printf("  construct from plain array   %9.6f ms\n", $constructNs / 1e6);
printf("  fromDatabaseRows(), trusted  %9.6f ms\n", $constructDbNs / 1e6);
printf("  foreach over all rows        %9.6f ms\n", $foreachNs / 1e6);
printf("  read one field (\$row->title) %9.6f ms\n", $readNs / 1e6);
printf("  toArray() on the record set  %9.6f ms\n", $toArraySetNs / 1e6);
printf("  toArray() on one flat row    %9.6f ms\n\n", $toArrayRowNs / 1e6);

printf("millisecond test (how many before SmartArray overhead adds 1 ms):\n");
printf("  rendered list pages (25 rows each): %6.0f\n", 1_000_000 / $listOverheadNs);
printf("  raw 25-row record sets:             %6.0f\n", 1_000_000 / $rawOverheadNs);
printf("  rows through construction:          %6.0f\n", 1_000_000 / ($constructNs / 25));

#endregion
