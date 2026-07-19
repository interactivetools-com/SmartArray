<?php
declare(strict_types=1);

/**
 * Speed matrix probe: paired A/B benchmarks of every hot-path optimization
 * candidate, designed for noisy CI runners.
 *
 *     php speed-probe.php [--json=out.json] [--filter=id1,id2] [--scale=1.0] [--skip-corpus]
 *
 * Design rules (see __speed-matrix-plan.md):
 * - Every number is a ratio from interleaved A/B pairs in one process (A,B,A,B...),
 *   best-of-7 per side, so shared-VM speed wobble cancels out.
 * - Input strings are runtime-built and cycled from pools of >= 64 distinct values:
 *   literals are interned and PCRE stamps a UTF-8-validity flag on zend_strings,
 *   both of which flatter repeated-literal loops.
 * - Every result is consumed (strlen accumulator) so dead code can't be eliminated.
 * - Encoding variants must pass the byte-identity corpus BEFORE timing; a failing
 *   encoder reports FAIL and its timings are withheld.
 *
 * Self-contained: no composer, PHP 8.1+ syntax, no extensions beyond pcre/spl.
 * Variant classes are minimal mimics of the real SmartString/SmartArray code shape;
 * they exist to compare code SHAPES, not to test the real classes (the real-class
 * A/B happens locally; see the plan file).
 */

require __DIR__ . '/speed-corpus.php';

const FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5;

error_reporting(E_ALL);
ini_set('display_errors', '1');

//region Encoding variants (function level)

// Byte-mode identity gate: no byte the full-flag encoder would touch -> return as-is.
// Char class = complement of {\t \n \f \r, 0x20-0x7E minus " & ' < >}.
const GATE_CHANGEABLE = '/[\x00-\x08\x0B\x0E-\x1F"&\'<>\x7F-\xFF]/';
// Anything outside {\t \n \f \r, all printable ASCII} - specials allowed (they encode cleanly).
const GATE_NON_ASCII  = '/[\x00-\x08\x0B\x0E-\x1F\x7F-\xFF]/';
// preg equivalent of the tier-1 set as a negated class (hybrid encoder, PHP <= 8.3 gate)
const GATE_NOT_TIER1  = '/[^\x09\x0A\x0C\x0D\x20\x21\x23-\x25\x28-\x3B\x3D\x3F-\x7E]/';

const SPECIALS_FROM = ['&', '<', '>', '"', "'"];
const SPECIALS_TO   = ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'];

/** strspn charsets for the PHP 8.4+ gates (linear-scan strspn; catastrophic before 8.4) */
function tier1_charset(): string
{
    $set = "\t\n\x0C\r";
    for ($b = 0x20; $b <= 0x7E; $b++) {
        $c = chr($b);
        if ($c === '&' || $c === '<' || $c === '>' || $c === '"' || $c === "'") {
            continue;
        }
        $set .= $c;
    }
    return $set;
}
define('TIER1', tier1_charset());
define('TIER2', TIER1 . '&<>"\'');

/** U+... code points ENT_DISALLOWED|ENT_HTML5 substitutes (unicode third tier) */
function disallowed_re(): string
{
    $cls = '\x00-\x08\x0B\x0E-\x1F\x7F-\x9F\x{FDD0}-\x{FDEF}';
    for ($p = 0; $p <= 0x10; $p++) {
        $cls .= sprintf('\x{%X}-\x{%X}', $p * 0x10000 + 0xFFFE, $p * 0x10000 + 0xFFFF);
    }
    return "/[$cls]/u";
}
define('DISALLOWED_RE', disallowed_re());

function enc_baseline(string $s): string
{
    return htmlspecialchars($s, FLAGS, 'UTF-8');
}

function enc_gate(string $s): string
{
    if (preg_match(GATE_CHANGEABLE, $s) === 0) {
        return $s;
    }
    return htmlspecialchars($s, FLAGS, 'UTF-8');
}

function enc_two_tier(string $s): string
{
    if (preg_match(GATE_CHANGEABLE, $s) === 0) {
        return $s;
    }
    if (preg_match(GATE_NON_ASCII, $s) === 0) {
        return str_replace(SPECIALS_FROM, SPECIALS_TO, $s);
    }
    return htmlspecialchars($s, FLAGS, 'UTF-8');
}

