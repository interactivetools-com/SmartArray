<?php
/** @noinspection PhpDeprecationInspection, PhpUndefinedMethodInspection */
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use ArgumentCountError;
use Closure;
use Error;
use InvalidArgumentException;
use Itools\SmartArray\CallerException;
use Itools\SmartArray\DeprecatedAliases;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\SmartArrayRaw;
use Itools\SmartArray\Tests\Support\Fixtures;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

/**
 * The DeprecatedAliases trait: old method names, retired methods, and the
 * unknown-method errors.
 *
 * Every alias is a real declared method (method_exists() sees it, IDEs show a
 * strikethrough), sorted into the stage regions of src/DeprecatedAliases.php:
 *
 *     Silent  pluck, nth, pluckNth, each, sprintf - work, no runtime signal
 *     Logged  toRaw, toHtml, withSmartStrings, enableSmartStrings,
 *             noSmartStrings, disableSmartStrings, isMultipleOf, smartMap,
 *             chunk - work, one E_USER_DEPRECATED with the caller's file:line
 *     Visible offsetGet/offsetSet/offsetExists/offsetUnset - the deprecated
 *             $array['key'] syntax, covered by GlobalSettingsTest
 *
 * Expected values are literals, not the output of the replacement method, so a
 * change in either method breaks this file.
 */
class DeprecationsTest extends SmartArrayTestCase
{
    //region Alias inventory

    public function testTraitPublicMethodInventoryIsPinned(): void
    {
        // Adding or removing an alias must touch this file
        $methods = [];
        foreach ((new ReflectionClass(DeprecatedAliases::class))->getMethods() as $method) {
            if ($method->isPublic()) {
                $methods[] = $method->getName();
            }
        }
        sort($methods);

        $this->assertSame([
            '__call',
            '__callStatic',
            'chunk',
            'disableSmartStrings',
            'each',
            'enableSmartStrings',
            'isMultipleOf',
            'noSmartStrings',
            'nth',
            'offsetExists',
            'offsetGet',
            'offsetSet',
            'offsetUnset',
            'pluck',
            'pluckNth',
            'smartMap',
            'sprintf',
            'toHtml',
            'toRaw',
            'withSmartStrings',
        ], $methods);
    }

    /**
     * Every alias name, both stages. Rows are [alias name].
     *
     * @return array<string, array{string}>
     */
    public static function aliasNameProvider(): array
    {
        $names = [
            'pluck', 'nth', 'pluckNth', 'each', 'sprintf',
            'toRaw', 'toHtml', 'withSmartStrings', 'enableSmartStrings',
            'noSmartStrings', 'disableSmartStrings', 'isMultipleOf', 'smartMap', 'chunk',
        ];
        return array_combine($names, array_map(static fn(string $name) => [$name], $names));
    }

    #[DataProvider('aliasNameProvider')]
    public function testAliasesAreDeclaredMethodsNotCallShims(string $alias): void
    {
        $this->assertTrue(method_exists(SmartArray::class, $alias), "$alias should be a declared method");
        $this->assertTrue(method_exists(SmartArrayHtml::class, $alias), "$alias should be a declared method");
    }

    public function testRemovedMethodsAreNotDeclared(): void
    {
        $this->assertFalse(method_exists(SmartArray::class, 'usingSmartStrings'));
        $this->assertFalse(method_exists(SmartArray::class, 'setLoadHandler'));
    }

    //endregion
    //region Silent aliases: no runtime signal

    /**
     * Rows are [class, fixture kind, alias, args].
     *
     * @return array<string, array{class-string<SmartArrayBase>, string, string, array}>
     */
    public static function silentAliasProvider(): array
    {
        $calls = [
            'pluck'    => ['nested', ['isFirst']],
            'nth'      => ['flat', [0]],
            'pluckNth' => ['nested', [0]],
            'each'     => ['flat', [null]],   // filled in below: providers can't share a closure instance safely
            'sprintf'  => ['flat', ['{value}']],
        ];
        $calls['each'][1] = [static fn() => null];

        $rows = [];
        foreach (self::modeProvider() as $mode => [$class]) {
            foreach ($calls as $alias => [$kind, $args]) {
                $rows["$mode: $alias"] = [$class, $kind, $alias, $args];
            }
        }
        return $rows;
    }

