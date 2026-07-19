<?php
declare(strict_types=1);

/**
 * Merge per-cell speed-probe JSON artifacts into one comparison grid.
 *
 *     php speed-merge.php probes/*.json >> "$GITHUB_STEP_SUMMARY"
 *
 * Rows = tests, columns = cells (os/arch php), values = "B vs A" ratio.
 * Ratio > 1.00 means the B side (the candidate) is faster; >= 1.05 bold,
 * <= 0.95 flagged as a regression. CORPUS_FAIL and missing cells are called out.
 */

$files = array_slice($argv, 1);
if ($files === []) {
    fwrite(STDERR, "Usage: php speed-merge.php probe-*.json\n");
    exit(1);
}

$cells = []; // label => decoded json
foreach ($files as $file) {
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data) || !isset($data['php'], $data['os'], $data['tests'])) {
        fwrite(STDERR, "skipping unreadable probe file: $file\n");
        continue;
    }
    $phpShort = preg_replace('/^(\d+\.\d+)\..*$/', '$1', $data['php']);
    $arch     = str_contains((string)$data['arch'], 'arm') || str_contains((string)$data['arch'], 'aarch') ? 'arm' : 'x64';
    $label    = strtolower($data['os']) . "-$arch $phpShort";
    $cells[$label] = $data;
}
if ($cells === []) {
    fwrite(STDERR, "no valid probe files\n");
    exit(1);
}
ksort($cells);

// Collect the union of test ids in first-seen order, and A/B labels for the legend
$testIds = [];
$legend  = [];
foreach ($cells as $data) {
    foreach ($data['tests'] as $id => $t) {
        if (!in_array($id, $testIds, true)) {
            $testIds[] = $id;
            $legend[$id] = [$t['a_label'] ?? '?', $t['b_label'] ?? '?'];
        }
    }
}

echo "## Speed matrix: B-vs-A ratios (>1.00 = candidate faster)\n\n";

// Corpus status line: any failure anywhere is a headline, not a footnote
$corpusBad = [];
foreach ($cells as $label => $data) {
    foreach (($data['corpus']['encoders'] ?? []) as $fn => $ok) {
        if (!$ok) {
            $corpusBad[] = "$label:$fn";
        }
    }
}
echo $corpusBad === []
    ? "Correctness: every encoder byte-identical on every cell.\n\n"
    : "**CORRECTNESS FAILURES: " . implode(', ', $corpusBad) . "** - affected timings withheld.\n\n";

// Grid
echo '| test |';
foreach (array_keys($cells) as $label) {
    echo " $label |";
}
echo "\n|---|" . str_repeat('---|', count($cells)) . "\n";
foreach ($testIds as $id) {
    echo "| $id |";
    foreach ($cells as $data) {
        $t = $data['tests'][$id] ?? null;
        if ($t === null) {
            echo ' - |';
        } elseif (($t['verdict'] ?? '') === 'CORPUS_FAIL') {
            echo ' **FAIL** |';
        } else {
            $r    = (float)$t['ratio'];
            $text = sprintf('%.2fx', $r);
            echo ' ' . ($r >= 1.05 ? "**$text**" : ($r <= 0.95 ? "$text (slower)" : $text)) . ' |';
        }
    }
    echo "\n";
}

// Legend
echo "\n<details><summary>Test legend (A vs B)</summary>\n\n";
foreach ($legend as $id => [$a, $b]) {
    echo "- **$id**: $a vs $b\n";
}
echo "\n</details>\n";