function enc_three_tier(string $s): string
{
    if (preg_match(GATE_CHANGEABLE, $s) === 0) {
        return $s;
    }
    if (preg_match(GATE_NON_ASCII, $s) === 0) {
        return str_replace(SPECIALS_FROM, SPECIALS_TO, $s);
    }
    if (preg_match(DISALLOWED_RE, $s) === 0) { // false (invalid UTF-8) falls through
        return str_replace(SPECIALS_FROM, SPECIALS_TO, $s);
    }
    return htmlspecialchars($s, FLAGS, 'UTF-8');
}

function enc_hybrid_strspn(string $s): string
{
    $len = strlen($s);
    if (strspn($s, TIER1) === $len) {
        return $s;
    }
    if (strspn($s, TIER2) === $len) {
        return str_replace(SPECIALS_FROM, SPECIALS_TO, $s);
    }
    return htmlspecialchars($s, FLAGS, 'UTF-8');
}

/** Version-adaptive: constant condition, opcache deletes the dead branch. */
function enc_adaptive(string $s): string
{
    return PHP_VERSION_ID >= 80400 ? enc_hybrid_strspn($s) : enc_two_tier($s);
}

/** Guarded per-request memo stacked behind the gate (verified REJECT; matrix confirms cross-platform). */
function enc_memo(string $s): string
{
    static $cache = [];
    if (preg_match(GATE_CHANGEABLE, $s) === 0) {
        return $s;
    }
    if (strlen($s) <= 256) {
        if (isset($cache[$s])) {
            return $cache[$s];
        }
        if (count($cache) < 5000) {
            return $cache[$s] = htmlspecialchars($s, FLAGS, 'UTF-8');
        }
    }
    return htmlspecialchars($s, FLAGS, 'UTF-8');
}

//endregion
//region SmartString-shaped classes (storage / construction / output variants)

final class SSCurrent // today's shape: typed readonly property, body assignment
{
    private readonly string|int|float|bool|null $rawData;
    public function __construct(string|int|float|bool|null $value)
    {
        $this->rawData = $value;
    }
    public function __toString(): string
    {
        return htmlspecialchars((string)$this->rawData, FLAGS, 'UTF-8');
    }
    public function htmlEncode(): string
    {
        return htmlspecialchars((string)$this->rawData, FLAGS, 'UTF-8');
    }
}

final class SSPromo // constructor property promotion (expected: identical speed)
{
    public function __construct(private readonly string|int|float|bool|null $rawData)
    {
    }
    public function __toString(): string
    {
        return htmlspecialchars((string)$this->rawData, FLAGS, 'UTF-8');
    }
}

final class SSUntyped // untyped non-readonly property, typed ctor param kept
{
    private $rawData;
    public function __construct(string|int|float|bool|null $value)
    {
        $this->rawData = $value;
    }
    public function __toString(): string
    {
        return htmlspecialchars((string)$this->rawData, FLAGS, 'UTF-8');
    }
}

final class SSGate // identity gate inside __toString (adoption candidate #1)
{
    private readonly string|int|float|bool|null $rawData;
    public function __construct(string|int|float|bool|null $value)
    {
        $this->rawData = $value;
    }
    public function __toString(): string
    {
        $text = (string)$this->rawData;
        if (preg_match(GATE_CHANGEABLE, $text) === 0) {
            return $text;
        }
        return htmlspecialchars($text, FLAGS, 'UTF-8');
    }
}

//endregion
//region SmartArray-shaped classes (access-path variants)

final class NullMini
{
}

/** Today's shape: __get -> getElement -> offsetExists, two hash lookups, union returns */
final class ArrCurrent
{
    private array $data;
    private bool $useSmartStrings = true;
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    public function __get(string $key): static|NullMini|SSCurrent|string|int|float|bool|null
    {
        return $this->getElement($key);
    }
    private function getElement(string $key): static|NullMini|SSCurrent|string|int|float|bool|null
    {
        if ($this->offsetExists($key)) {
            $value = $this->data[$key];
            return $this->useSmartStrings && !$value instanceof self
                ? new SSCurrent($value)
                : $value;
        }
        return new NullMini();
    }
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }
}

