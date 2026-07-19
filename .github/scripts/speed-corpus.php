<?php
declare(strict_types=1);

/**
 * Correctness corpus for encoding fast-path candidates.
 *
 * Any alternative encoder must produce byte-identical output to
 * htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE|ENT_DISALLOWED|ENT_HTML5, 'UTF-8')
 * for every string this corpus generates. speed-probe.php runs this gate before
 * timing anything; a future PHPUnit test can wrap it when a fast path ships.
 *
 * Corpus derived from the adversarial verifier run of 2026-07-18 (~91k entries):
 * empty string, all 256 single bytes, ALL 65,536 two-byte strings, every byte at
 * head/mid/tail of clean ASCII, valid multibyte incl. emoji and combining marks,
 * all 66 Unicode noncharacters, overlongs, surrogate halves, truncated sequences,
 * stray continuation bytes, the five specials in context, long strings with a bad
 * byte at head/middle/tail, whitespace combos, and 40,000 seeded fuzz strings.
 *
 * No extension requirements: UTF-8 is encoded by hand (mbstring may be absent),
 * fuzz uses a fixed mt_srand seed so every run tests the identical corpus.
 */

const SPEED_CORPUS_FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5;

/**
 * Encode a code point to UTF-8 bytes without mbstring.
 */
function speed_corpus_u8(int $cp): string
{
    if ($cp < 0x80) {
        return chr($cp);
    }
    if ($cp < 0x800) {
        return chr(0xC0 | $cp >> 6) . chr(0x80 | $cp & 0x3F);
    }
    if ($cp < 0x10000) {
        return chr(0xE0 | $cp >> 12) . chr(0x80 | ($cp >> 6) & 0x3F) . chr(0x80 | $cp & 0x3F);
    }
    return chr(0xF0 | $cp >> 18) . chr(0x80 | ($cp >> 12) & 0x3F) . chr(0x80 | ($cp >> 6) & 0x3F) . chr(0x80 | $cp & 0x3F);
}

/**
 * Build the full corpus. ~91k strings, a few MB; generate once per process.
 *
 * @return string[]
 */
