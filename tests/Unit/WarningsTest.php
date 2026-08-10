<?php
/** @noinspection PhpDeprecationInspection */ // get() is a Silent alias; its warning contract is pinned here
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The warning system: the two texts warnIfMissing() produces, when each one
 * fires, when the library stays silent, and the string-conversion warning.
 *
 * Warnings are echoed AND sent through @trigger_error() so error handlers can
 * log them, so tests here assert both channels. File names, line numbers, and
 * closure names are masked by maskLocations() (they move with every edit);
 * everything else is pinned literally.
 *
 * Per-method missing-key return values live in ReadAccessTest and the method
 * files; this file covers the warning texts and the rules that pick them.
 */
class WarningsTest extends SmartArrayTestCase
{
    /** The hint appended when a missing key matches a method name. */
    private const BRACES_HINT = 'In double-quoted strings, use "$var->property" for properties, but wrap methods in braces like "{$var->method()}"';

    //region Helpers

    /**
     * Replace the parts of a warning that move with every edit: the caller's
     * file and line, the library file and line, and the closure name PHP builds
     * for the frame that called the library (its format changed in PHP 8.4).
     */
    private static function maskLocations(string $output): string
    {
        $output = preg_replace('#\S*WarningsTest\.php on line \d+#', 'TEST_FILE on line LINE', $output);
        $output = preg_replace('#\S*WarningsTest\.php:\d+#',         'TEST_FILE:LINE',         $output);
        $output = preg_replace('#\S*SmartArrayBase\.php:\d+#',       'SRC_FILE:LINE',          $output);
        return preg_replace('#in \S*\{closure[^}]*}\(\)#',           'in CLOSURE()',           $output);
    }

    /**
     * Run $fn capturing both warning channels: echoed output and the
     * E_USER_WARNING messages the library sends via @trigger_error().
     *
     * @return array{0: string, 1: string[]}
     */
    private function captureWarnings(callable $fn): array
    {
        [[, $output], $messages] = $this->captureErrors(fn() => $this->captureOutput($fn), E_USER_WARNING);
        return [$output, $messages];
    }

    //endregion
    //region Offset warnings (missing key on read)

    #[DataProvider('modeProvider')]
    public function testMissingKeyWarningNamesTheKeyAndCallerLocation(string $class): void
    {
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [, $output] = $this->captureOutput(fn() => $sa->zzz);

        $this->assertSame("\nWarning: zzz is undefined in TEST_FILE:LINE\n\n", self::maskLocations($output));
    }

    #[DataProvider('modeProvider')]
    public function testMissingKeyWarningShowsEmptyStringKeyAsQuotes(string $class): void
    {
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [, $output] = $this->captureOutput(fn() => $sa->get(''));

        $this->assertSame("\nWarning: '' is undefined in TEST_FILE:LINE\n\n", self::maskLocations($output));
    }

    #[DataProvider('modeProvider')]
    public function testMissingKeyWarningShowsIntegerKeysUnquoted(string $class): void
    {
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [, $output] = $this->captureOutput(fn() => $sa->get(5));

        $this->assertSame("\nWarning: 5 is undefined in TEST_FILE:LINE\n\n", self::maskLocations($output));
    }

    #[DataProvider('modeProvider')]
    public function testMissingKeyWarningEncodesTheKeyInBothModes(string $class): void
    {
        // SECURITY: the key can be user input (->{$_GET['sort']}) and the warning
        // echoes into the page, so it is HTML-encoded in both modes
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [, $output] = $this->captureOutput(fn() => $sa->{'<b>'});

        $this->assertSame("\nWarning: &lt;b&gt; is undefined in TEST_FILE:LINE\n\n", self::maskLocations($output));
    }