/** Tuned: folded into __get, single ?? lookup, is_object gate; union return kept */
final class ArrTunedUnion
{
    private array $data;
    private bool $useSmartStrings = true;
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    public function __get(string $key): static|NullMini|SSCurrent|string|int|float|bool|null
    {
        $value = $this->data[$key] ?? null;
        if ($value !== null || array_key_exists($key, $this->data)) {
            return $this->useSmartStrings && !is_object($value)
                ? new SSCurrent($value)
                : $value;
        }
        return new NullMini();
    }
}

/** Tuned + mixed return type (isolates the union-return-check cost) */
final class ArrTunedMixed
{
    private array $data;
    private bool $useSmartStrings = true;
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    public function __get(string $key): mixed
    {
        $value = $this->data[$key] ?? null;
        if ($value !== null || array_key_exists($key, $this->data)) {
            return $this->useSmartStrings && !is_object($value)
                ? new SSCurrent($value)
                : $value;
        }
        return new NullMini();
    }
}

/** No-object ceiling: __get returns the encoded string directly (parked API break) */
final class ArrDirect
{
    private array $data;
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    public function __get(string $key): string
    {
        return htmlspecialchars((string)($this->data[$key] ?? ''), FLAGS, 'UTF-8');
    }
}

//endregion
//region Input pools (runtime-built, never interned literals)

/** Deterministic pseudo-random ASCII word soup, safe bytes only */
function build_clean(int $len, int $seed): string
{
    mt_srand($seed);
    $words = ['alpha', 'bravo', 'charlie', 'delta', 'echo', 'fox', 'golf', 'hotel', 'india', 'kilo'];
    $s = '';
    while (strlen($s) < $len) {
        $s .= $words[mt_rand(0, 9)] . ' ';
    }
    return substr($s, 0, $len);
}

/**
 * @return array<string, string[]>  pool name -> >= 64 distinct runtime-built strings
 */
function build_pools(): array
{
    $pools = ['clean10' => [], 'clean200' => [], 'clean1k' => [], 'dirty10' => [],
              'dirty1k' => [], 'accented1k' => [], 'mix' => [], 'memo_mix' => []];

    for ($i = 0; $i < 64; $i++) {
        $pools['clean10'][]  = build_clean(10, 1000 + $i);
        $pools['clean200'][] = build_clean(200, 2000 + $i);
        $pools['clean1k'][]  = build_clean(1024, 3000 + $i);
        // dirty10: one special mid-string
        $pools['dirty10'][] = substr(build_clean(9, 4000 + $i), 0, 4) . '&' . substr(build_clean(9, 4000 + $i), 5);
        // dirty1k: HTML-ish, specials throughout (ASCII only, so the str_replace tier fires)
        $pools['dirty1k'][] = '<p class="x">' . str_replace(' ', ' & ', build_clean(950, 5000 + $i)) . "</p>\n";
        // accented1k: clean multibyte text (é per word), no specials
        $pools['accented1k'][] = str_replace('a', "\u{E9}", build_clean(900, 6000 + $i));
    }

    // Realistic field mix: 70% clean-short, 15% clean-200B, 10% clean-1KB, 5% dirty-1KB
    for ($i = 0; $i < 64; $i++) {
        $r = $i % 20;
        $pools['mix'][] = match (true) {
            $r < 14 => $pools['clean10'][$i],
            $r < 17 => $pools['clean200'][$i],
            $r < 19 => $pools['clean1k'][$i],
            default => $pools['dirty1k'][$i],
        };
    }

    // Memo-shaped mix: 85% clean unique, 10% REPEATED short dirty (cache's best case), 5% unique 1KB dirty
    $repeated = ['Save 10% & more', 'Terms & Conditions', "O'Brien & Sons"];
    for ($i = 0; $i < 64; $i++) {
        $r = $i % 20;
        $pools['memo_mix'][] = match (true) {
            $r < 17 => $pools['clean10'][$i],
            $r < 19 => $repeated[$i % 3],
            default => $pools['dirty1k'][$i],
        };
    }

    return $pools;
}

//endregion
//region Harness

/**
 * Interleaved paired benchmark: alternate A and B reps, best-of-N each.
 * Callables take only the iteration count (their input pool is bound at build time).
 * Returns [a_ns, b_ns] per-op bests.
 */