function speed_corpus(): array
{
    $corpus = [''];

    // All 256 single bytes
    for ($b = 0; $b <= 0xFF; $b++) {
        $corpus[] = chr($b);
    }

    // ALL 65,536 two-byte strings (partial multibyte, C1-as-latin1, etc.)
    for ($a = 0; $a <= 0xFF; $a++) {
        $ca = chr($a);
        for ($b = 0; $b <= 0xFF; $b++) {
            $corpus[] = $ca . chr($b);
        }
    }

    // Each byte at head / mid / tail of clean ASCII (position sensitivity)
    for ($b = 0; $b <= 0xFF; $b++) {
        $corpus[] = "Hello " . chr($b) . " World";
        $corpus[] = chr($b) . " leading";
        $corpus[] = "trailing " . chr($b);
    }

    // Valid multibyte UTF-8: 2/3/4-byte sequences, emoji, combining marks, BOM, zero-width
    $mb = ["caf\u{E9}", "\u{4E2D}\u{6587}", "\u{1F600}\u{1F4A9}", "e\u{0301}", "\u{FEFF}",
           "\u{00A0}", "\u{2028}\u{2029}", "\u{FFFD}", "\u{10FFFF}", "\u{E000}", "\u{200B}"];
    foreach ($mb as $s) {
        $corpus[] = $s;
        $corpus[] = "pre $s post";
    }

    // Noncharacters: U+FDD0..U+FDEF, and U+xFFFE/U+xFFFF in every plane
    for ($cp = 0xFDD0; $cp <= 0xFDEF; $cp++) {
        $corpus[] = speed_corpus_u8($cp) . " tail";
    }
    for ($plane = 0; $plane <= 0x10; $plane++) {
        $corpus[] = "a" . speed_corpus_u8($plane * 0x10000 + 0xFFFE);
        $corpus[] = "a" . speed_corpus_u8($plane * 0x10000 + 0xFFFF);
    }

    // Invalid UTF-8: overlongs, surrogate halves (CESU-8), truncations, stray continuations
    $invalid = [
        "\xC0\xAF", "\xC1\xBF",                 // overlong '/'
        "\xE0\x80\xAF", "\xF0\x80\x80\xAF",     // more overlongs
        "\xED\xA0\x80", "\xED\xBF\xBF",         // surrogate halves
        "\xF4\x90\x80\x80",                     // > U+10FFFF
        "\xC3", "\xE2\x82", "\xF0\x9F\x98",     // truncated sequences
        "\x80", "\xBF", "\x80\x80\x80",         // stray continuations
        "\xFE", "\xFF", "\xFF\xFE\xFD",
        "ok\xC3\x28bad",                        // invalid continuation
    ];
    foreach ($invalid as $s) {
        $corpus[] = $s;
        $corpus[] = "text $s text";
    }

    // The five specials in various contexts
    foreach (['&', '<', '>', '"', "'"] as $sp) {
        $corpus[] = $sp;
        $corpus[] = "a{$sp}b";
        $corpus[] = str_repeat($sp, 50);
    }
    $corpus[] = '<script>alert("x&y\'z")</script>';
    $corpus[] = '&amp; already encoded &#39;';

    // Long strings, bad byte at head / middle / tail (gate must not miss by position)
    $clean1k  = str_repeat('The quick brown fox jumps over the lazy dog. ', 23);
    $corpus[] = $clean1k;
    $corpus[] = "<" . $clean1k;
    $corpus[] = substr($clean1k, 0, 500) . "\x00" . substr($clean1k, 500);
    $corpus[] = $clean1k . "\xE9";
    $corpus[] = $clean1k . speed_corpus_u8(0xFDD0);

    // Whitespace combos: \t \n \v \f \r
    $corpus[] = "a\tb\nc\x0Bd\x0Ce\rf";
    $corpus[] = "\t\n\x0C\r";
    $corpus[] = "\x0B";

    // Fuzz: 20,000 random-byte strings + 20,000 ASCII-biased (fast path's home turf).
    // Fixed seed: every run, every platform, tests the identical corpus.
    mt_srand(20260718);
    for ($i = 0; $i < 20000; $i++) {
        $len = mt_rand(0, 64);
        $s   = '';
        for ($j = 0; $j < $len; $j++) {
            $s .= chr(mt_rand(0, 255));
        }
        $corpus[] = $s;
    }
    for ($i = 0; $i < 20000; $i++) {
        $len = mt_rand(0, 64);
        $s   = '';
        for ($j = 0; $j < $len; $j++) {
            $s .= mt_rand(0, 30) === 0 ? chr(mt_rand(0, 255)) : chr(mt_rand(0x20, 0x7E));
        }
        $corpus[] = $s;
    }

    return $corpus;
}

/**
 * Assert an encoder is byte-identical to the reference over the whole corpus.
 *
 * @param callable $encoder  fn(string): string
 * @param string[] $corpus   result of speed_corpus() (pass in to reuse across encoders)
 * @return array{count: int, fail: int, samples: string[]}  samples = first 5 mismatches as hex
 */
function speed_corpus_assert(callable $encoder, array $corpus): array
{
    $fail    = 0;
    $samples = [];
    foreach ($corpus as $s) {
        $want = htmlspecialchars($s, SPEED_CORPUS_FLAGS, 'UTF-8');
        $got  = $encoder($s);
        if ($got !== $want) {
            $fail++;
            if (count($samples) < 5) {
                $samples[] = sprintf('input=%s want=%s got=%s', bin2hex($s), bin2hex($want), bin2hex($got));
            }
        }
    }
    return ['count' => count($corpus), 'fail' => $fail, 'samples' => $samples];
}

// Standalone self-check: the reference must agree with itself, and the corpus
// must be a sane size. Run: php speed-corpus.php
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $corpus = speed_corpus();
    $result = speed_corpus_assert(static fn(string $s): string => htmlspecialchars($s, SPEED_CORPUS_FLAGS, 'UTF-8'), $corpus);
    printf("PHP %s | corpus=%d fail=%d (self-check)\n", PHP_VERSION, $result['count'], $result['fail']);
    exit($result['fail'] === 0 && $result['count'] > 90000 ? 0 : 1);
}