    #[DataProvider('modeProvider')]
    public function testMissingKeyWarningIsAlsoSentToErrorHandlers(string $class): void
    {
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [$output, $messages] = $this->captureWarnings(fn() => $sa->zzz);

        // Echoed text adds the "Warning:" prefix and blank lines; the logged
        // message is the bare text, so handlers can format it themselves
        $this->assertSame("\nWarning: zzz is undefined in TEST_FILE:LINE\n\n", self::maskLocations($output));
        $this->assertCount(1, $messages);
        $this->assertSame("zzz is undefined in TEST_FILE:LINE\n", self::maskLocations($messages[0]));
    }

    //endregion
    //region Argument warnings (missing field passed to a method)

    #[DataProvider('modeProvider')]
    public function testMissingFieldWarningNamesTheMethodFieldAndBothLocations(string $class): void
    {
        $rows = $class::new([['a' => 1], ['a' => 2]]);

        [, $output] = $this->captureOutput(fn() => $rows->where('zzz', 1));

        $expected = "\nWarning: where(): 'zzz' doesn't exist\n"
            . "Occurred in TEST_FILE:LINE in CLOSURE()\n"
            . "Reported in SRC_FILE:LINE in SmartArrayBase->warnIfMissing()\n"
            . "\n";
        $this->assertSame($expected, self::maskLocations($output));
    }

    /**
     * @return array<string, array{callable(SmartArrayBase): mixed, string}>
     */
    public static function fieldMethodProvider(): array
    {
        return [
            'where'       => [static fn(SmartArrayBase $rows) => $rows->where('zzz', 1),      'where'],
            'whereNot'    => [static fn(SmartArrayBase $rows) => $rows->whereNot('zzz', 1),   'whereNot'],
            'whereInList' => [static fn(SmartArrayBase $rows) => $rows->whereInList('zzz', 1), 'whereInList'],
            'sortBy'      => [static fn(SmartArrayBase $rows) => $rows->sortBy('zzz'),        'sortBy'],
            'indexBy'     => [static fn(SmartArrayBase $rows) => $rows->indexBy('zzz'),       'indexBy'],
            'groupBy'     => [static fn(SmartArrayBase $rows) => $rows->groupBy('zzz'),       'groupBy'],
            'column'      => [static fn(SmartArrayBase $rows) => $rows->column('zzz'),        'column'],
        ];
    }

    #[DataProvider('fieldMethodProvider')]
    public function testArgumentWarningNamesTheMethodThatReceivedTheField(callable $call, string $method): void
    {
        $rows = SmartArray::new([['a' => 1], ['a' => 2]]);

        [, $output] = $this->captureOutput(static fn() => $call($rows));

        $firstLine = explode("\n", $output)[1] ?? '';
        $this->assertSame("Warning: $method(): 'zzz' doesn't exist", $firstLine);
    }