function ab_bench(callable $a, callable $b, int $iters, int $reps = 7): array
{
    $bestA = INF;
    $bestB = INF;
    // warmup (JIT/opcache/branch predictors)
    $a(max(1000, intdiv($iters, 50)));
    $b(max(1000, intdiv($iters, 50)));
    for ($r = 0; $r < $reps; $r++) {
        $t = hrtime(true);
        $a($iters);
        $ns = (hrtime(true) - $t) / $iters;
        if ($ns < $bestA) {
            $bestA = $ns;
        }
        $t = hrtime(true);
        $b($iters);
        $ns = (hrtime(true) - $t) / $iters;
        if ($ns < $bestB) {
            $bestB = $ns;
        }
    }
    return [$bestA, $bestB];
}

/** Global sink: consumed results, keeps every benchmarked expression alive */
$GLOBALS['sink'] = 0;

/**
 * Bind a per-string closure to a pool, producing a timed loop body fn(int $iters).
 * The modulo cycle defeats single-zval cache effects (PCRE UTF-8 flag, interning).
 */
function looped(callable $perString, array $pool): callable
{
    return static function (int $iters) use ($perString, $pool): void {
        $n = count($pool);
        $acc = 0;
        for ($i = 0; $i < $iters; $i++) {
            $acc += $perString($pool[$i % $n]);
        }
        $GLOBALS['sink'] += $acc;
    };
}

//endregion
//region Test registry

/**
 * Each test: [id, iterations-class, A-label, B-label, A-callable, B-callable, encoders-to-gate]
 * iterations-class: short|medium|long (scaled per string size so cells stay ~minutes)
 */