    #[DataProvider('silentAliasProvider')]
    public function testSilentAliasesEmitNoDeprecationAndNoOutput(string $class, string $kind, string $alias, array $args): void
    {
        $sa = self::fixture($class, $kind);

        [[, $output], $deprecations] = $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $sa->$alias(...$args))
        );

        $this->assertSame([], $deprecations, "$alias is a Silent-stage alias: IDE strikethrough only, no runtime notice");
        $this->assertSame('', $output, "$alias should print nothing");
    }

    #[DataProvider('modeProvider')]
    public function testPluckReturnsTheColumnValues(string $class): void
    {
        $rows = $class::new(Fixtures::records());

        $result = $rows->pluck('isFirst');

        $this->assertInstanceOf($class, $result);
        $this->assertSame(['C', 'Q', 'K', 'Z'], $result->toArray());
        $this->assertSame(4, $rows->count(), 'source unchanged');
    }

    #[DataProvider('modeProvider')]
    public function testPluckWithKeyFieldReturnsAKeyedColumn(string $class): void
    {
        $rows = $class::new(Fixtures::records());

        $result = $rows->pluck('int', 'isFirst');

        $this->assertInstanceOf($class, $result);
        $this->assertSame(['C' => 7, 'Q' => 0, 'K' => 1, 'Z' => -3], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testPluckOnFlatArrayNamesPluckInTheError(string $class): void
    {
        // The alias asserts before delegating, so the message says pluck(), not column()
        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('pluck(): Expected a nested array, but got a flat array');

        $class::new(['a', 'b'])->pluck('x');
    }

    #[DataProvider('modeProvider')]
    public function testNthReturnsTheElementAtAPosition(string $class): void
    {
        $sa = $class::new([2 => 'first', 4 => 'second', 6 => 'third']);

        $this->assertModeValue('first', $sa->nth(0), $class);
        $this->assertModeValue('third', $sa->nth(2), $class);
        $this->assertModeValue('third', $sa->nth(-1), $class, 'negative indices count from the end');
    }

    #[DataProvider('modeProvider')]
    public function testNthOutOfRangeReturnsSmartNull(string $class): void
    {
        $sa = $class::new(['a', 'b']);

        $this->assertSmartNull($sa->nth(2));
        $this->assertSmartNull($sa->nth(-3));
    }

    #[DataProvider('modeProvider')]
    public function testNthOnNestedReturnsTheRow(string $class): void
    {
        $rows = $class::new(Fixtures::records());

        $row = $rows->nth(1);

        $this->assertInstanceOf($class, $row);
        $this->assertSame(Fixtures::records()[1], $row->toArray());
        $this->assertSame(2, $row->position());
    }

    #[DataProvider('modeProvider')]
    public function testPluckNthReturnsTheColumnAtAPosition(string $class): void
    {
        $rows = $class::new(Fixtures::records());

        $result = $rows->pluckNth(1);

        $this->assertInstanceOf($class, $result);
        $this->assertSame([7, 0, 1, -3], $result->toArray(), 'column 1 is int, whatever the key names are');
    }

    #[DataProvider('modeProvider')]
    public function testPluckNthOnFlatArrayNamesPluckNthInTheError(string $class): void
    {
        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('pluckNth(): Expected a nested array, but got a flat array');

        $class::new(['a', 'b'])->pluckNth(0);
    }

    #[DataProvider('modeProvider')]
    public function testEachVisitsEveryElementAndReturnsSelf(string $class): void
    {
        $sa = $class::new(['x' => 'alpha', 'y' => 'beta']);

        $seenKeys   = [];
        $seenValues = [];
        $returned   = $sa->each(function ($value, $key) use (&$seenKeys, &$seenValues) {
            $seenKeys[]   = $key;
            $seenValues[] = $value;
        });

        $this->assertSame(['x', 'y'], $seenKeys);
        $this->assertModeValue('alpha', $seenValues[0], $class, 'each() passes Smart values, unlike map()');
        $this->assertModeValue('beta', $seenValues[1], $class);
        $this->assertSame($sa, $returned, 'each() returns the same instance for chaining');
    }

    #[DataProvider('modeProvider')]
    public function testEachOnNestedRowsPassesChildArrays(string $class): void
    {
        $rows = $class::new([['id' => 1], ['id' => 2]]);

        $seen = [];
        $rows->each(function ($row) use (&$seen) {
            $seen[] = $row;
        });

        $this->assertInstanceOf($class, $seen[0]);
        $this->assertSame(['id' => 1], $seen[0]->toArray());
        $this->assertSame(['id' => 2], $seen[1]->toArray());
    }

    public function testSprintfLeavesValuesUnencodedInRawMode(): void
    {
        $sa = SmartArray::new(['a&b' => 'v<']);

        $result = $sa->sprintf('<td>{key}={value}</td>');

        $this->assertInstanceOf(SmartArray::class, $result);
        $this->assertSame(['a&b' => '<td>a&b=v<</td>'], $result->toArray());
    }

    public function testSprintfEncodesValuesAndKeysInHtmlMode(): void
    {
        $sa = SmartArrayHtml::new(['a&b' => 'v<']);

        $result = $sa->sprintf('<td>{key}={value}</td>');

        // Returns raw SmartArray so the finished HTML isn't encoded a second time on output
        $this->assertInstanceOf(SmartArray::class, $result);
        $this->assertNotInstanceOf(SmartArrayHtml::class, $result);
        $this->assertSame(['a&b' => '<td>a&amp;b=v&lt;</td>'], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testSprintfSupportsPositionalPlaceholders(string $class): void
    {
        $sa = $class::new(['x' => 'alpha']);

        $this->assertSame(['x' => 'alpha/x'], $sa->sprintf('%1$s/%2$s')->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testSprintfOnNestedArrayThrows(string $class): void
    {
        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('sprintf(): Expected a flat array, but got a nested array');

        $class::new([['id' => 1]])->sprintf('{value}');
    }

    //endregion
    //region Logged aliases: one E_USER_DEPRECATED per call

    /**
     * Rows are [class, alias, args, message text without the caller suffix].
     *
     * @return array<string, array{class-string<SmartArrayBase>, string, array, string}>
     */
    public static function loggedAliasProvider(): array
    {
        $calls = [
            'toRaw'               => [[], 'Replace ->toRaw() with ->asRaw()'],
            'toHtml'              => [[], 'Replace ->toHtml() with ->asHtml()'],
            'withSmartStrings'    => [[], 'Replace ->withSmartStrings() with ->asHtml() or use SmartArrayHtml::new()'],
            'enableSmartStrings'  => [[], 'Replace ->enableSmartStrings() with ->asHtml() or use SmartArrayHtml::new()'],
            'noSmartStrings'      => [[], 'Replace ->noSmartStrings() with ->asRaw() or use SmartArray::new()'],
            'disableSmartStrings' => [[], 'Replace ->disableSmartStrings() with ->asRaw() or use SmartArray::new()'],
            'isMultipleOf'        => [[2], '->isMultipleOf() is deprecated and will be removed in a future version'],
            'smartMap'            => [[], '->smartMap() is deprecated, use ->map() instead'],
            'chunk'               => [[2], '->chunk() is deprecated and will be removed in a future version'],
        ];
        $calls['smartMap'][0] = [static fn($value) => $value];

        $rows = [];
        foreach (self::modeProvider() as $mode => [$class]) {
            foreach ($calls as $alias => [$args, $message]) {
                $rows["$mode: $alias"] = [$class, $alias, $args, $message];
            }
        }
        return $rows;
    }

    #[DataProvider('loggedAliasProvider')]
    public function testLoggedAliasesEmitExactlyOneDeprecationAndNoOutput(string $class, string $alias, array $args, string $message): void
    {
        $sa = $class::new(['a', 'b', 'c']);

        [[, $output], $deprecations] = $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $sa->$alias(...$args))
        );

        $this->assertCount(1, $deprecations, "$alias should log one deprecation per call");
        $this->assertSame($message, self::withoutCallerSuffix($deprecations[0]));
        $this->assertSame('', $output, 'logged-stage aliases are silent in output; only error handlers see them');
    }

    public function testDeprecationMessageNamesTheCallersFileAndLine(): void
    {
        $sa = SmartArray::new(['a']);

        $line = __LINE__ + 1;
        [, $deprecations] = $this->captureDeprecations(fn() => $sa->toRaw());

        $this->assertSame("Replace ->toRaw() with ->asRaw() in DeprecationsTest.php:$line.", $deprecations[0]);
    }

    public function testDeprecationMessageUsesCanonicalMethodCasing(): void
    {
        // PHP method names are case-insensitive; the message is hardcoded, so it
        // always shows the name as the docs spell it
        $sa = SmartArray::new(['a']);

        [, $deprecations] = $this->captureDeprecations(function () use ($sa) {
            $sa->TORAW();
            $sa->ISMULTIPLEOF(2);
        });

        $this->assertSame('Replace ->toRaw() with ->asRaw()', self::withoutCallerSuffix($deprecations[0]));
        $this->assertSame('->isMultipleOf() is deprecated and will be removed in a future version', self::withoutCallerSuffix($deprecations[1]));
    }

    /**
     * Rows are [starting class, alias, resulting class].
     *
     * @return array<string, array{class-string<SmartArrayBase>, string, class-string<SmartArrayBase>}>
     */
    public static function conversionAliasProvider(): array
    {
        $rows = [];
        foreach (self::modeProvider() as $mode => [$class]) {
            foreach ([
                'toRaw'               => SmartArray::class,
                'noSmartStrings'      => SmartArray::class,
                'disableSmartStrings' => SmartArray::class,
                'toHtml'              => SmartArrayHtml::class,
                'withSmartStrings'    => SmartArrayHtml::class,
                'enableSmartStrings'  => SmartArrayHtml::class,
            ] as $alias => $expectedClass) {
                $rows["$mode: $alias"] = [$class, $alias, $expectedClass];
            }
        }
        return $rows;
    }

    #[DataProvider('conversionAliasProvider')]
    public function testConversionAliasesReturnTheReplacementsMode(string $class, string $alias, string $expectedClass): void
    {
        $sa = $class::new(['name' => 'Bob', 'tag' => '<b>']);

        [$result, ] = $this->captureDeprecations(fn() => $sa->$alias());

        $this->assertInstanceOf($expectedClass, $result);
        $this->assertSame(['name' => 'Bob', 'tag' => '<b>'], $result->toArray(), 'data survives the conversion unchanged');
    }

    #[DataProvider('conversionAliasProvider')]
    public function testConversionAliasesKeepQueryMetadata(string $class, string $alias, string $expectedClass): void
    {
        $sa = $class::new(['name' => 'Bob'], ['mysqli' => ['insert_id' => 42]]);

        [$result, ] = $this->captureDeprecations(fn() => $sa->$alias());

        $this->assertSame(['insert_id' => 42], $result->mysqli());
        $this->assertSame($sa->root(), $result->root());
    }

    #[DataProvider('modeProvider')]
    public function testIsMultipleOfMatchesPositionModulo(string $class): void
    {
        $rows = $class::new([['n' => 1], ['n' => 2], ['n' => 3], ['n' => 4]]);

        [$flags, ] = $this->captureDeprecations(function () use ($rows) {
            $flags = [];
            foreach ($rows as $row) {
                $flags[] = $row->isMultipleOf(2);
            }
            return $flags;
        });

        $this->assertSame([false, true, false, true], $flags);
    }

    #[DataProvider('modeProvider')]
    public function testIsMultipleOfIsAlwaysTrueOnARootArray(string $class): void
    {
        // A root array has position 0, and 0 % n === 0 for every n
        $rows = $class::new([['n' => 1], ['n' => 2]]);

        [$result, ] = $this->captureDeprecations(fn() => $rows->isMultipleOf(3));

        $this->assertSame(0, $rows->position());
        $this->assertTrue($result);
    }

    public function testIsMultipleOfWithoutAnArgumentThrowsArgumentCountError(): void
    {
        $sa = SmartArray::new(['a']);

        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage('Too few arguments to function Itools\SmartArray\SmartArrayBase::isMultipleOf(), 0 passed');

        $sa->isMultipleOf();
    }

    #[DataProvider('modeProvider')]
    public function testIsMultipleOfRejectsZeroAndNegativeValues(string $class): void
    {
        $sa = $class::new(['a']);

        foreach ([0, -2] as $value) {
            $thrown = null;
            try {
                $this->captureDeprecations(fn() => $sa->isMultipleOf($value));
            } catch (InvalidArgumentException $e) {
                $thrown = $e;
            }
            $this->assertInstanceOf(InvalidArgumentException::class, $thrown, "isMultipleOf($value) should throw");
            $this->assertSame('Value must be greater than 0.', $thrown->getMessage());
        }
    }

    #[DataProvider('modeProvider')]
    public function testSmartMapPassesSmartValuesAndReturnsSameMode(string $class): void
    {
        $sa = $class::new(['x' => 'alpha', 'y' => 'beta']);

        [$result, ] = $this->captureDeprecations(fn() => $sa->smartMap(function ($value, $key) use ($class) {
            $this->assertModeValue($key === 'x' ? 'alpha' : 'beta', $value, $class);
            return strtoupper((string) $value);
        }));

        $this->assertInstanceOf($class, $result);
        $this->assertSame(['x' => 'ALPHA', 'y' => 'BETA'], $result->toArray(), 'keys preserved');
    }

    #[DataProvider('modeProvider')]
    public function testSmartMapOnNestedRowsPassesChildArrays(string $class): void
    {
        $rows = $class::new([['id' => 1], ['id' => 2]]);

        [$result, ] = $this->captureDeprecations(fn() => $rows->smartMap(function ($row) use ($class) {
            $this->assertInstanceOf($class, $row);
            return $row->toArray();
        }));

        $this->assertSame([['id' => 1], ['id' => 2]], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testChunkSplitsIntoNestedArraysOfTheSameMode(string $class): void
    {
        $sa = $class::new(['a', 'b', 'c', 'd', 'e']);

        [$result, ] = $this->captureDeprecations(fn() => $sa->chunk(2));

        $this->assertInstanceOf($class, $result);
        $this->assertSame([['a', 'b'], ['c', 'd'], ['e']], $result->toArray(), 'last chunk is short');
        $this->assertInstanceOf($class, $result->first());
        $this->assertValidStructure($result);
    }

    #[DataProvider('modeProvider')]
    public function testChunkRejectsZeroAndNegativeSizes(string $class): void
    {
        $sa = $class::new(['a', 'b']);

        foreach ([0, -2] as $size) {
            $thrown = null;
            try {
                $this->captureDeprecations(fn() => $sa->chunk($size));
            } catch (InvalidArgumentException $e) {
                $thrown = $e;
            }
            $this->assertInstanceOf(InvalidArgumentException::class, $thrown, "chunk($size) should throw");
            $this->assertSame('Chunk size must be greater than 0.', $thrown->getMessage());
        }
    }

    /**
     * Aliases that return a new collection in the same mode. Rows are
     * [class, alias, args].
     *
     * @return array<string, array{class-string<SmartArrayBase>, string, array}>
     */
    public static function sameModeAliasProvider(): array
    {
        $calls = [
            'pluck'    => ['id'],
            'pluckNth' => [0],
            'smartMap' => [],
            'chunk'    => [1],
        ];
        $calls['smartMap'] = [static fn($value) => $value];

        $rows = [];
        foreach (self::modeProvider() as $mode => [$class]) {
            foreach ($calls as $alias => $args) {
                $rows["$mode: $alias"] = [$class, $alias, $args];
            }
        }
        return $rows;
    }

    #[DataProvider('sameModeAliasProvider')]
    public function testAliasResultsKeepQueryMetadata(string $class, string $alias, array $args): void
    {
        $rows = $class::new([['id' => 1], ['id' => 2]], ['mysqli' => ['insert_id' => 42]]);

        [$result, ] = $this->captureDeprecations(fn() => $rows->$alias(...$args));

        $this->assertMetadataPreserved($rows, $result);
    }

    //endregion
    //region Unknown methods

    public function testUnknownMethodThrowsWithTheCallersFileAndLine(): void
    {
        $sa = SmartArray::new(['a']);

        $line = __LINE__ + 2;
        try {
            $sa->totallyUnknown();
            $this->fail('expected an Error for an undefined method');
        } catch (Error $e) {
            $this->assertSame(
                "Call to undefined method SmartArray->totallyUnknown(), call ->help() for available methods.\n"
                . 'Occurred in ' . __FILE__ . ":$line in " . self::class . "->testUnknownMethodThrowsWithTheCallersFileAndLine()\nReported",
                $e->getMessage(),
            );
        }
    }

    /**
     * Names from other collection libraries and common LLM guesses, with the
     * suggestion didYouMean() maps them to. Rows are [called name, suggestion].
     *
     * @return array<string, array{string, string}>
     */
    public static function didYouMeanProvider(): array
    {
        return [
            'join'      => ['join', 'did you mean ->implode()?'],
            'dump'      => ['dump', 'did you mean ->debug()?'],
            'keyby'     => ['keyby', 'did you mean ->indexBy()?'],
            'not_empty' => ['not_empty', 'did you mean ->isNotEmpty()?'],
            'raw'       => ['raw', 'did you mean ->toArray()?'],
            'walk'      => ['walk', 'did you mean ->each()?'],
            'empty'     => ['empty', 'did you mean ->isEmpty()?'],
            'HEAD'      => ['HEAD', 'did you mean ->first()?'],
        ];
    }

    #[DataProvider('didYouMeanProvider')]
    public function testUnknownMethodSuggestsTheRealMethod(string $called, string $suggestion): void
    {
        $sa = SmartArray::new(['a']);

        $this->assertSame(
            "Call to undefined method SmartArray->$called(), $suggestion",
            $this->firstLineOfError(fn() => $sa->$called()),
        );
    }

    public function testUnknownMethodMatchingIsCaseInsensitiveButEchoesTheCallersSpelling(): void
    {
        $sa = SmartArray::new(['a']);

        // 'sortbycolumn' is the alias list entry; the message repeats how it was typed
        $this->assertSame(
            'Call to undefined method SmartArray->SortByColumn(), did you mean ->sortBy()?',
            $this->firstLineOfError(fn() => $sa->SortByColumn()),
        );
    }

    public function testUnknownMethodWithNoMatchPointsAtHelp(): void
    {
        $sa = SmartArray::new(['a']);

        $this->assertSame(
            'Call to undefined method SmartArray->isMultipleOff(), call ->help() for available methods.',
            $this->firstLineOfError(fn() => $sa->isMultipleOff()),
            'a near-miss on a real method name is not in the alias list',
        );
    }

    public function testUnknownMethodErrorNamesTheSubclass(): void
    {
        $sa = SmartArrayHtml::new(['a']);

        $this->assertSame(
            'Call to undefined method SmartArrayHtml->join(), did you mean ->implode()?',
            $this->firstLineOfError(fn() => $sa->join()),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function removedMethodProvider(): array
    {
        return [
            'usingSmartStrings' => ['usingSmartStrings'],
            'setLoadHandler'    => ['setLoadHandler'],
        ];
    }

    #[DataProvider('removedMethodProvider')]
    public function testRemovedMethodsGetTheOrdinaryUndefinedMethodError(string $method): void
    {
        // The Fatal-stage match arm in __call() is currently empty, so removed
        // names take the unknown-method path with no replacement named
        $sa = SmartArray::new(['a']);

        $this->assertSame(
            "Call to undefined method SmartArray->$method(), call ->help() for available methods.",
            $this->firstLineOfError(fn() => $sa->$method()),
        );
    }

    public function testUnknownStaticCallThrowsWithTheStaticSeparator(): void
    {
        $line = __LINE__ + 2;
        try {
            SmartArray::bogus();
            $this->fail('expected an Error for an undefined static method');
        } catch (Error $e) {
            $this->assertSame(
                "Call to undefined method SmartArray::bogus(), call ->help() for available methods.\n"
                . 'Occurred in ' . __FILE__ . ":$line in " . self::class . "->testUnknownStaticCallThrowsWithTheStaticSeparator()\nReported",
                $e->getMessage(),
            );
        }
    }

    public function testUnknownStaticCallOffersNoSuggestion(): void
    {
        // __callStatic() skips didYouMean(): a static call is the wrong shape
        // for every method on the list
        $this->assertSame(
            'Call to undefined method SmartArrayHtml::join(), call ->help() for available methods.',
            $this->firstLineOfError(fn() => SmartArrayHtml::join()),
        );
    }

    public function testStaticCallOfAnInstanceMethodIsPhpsOwnError(): void
    {
        // Declared methods never reach __callStatic()
        $this->assertSame(
            'Non-static method Itools\SmartArray\SmartArray::first() cannot be called statically',
            $this->firstLineOfError(static fn() => SmartArray::first()),
        );
    }

    //endregion
    //region SmartArrayRaw class

    public function testSmartArrayRawConstructorDeprecatesAndBehavesLikeSmartArray(): void
    {
        [$sa, $deprecations] = $this->captureDeprecations(fn() => new SmartArrayRaw(['name' => 'Bob']));

        $this->assertSame(['SmartArrayRaw is deprecated. Use SmartArray instead.'], $deprecations);
        $this->assertInstanceOf(SmartArray::class, $sa);
        $this->assertSame(['name' => 'Bob'], $sa->toArray());
        $this->assertSame('Bob', $sa->name, 'raw mode: values are plain PHP, not SmartStrings');
    }

    public function testSmartArrayRawNewDeprecatesTwice(): void
    {
        [$sa, $deprecations] = $this->captureDeprecations(fn() => SmartArrayRaw::new(['name' => 'Bob']));

        $this->assertSame([
            'SmartArrayRaw::new() is deprecated. Use SmartArray::new() instead.',
            'SmartArrayRaw is deprecated. Use SmartArray instead.',
        ], $deprecations, 'the factory logs, then the constructor it calls logs again');
        $this->assertSame(['name' => 'Bob'], $sa->toArray());
    }

    public function testSmartArrayRawNewIgnoresTheLegacyBooleanArgument(): void
    {
        // REVIEW: SmartArray::new($data, true) throws "Cannot create SmartArray
        // with useSmartStrings=true", but the SmartArrayRaw factory drops the
        // boolean and returns raw values with no signal beyond its own class
        // deprecation - the exact silent-downgrade the throw was added to stop.
        [$sa, ] = $this->captureDeprecations(fn() => SmartArrayRaw::new(['name' => 'Bob'], true));

        $this->assertInstanceOf(SmartArrayRaw::class, $sa);
        $this->assertSame('Bob', $sa->name);
    }

    public function testSmartArrayRawRowsAreAlsoSmartArrayRaw(): void
    {
        // Every row is built by the deprecated constructor, so a 2-row result
        // logs three times: the outer array plus one per row
        [$sa, $deprecations] = $this->captureDeprecations(fn() => new SmartArrayRaw([['id' => 1], ['id' => 2]]));

        $this->assertCount(3, $deprecations);
        $this->assertInstanceOf(SmartArrayRaw::class, $sa->first());
        $this->assertSame(['id' => 1], $sa->first()->toArray());
    }

    //endregion
    //region Helpers

    private static function fixture(string $class, string $kind): SmartArrayBase
    {
        return match ($kind) {
            'flat'   => $class::new(['a', 'b', 'c']),
            'nested' => $class::new(Fixtures::records()),
        };
    }

    /**
     * Strip the " in file:line." that logDeprecation() appends, so the message
     * text itself can be compared to a literal.
     */
    private static function withoutCallerSuffix(string $message): string
    {
        return preg_replace('/ in DeprecationsTest\.php:\d+\.$/', '', $message);
    }

    /**
     * Run $fn and return the first line of the Error it throws.
     */
    private function firstLineOfError(Closure $fn): string
    {
        try {
            $fn();
        } catch (Error $e) {
            return strtok($e->getMessage(), "\n");
        }
        $this->fail('expected an Error');
    }

    //endregion
}