    #[DataProvider('modeProvider')]
    public function testArgumentWarningChecksTheFirstRowOnly(string $class): void
    {
        // Only the first row is sampled, so a field missing from later rows is
        // not reported (and a field present only in the first row is accepted)
        $rows = $class::new([['a' => 1], ['b' => 2]]);

        [, $output] = $this->captureOutput(fn() => $rows->where('a', 1));

        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testArgumentWarningSkippedOnEmptyArray(string $class): void
    {
        $rows = $class::new([]);

        [, $output] = $this->captureOutput(fn() => $rows->where('zzz', 1));

        $this->assertSame('', $output, 'an empty array has no rows to check the field against');
    }

    #[DataProvider('modeProvider')]
    public function testMissingFieldWarningIsAlsoSentToErrorHandlers(string $class): void
    {
        $rows = $class::new([['a' => 1]]);

        [$output, $messages] = $this->captureWarnings(fn() => $rows->indexBy('zzz'));

        $this->assertStringContainsString("Warning: indexBy(): 'zzz' doesn't exist", $output);
        $this->assertCount(1, $messages);

        $expected = "indexBy(): 'zzz' doesn't exist\n"
            . "Occurred in TEST_FILE:LINE in CLOSURE()\n"
            . "Reported in SRC_FILE:LINE in SmartArrayBase->warnIfMissing()\n";
        $this->assertSame($expected, self::maskLocations($messages[0]));
    }

    //endregion
    //region Method-name hint (missing braces in a double-quoted string)

    #[DataProvider('modeProvider')]
    public function testMissingKeyNamedAfterAMethodSuggestsBraces(string $class): void
    {
        // "Total: $list->implode" in a string reads as a property, not a call
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [, $output] = $this->captureOutput(fn() => $sa->implode);

        $expected = "\nWarning: implode is undefined in TEST_FILE:LINE\n"
            . "\n" . self::BRACES_HINT . "\n"
            . "\n";
        $this->assertSame($expected, self::maskLocations($output));
    }

    #[DataProvider('modeProvider')]
    public function testMissingFieldNamedAfterAMethodSuggestsBraces(string $class): void
    {
        $rows = $class::new([['a' => 1]]);

        [, $output] = $this->captureOutput(fn() => $rows->groupBy('count'));

        $expected = "\nWarning: groupBy(): 'count' doesn't exist\n"
            . "\n" . self::BRACES_HINT . "\n"
            . "Occurred in TEST_FILE:LINE in CLOSURE()\n"
            . "Reported in SRC_FILE:LINE in SmartArrayBase->warnIfMissing()\n"
            . "\n";
        $this->assertSame($expected, self::maskLocations($output));
    }

    #[DataProvider('modeProvider')]
    public function testHintCoversDeprecatedMethodNamesToo(string $class): void
    {
        // The hint keys off method_exists(), so the deprecated aliases (now real
        // declared methods) are matched the same as current ones
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [, $output] = $this->captureOutput(fn() => $sa->pluckNth);

        $this->assertStringContainsString(self::BRACES_HINT, $output);
    }

    //endregion
    //region Silence on happy paths

    #[DataProvider('modeProvider')]
    public function testReadsThatFindTheirKeyAreSilent(string $class): void
    {
        $sa = $class::new(['name' => 'Bob', 'middle' => null, 'rows' => [['a' => 1]]]);

        [, $output] = $this->captureOutput(function () use ($sa) {
            $sa->name;
            $sa->middle;          // a stored null is a hit, not a miss
            $sa->get('name');
            $sa->{'rows'}->first()->a;
            $sa->rows->where('a', 1);
        });

        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testMissingKeyOnAnEmptyArrayIsSilent(string $class): void
    {
        // Empty results are the normal "no rows" case, not a developer mistake
        $empty = $class::new([]);

        [, $output] = $this->captureOutput(function () use ($empty) {
            $empty->zzz;
            $empty->first()->zzz;   // and the SmartNull the chain returns stays quiet
        });

        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testExistenceChecksAndDefaultsDoNotWarn(string $class): void
    {
        $sa = $class::new(['name' => 'Bob']);

        [, $output] = $this->captureOutput(function () use ($sa) {
            isset($sa->zzz);
            empty($sa->zzz);
            $sa->get('zzz', 'default');   // a default says the miss is expected
            $sa->at(9);                   // out of bounds is a position, not a named key
            $sa->last();
        });

        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testValueReadsOfAMissingKeyWarnOncePerRead(string $class): void
    {
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [, $output] = $this->captureOutput(function () use ($sa) {
            $sa->zzz;
            $sa->get('zzz');
        });

        $this->assertSame(2, substr_count($output, 'Warning:'));
    }

    #[DataProvider('modeProvider')]
    public function testMissingKeysOnTopLevelCollectionsAreSilent(string $class): void
    {
        // Only result-set rows warn: top-level and derived collections (indexBy/column
        // maps, standalone arrays) are keyed by data, where a miss is a normal no-match
        $standalone = $class::new(['name' => 'Bob']);
        $byId       = $class::new([['id' => 7, 'name' => 'Alice']])->indexBy('id');
        $nameById   = $class::new([['id' => 7, 'name' => 'Alice']])->column('name', 'id');

        [, $output] = $this->captureOutput(function () use ($standalone, $byId, $nameById) {
            $standalone->zzz;
            $byId->{99};
            $nameById->{99};
        });

        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testRowsInsideDerivedCollectionsStillWarn(string $class): void
    {
        $rows = $class::new([['name' => 'Bob'], ['name' => 'Sue']]);

        [, $output] = $this->captureOutput(fn() => $rows->where('name', 'Sue')->first()->zzz);

        $this->assertSame(1, substr_count($output, 'Warning:'));
    }

    //endregion
    //region __toString

    #[DataProvider('modeProvider')]
    public function testStringConversionWarnsAndReturnsEmptyString(string $class): void
    {
        // The message names the actual class being converted
        $shortClass = basename(str_replace('\\', '/', $class));
        $sa         = $class::new(['name' => 'Bob']);

        [$result, $output] = $this->captureOutput(fn() => (string)$sa);

        $expected = "\nWarning: Can't convert $shortClass to string in TEST_FILE on line LINE.\n"
            . "\n" . self::BRACES_HINT . "\n"
            . "\nSee SmartArray docs for more info\n"
            . "\n";
        $this->assertSame($expected, self::maskLocations($output));
        $this->assertSame('', $result, 'returns an empty string so the conversion is not fatal');
    }

    #[DataProvider('modeProvider')]
    public function testStringInterpolationWarnsTheSameAsAnExplicitCast(string $class): void
    {
        $sa = $class::new(['name' => 'Bob']);

        [$interpolated, $fromInterpolation] = $this->captureOutput(fn() => "[$sa]");
        [, $fromCast]                       = $this->captureOutput(fn() => (string)$sa);

        $this->assertSame('[]', $interpolated, 'the array contributes nothing to the string');
        $this->assertSame(self::maskLocations($fromCast), self::maskLocations($fromInterpolation));
    }

    #[DataProvider('modeProvider')]
    public function testStringConversionOfAnEmptyArrayStillWarns(string $class): void
    {
        // Unlike missing keys, this is always a coding mistake, empty or not
        $empty = $class::new([]);

        [, $output] = $this->captureOutput(fn() => (string)$empty);

        $shortClass = basename(str_replace('\\', '/', $class));
        $this->assertStringContainsString("Can't convert $shortClass to string", $output);
    }

    #[DataProvider('modeProvider')]
    public function testStringConversionWarningIsAlsoSentToErrorHandlers(string $class): void
    {
        $shortClass = basename(str_replace('\\', '/', $class));
        $sa         = $class::new(['name' => 'Bob']);

        [, $messages] = $this->captureWarnings(fn() => (string)$sa);

        $this->assertCount(1, $messages);

        // No leading newline, no "Warning:" prefix, and no trailing blank lines
        $expected = "Can't convert $shortClass to string in TEST_FILE on line LINE.\n"
            . "\n" . self::BRACES_HINT . "\n"
            . "\nSee SmartArray docs for more info";
        $this->assertSame($expected, self::maskLocations($messages[0]));
    }

    public function testStringConversionReportsTheConversionSiteNotTheLibrary(): void
    {
        $sa = SmartArray::new(['name' => 'Bob']);

        [, $output] = $this->captureOutput(fn() => (string)$sa);

        $this->assertStringContainsString('in ' . basename(__FILE__) . ' on line', $output);
        $this->assertStringNotContainsString(__DIR__, $output, 'the warning shows the basename only, never the full path');
        $this->assertStringNotContainsString('SmartArrayBase.php', $output);
    }

    //endregion
    //region Cross-mode consistency

    public function testBothModesProduceIdenticalWarningText(): void
    {
        $raw  = SmartArray::new(['name' => 'Bob']);
        $html = SmartArrayHtml::new(['name' => 'Bob']);

        [, $rawOutput]  = $this->captureOutput(fn() => $raw->zzz);
        [, $htmlOutput] = $this->captureOutput(fn() => $html->zzz);

        $this->assertSame(self::maskLocations($rawOutput), self::maskLocations($htmlOutput));
    }

    //endregion
}