function build_tests(array $pools): array
{
    return [
        // --- SmartString construction/storage ---
        ['promo', 'short', 'manual assignment', 'ctor promotion',
            looped(static fn(string $v): int => strlen((string)(new SSCurrent($v))), $pools['clean10']),
            looped(static fn(string $v): int => strlen((string)(new SSPromo($v))), $pools['clean10']), []],
        ['prop-type', 'short', 'typed readonly prop', 'untyped prop (typed ctor)',
            looped(static fn(string $v): int => strlen((string)(new SSCurrent($v))), $pools['clean10']),
            looped(static fn(string $v): int => strlen((string)(new SSUntyped($v))), $pools['clean10']), []],

        // --- Identity gate (adoption candidate #1) ---
        ['gate-preg-short', 'short', 'htmlspecialchars', 'preg identity gate',
            looped(static fn(string $v): int => strlen((string)(new SSCurrent($v))), $pools['clean10']),
            looped(static fn(string $v): int => strlen((string)(new SSGate($v))), $pools['clean10']), ['enc_gate']],
        ['gate-preg-1kb', 'long', 'htmlspecialchars', 'preg identity gate',
            looped(static fn(string $v): int => strlen((string)(new SSCurrent($v))), $pools['clean1k']),
            looped(static fn(string $v): int => strlen((string)(new SSGate($v))), $pools['clean1k']), ['enc_gate']],
        ['gate-preg-mix', 'medium', 'htmlspecialchars', 'preg identity gate',
            looped(static fn(string $v): int => strlen((string)(new SSCurrent($v))), $pools['mix']),
            looped(static fn(string $v): int => strlen((string)(new SSGate($v))), $pools['mix']), ['enc_gate']],
        ['gate-miss-short', 'short', 'htmlspecialchars', 'gate (always misses)',
            looped(static fn(string $v): int => strlen((string)(new SSCurrent($v))), $pools['dirty10']),
            looped(static fn(string $v): int => strlen((string)(new SSGate($v))), $pools['dirty10']), ['enc_gate']],
        ['gate-miss-1kb', 'long', 'htmlspecialchars', 'gate (always misses)',
            looped(static fn(string $v): int => strlen((string)(new SSCurrent($v))), $pools['dirty1k']),
            looped(static fn(string $v): int => strlen((string)(new SSGate($v))), $pools['dirty1k']), ['enc_gate']],

        // --- Gate primitives and tiers (function level) ---
        ['strspn-cliff', 'long', 'preg gate scan', 'strspn scan',
            looped(static fn(string $v): int => preg_match(GATE_CHANGEABLE, $v) === 0 ? strlen($v) : 0, $pools['clean1k']),
            looped(static fn(string $v): int => strspn($v, TIER1) === strlen($v) ? strlen($v) : 0, $pools['clean1k']), []],
        ['gate-adaptive-short', 'short', 'preg-only gate', 'version-adaptive gate',
            looped(static fn(string $v): int => strlen(enc_two_tier($v)), $pools['clean10']),
            looped(static fn(string $v): int => strlen(enc_adaptive($v)), $pools['clean10']), ['enc_two_tier', 'enc_adaptive']],
        ['gate-adaptive-1kb', 'long', 'preg-only gate', 'version-adaptive gate',
            looped(static fn(string $v): int => strlen(enc_two_tier($v)), $pools['clean1k']),
            looped(static fn(string $v): int => strlen(enc_adaptive($v)), $pools['clean1k']), ['enc_two_tier', 'enc_adaptive']],
        ['tier-strrep', 'long', 'htmlspecialchars', 'gated str_replace tier',
            looped(static fn(string $v): int => strlen(enc_baseline($v)), $pools['dirty1k']),
            looped(static fn(string $v): int => strlen(enc_two_tier($v)), $pools['dirty1k']), ['enc_two_tier']],
        ['gate-unicode', 'long', 'two-tier (bails on UTF-8)', 'three-tier (/u pass)',
            looped(static fn(string $v): int => strlen(enc_two_tier($v)), $pools['accented1k']),
            looped(static fn(string $v): int => strlen(enc_three_tier($v)), $pools['accented1k']), ['enc_two_tier', 'enc_three_tier']],

        // --- SmartArray access path (adoption candidate #2) ---
        ['arr-get', 'short', 'current 3-call chain', 'folded single-lookup __get',
            row_loop(ArrCurrent::class, $pools['clean10']),
            row_loop(ArrTunedUnion::class, $pools['clean10']), []],
        ['arr-get-mixed', 'short', 'tuned, union return', 'tuned, mixed return',
            row_loop(ArrTunedUnion::class, $pools['clean10']),
            row_loop(ArrTunedMixed::class, $pools['clean10']), []],
        ['no-object', 'short', 'object + __toString', 'direct encoded string',
            row_loop(ArrCurrent::class, $pools['clean10']),
            row_loop(ArrDirect::class, $pools['clean10']), []],

        // --- Output idiom + rejected candidate ---
        ['idiom', 'short', '(string) cast', '->htmlEncode()',
            looped(static fn(string $v): int => strlen((string)(new SSCurrent($v))), $pools['clean10']),
            looped(static fn(string $v): int => strlen((new SSCurrent($v))->htmlEncode()), $pools['clean10']), []],
        ['memo-mix', 'medium', 'gate only', 'gate + memo cache',
            looped(static fn(string $v): int => strlen(enc_gate($v)), $pools['memo_mix']),
            looped(static fn(string $v): int => strlen(enc_memo($v)), $pools['memo_mix']), ['enc_gate', 'enc_memo']],
    ];
}

/**
 * Timed loop over 64 single-field row objects of the given class, cycled.
 * The field key is runtime-built so it is not an interned literal.
 */
function row_loop(string $cls, array $pool): callable
{
    $key  = 'title' . substr((string)crc32($cls), 0, 2);
    $rows = [];
    foreach ($pool as $v) {
        $rows[] = new $cls([$key => $v]);
    }
    return static function (int $iters) use ($rows, $key): void {
        $n = count($rows);
        $acc = 0;
        for ($i = 0; $i < $iters; $i++) {
            $acc += strlen((string)$rows[$i % $n]->$key);
        }
        $GLOBALS['sink'] += $acc;
    };
}

//endregion
//region Main

$opts    = getopt('', ['json::', 'filter::', 'scale::', 'skip-corpus']);
$filter  = isset($opts['filter']) ? array_flip(array_map('trim', explode(',', (string)$opts['filter']))) : null;
$scale   = isset($opts['scale']) ? max(0.01, (float)$opts['scale']) : 1.0;
$itersBy = ['short' => (int)(300000 * $scale), 'medium' => (int)(100000 * $scale), 'long' => (int)(30000 * $scale)];

$out = [
    'php'        => PHP_VERSION,
    'os'         => PHP_OS_FAMILY,
    'arch'       => php_uname('m'),
    'opcache'    => (bool)ini_get('opcache.enable_cli'),
    'jit'        => (string)ini_get('opcache.jit'),
    'xdebug'     => extension_loaded('xdebug'),
    'iterations' => $itersBy,
    'corpus'     => null,
    'tests'      => [],
];

// 1. Correctness gate: every encoding variant must be byte-identical on the corpus
$encoderStatus = [];
if (!isset($opts['skip-corpus'])) {
    $corpus   = speed_corpus();
    $encoders = ['enc_gate', 'enc_two_tier', 'enc_three_tier', 'enc_adaptive', 'enc_hybrid_strspn', 'enc_memo'];
    // strspn corpus pass is slow before 8.4 (linear charset scan) but corpus strings are
    // short, so it stays in: correctness must hold even where we'd never deploy it.
    foreach ($encoders as $fn) {
        $res = speed_corpus_assert($fn, $corpus);
        $encoderStatus[$fn] = $res['fail'] === 0;
        if ($res['fail'] > 0) {
            fwrite(STDERR, "CORPUS FAIL: $fn ({$res['fail']} of {$res['count']})\n" . implode("\n", $res['samples']) . "\n");
        }
    }
    $out['corpus'] = ['entries' => count($corpus), 'encoders' => $encoderStatus];
    unset($corpus);
    // SSGate uses the same gate as enc_gate; class variant is covered by that assert.
}

// 2. Benchmarks
$pools = build_pools();
foreach (build_tests($pools) as [$id, $sizeClass, $aLabel, $bLabel, $aFn, $bFn, $gatedBy]) {
    if ($filter !== null && !isset($filter[$id])) {
        continue;
    }
    $withheld = array_values(array_filter($gatedBy, static fn(string $fn): bool => isset($encoderStatus[$fn]) && !$encoderStatus[$fn]));
    if ($withheld !== []) {
        $out['tests'][$id] = ['a_label' => $aLabel, 'b_label' => $bLabel, 'verdict' => 'CORPUS_FAIL', 'failed_encoders' => $withheld];
        continue;
    }
    [$aNs, $bNs] = ab_bench($aFn, $bFn, $itersBy[$sizeClass]);
    $ratio = $aNs / $bNs; // > 1: B faster
    $out['tests'][$id] = [
        'a_label' => $aLabel, 'b_label' => $bLabel,
        'a_ns'    => round($aNs, 1), 'b_ns' => round($bNs, 1),
        'ratio'   => round($ratio, 3),
        'verdict' => $ratio >= 1.05 ? 'B_FASTER' : ($ratio <= 0.952 ? 'A_FASTER' : 'TIE'),
    ];
}

// 3. Report: markdown to stdout (workflow appends to $GITHUB_STEP_SUMMARY), JSON to --json
printf("### PHP %s on %s %s (opcache_cli=%s, jit=%s)%s\n\n", $out['php'], $out['os'], $out['arch'],
    $out['opcache'] ? 'on' : 'off', $out['jit'] !== '' ? $out['jit'] : 'off',
    $out['xdebug'] ? ' **XDEBUG LOADED - RESULTS INVALID**' : '');
if ($out['corpus'] !== null) {
    $bad = array_keys(array_filter($out['corpus']['encoders'], static fn(bool $ok): bool => !$ok));
    printf("Corpus: %d entries, %s\n\n", $out['corpus']['entries'],
        $bad === [] ? 'all encoders byte-identical' : 'FAILED: ' . implode(', ', $bad));
}
echo "| test | A | B | A ns | B ns | B vs A | verdict |\n|---|---|---|---|---|---|---|\n";
foreach ($out['tests'] as $id => $t) {
    if (($t['verdict'] ?? '') === 'CORPUS_FAIL') {
        printf("| %s | %s | %s | - | - | - | CORPUS_FAIL |\n", $id, $t['a_label'], $t['b_label']);
        continue;
    }
    printf("| %s | %s | %s | %.1f | %.1f | %.2fx | %s |\n",
        $id, $t['a_label'], $t['b_label'], $t['a_ns'], $t['b_ns'], $t['ratio'], $t['verdict']);
}

if (isset($opts['json'])) {
    file_put_contents((string)$opts['json'], json_encode($out, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
}

$anyFail = $out['corpus'] !== null && in_array(false, $out['corpus']['encoders'], true);
exit($anyFail ? 1 : 0);

//endregion
