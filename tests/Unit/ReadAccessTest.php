<?php
/** @noinspection PhpDeprecationInspection */ // get() is a Silent alias; its behavior is pinned here
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\Support\Fixtures;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * Read access: __get (property access), first(), last(), at(), get()
 * (deprecated, Silent), offsetGet, __isset.
 *
 * Pins exact return types per mode (raw scalar vs SmartString, SmartNull vs
 * null) and the warning contract (missing keys warn with the caller's
 * file:line; happy paths and empty arrays stay silent). get() is a Silent
 * alias (Deprecations trait) but keeps full behavior - including the
 * default parameter and Smart-key unwrapping nothing else replicates - so
 * its contract stays pinned here alongside __get.
 */
class ReadAccessTest extends SmartArrayTestCase
{
    //region get()

    /**
     * @return array<string, array{class-string<SmartArrayBase>, string|int|float|bool|null}>
     */
    public static function modeAndScalarProvider(): array
    {
        $cases = [];
        foreach (self::modeProvider() as $mode => [$class]) {
            foreach (Fixtures::edgeScalars() as $label => $value) {
                $cases["$mode: $label"] = [$class, $value];
            }
        }
        return $cases;
    }

    #[DataProvider('modeAndScalarProvider')]
    public function testGetReturnsStoredValueInModeWrapper(string $class, string|int|float|bool|null $value): void
    {
        $sa = $class::new(['key' => $value]);

        [$result, $output] = $this->captureOutput(fn() => $sa->get('key'));

        $this->assertModeValue($value, $result, $class);
        $this->assertSame('', $output, 'reading an existing key should not warn');
    }

    #[DataProvider('modeProvider')]
    public function testGetNestedReturnsChildOfSameMode(string $class): void
    {
        $sa = $class::new(['user' => ['name' => 'Bob']]);

        $result = $sa->get('user');

        $this->assertInstanceOf($class, $result);
        $this->assertSame(['name' => 'Bob'], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testGetSupportsKeysPropertySyntaxCant(string $class): void
    {
        $sa = $class::new(['users.id' => 5, '' => 'empty', 0 => 'zero']);

        $this->assertModeValue(5, $sa->get('users.id'), $class);
        $this->assertModeValue('empty', $sa->get(''), $class);
        $this->assertModeValue('zero', $sa->get(0), $class);
        $this->assertModeValue('zero', $sa->get('0'), $class, 'string and int forms of a numeric key are the same PHP key');
    }

    #[DataProvider('modeProvider')]
    public function testGetUnwrapsSmartKeys(string $class): void
    {
        // Smart keys unwrap to raw values before lookup, then coerce like PHP array
        // keys. Property syntax can't do this: ->{$smartString} goes through
        // __toString, which HTML-encodes in HTML mode, so 'a&b' would look up 'a&amp;b'
        $sa = $class::new(['5' => 'five', 'a&b' => 'amp', '' => 'empty', 2 => 'two']);

        $this->assertModeValue('five', $sa->get(new SmartString(5)), $class);
        $this->assertModeValue('amp', $sa->get(new SmartString('a&b')), $class, 'reads the raw key, not the encoded form');
        $this->assertModeValue('two', $sa->get(new SmartString(2.7)), $class, 'float keys truncate to int like PHP array keys');
        $this->assertModeValue('empty', $sa->get($class::new([])->first()), $class, 'a SmartNull key reads key "" like PHP null');
    }

    #[DataProvider('modeProvider')]
    public function testGetMissingKeyWarnsAndReturnsSmartNull(string $class): void
    {
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [$result, $output] = $this->captureOutput(fn() => $sa->get('zzz'));

        $this->assertSmartNull($result);
        $this->assertMatchesRegularExpression('/^\nWarning: zzz is undefined in ReadAccessTest\.php:\d+\n\n$/', $output);
    }

    #[DataProvider('modeProvider')]
    public function testGetMissingKeyOnEmptyArrayIsSilent(string $class): void
    {
        $sa = $class::new([]);

        [$result, $output] = $this->captureOutput(fn() => $sa->get('anything'));

        $this->assertSmartNull($result);
        $this->assertSame('', $output, 'empty arrays are expected to have no keys; no warning');
    }

    #[DataProvider('modeProvider')]
    public function testGetDefaultUsedOnlyForMissingKeys(string $class): void
    {
        $sa = $class::new(['name' => 'Bob', 'middle' => null]);

        [, $output] = $this->captureOutput(function () use ($sa, $class) {
            $this->assertModeValue('Bob', $sa->get('name', 'n/a'), $class, 'existing key ignores default');
            $this->assertModeValue(null, $sa->get('middle', 'n/a'), $class, 'stored null is a value, not a missing key');
            $this->assertModeValue('n/a', $sa->get('zzz', 'n/a'), $class, 'missing key returns default');
            $this->assertModeValue('n/a', $class::new([])->get('zzz', 'n/a'), $class, 'missing key on empty array returns default');
        });

        $this->assertSame('', $output, 'providing a default disables the missing-key warning');
    }

    #[DataProvider('modeProvider')]
    public function testGetArrayDefaultBecomesSameModeArray(string $class): void
    {
        $sa = $class::new(['name' => 'Bob'], ['mysqli' => ['insert_id' => 42]]);

        $result = $sa->get('zzz', ['a' => 1]);

        $this->assertInstanceOf($class, $result);
        $this->assertSame(['a' => 1], $result->toArray());
        $this->assertMetadataPreserved($sa, $result);
    }

    #[DataProvider('modeProvider')]
    public function testGetSmartDefaultsActLikeStoredValues(string $class): void
    {
        // Q14: Smart defaults unwrap and re-wrap for this array's mode, exactly
        // as if the default had been the stored value. Cross-mode combinations
        // previously threw a TypeError from the subclass return declarations.
        $sa = $class::new(['name' => 'Bob']);

        $this->assertModeValue('fallback', $sa->get('zzz', new SmartString('fallback')), $class);

        $fromRawDefault  = $sa->get('zzz', SmartArray::new(['x' => 1]));
        $fromHtmlDefault = $sa->get('zzz', SmartArrayHtml::new(['x' => 1]));
        $this->assertInstanceOf($class, $fromRawDefault);
        $this->assertInstanceOf($class, $fromHtmlDefault);
        $this->assertSame(['x' => 1], $fromRawDefault->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testGetSmartNullDefaultReturnsNullValue(string $class): void
    {
        // Q7: the common chain `$row->get('color', $other->first())` no longer
        // throws when $other is empty - the SmartNull default acts like a stored null
        $sa = $class::new(['name' => 'Bob']);

        $smartNullDefault = $class::new([])->first();

        $this->assertModeValue(null, $sa->get('zzz', $smartNullDefault), $class);
    }

    #[DataProvider('modeProvider')]
    public function testGetUnsupportedDefaultThrows(string $class): void
    {
        $sa = $class::new(['name' => 'Bob']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported default value type: stdClass');

        $sa->get('zzz', new stdClass());
    }

    //endregion
    //region first() / last()

    #[DataProvider('modeProvider')]
    public function testFirstAndLastReturnEndpointValues(string $class): void
    {
        $flat = $class::new(['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma']);

        $this->assertModeValue('alpha', $flat->first(), $class);
        $this->assertModeValue('gamma', $flat->last(), $class);
    }

    #[DataProvider('modeProvider')]
    public function testFirstAndLastOnEmptyReturnSmartNull(string $class): void
    {
        $empty = $class::new([]);

        $this->assertSmartNull($empty->first());
        $this->assertSmartNull($empty->last());
    }

    public function testFirstStoredNullIsNullNotSmartNull(): void
    {
        // The empty-vs-null distinction: [null] has a first element and it is null
        $rawFirst = SmartArray::new([null])->first();
        $this->assertNull($rawFirst);

        $htmlFirst = SmartArrayHtml::new([null])->first();
        $this->assertInstanceOf(SmartString::class, $htmlFirst);
        $this->assertNull($htmlFirst->value());
    }

    #[DataProvider('modeProvider')]
    public function testFirstAndLastNestedReturnPositionedRows(string $class): void
    {
        $rows = $class::new(Fixtures::records());

        $first = $rows->first();
        $last  = $rows->last();

        $this->assertInstanceOf($class, $first);
        $this->assertSame(Fixtures::records()[0], $first->toArray());
        $this->assertTrue($first->isFirst());
        $this->assertFalse($first->isLast());
        $this->assertSame(1, $first->position());

        $this->assertInstanceOf($class, $last);
        $this->assertSame(Fixtures::records()[3], $last->toArray());
        $this->assertTrue($last->isLast());
        $this->assertSame(4, $last->position());
    }

    //endregion
    //region at()

    #[DataProvider('modeProvider')]
    public function testAtIsPositionalNotKeyBased(string $class): void
    {
        // Non-sequential integer keys: at() must count positions, not look up keys
        $sa = $class::new([2 => 'first', 4 => 'second', 6 => 'third']);

        $this->assertModeValue('first', $sa->at(0), $class);
        $this->assertModeValue('second', $sa->at(1), $class);
        $this->assertModeValue('third', $sa->at(2), $class);

        $assoc = $class::new(['a' => 'alpha', 'b' => 'beta']);
        $this->assertModeValue('beta', $assoc->at(1), $class);
    }

    #[DataProvider('modeProvider')]
    public function testAtNegativeCountsFromEnd(string $class): void
    {
        $sa = $class::new(['a', 'b', 'c']);

        $this->assertModeValue('c', $sa->at(-1), $class);
        $this->assertModeValue('b', $sa->at(-2), $class);
        $this->assertModeValue('a', $sa->at(-3), $class);
    }

    #[DataProvider('modeProvider')]
    public function testAtOutOfBoundsReturnsSmartNull(string $class): void
    {
        $sa = $class::new(['a', 'b']);

        $this->assertSmartNull($sa->at(2));
        $this->assertSmartNull($sa->at(-3));
        $this->assertSmartNull($class::new([])->at(0));
        $this->assertSmartNull($class::new([])->at(-1));
    }

    #[DataProvider('modeProvider')]
    public function testAtUnwrapsSmartStringIndexes(string $class): void
    {
        // Positions read from another array work directly (MySQL returns numeric strings)
        $sa = $class::new(['a', 'b', 'c']);

        $this->assertModeValue('b', $sa->at(new SmartString('1')), $class);
        $this->assertModeValue('c', $sa->at(new SmartString(-1)), $class);
    }

    //endregion
    //region __get (property access)

    #[DataProvider('modeProvider')]
    public function testPropertyGetReturnsValueSilently(string $class): void
    {
        $sa = $class::new(['name' => 'Bob', 'user' => ['city' => 'Vancouver']]);

        [, $output] = $this->captureOutput(function () use ($sa, $class) {
            $this->assertModeValue('Bob', $sa->name, $class);
            $this->assertInstanceOf($class, $sa->user);
            $this->assertModeValue('Vancouver', $sa->user->city, $class, 'chained property access');
        });

        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testPropertyGetMissingWarnsOnceAndChainsSafely(string $class): void
    {
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [$result, $output] = $this->captureOutput(fn() => $sa->missing->deeper->deepest);

        $this->assertSmartNull($result, 'chaining off a missing key must not fatal');
        $this->assertSame(1, substr_count($output, 'Warning:'), 'only the first missing access warns; SmartNull chains silently');
        $this->assertMatchesRegularExpression('/Warning: missing is undefined in ReadAccessTest\.php:\d+/', $output);
    }

    #[DataProvider('modeProvider')]
    public function testPropertyGetMissingMethodNameKeySuggestsBraces(string $class): void
    {
        // "$sa->pluck" in a string is a common mistake for "{$sa->pluck(...)}"
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [$result, $output] = $this->captureOutput(fn() => $sa->pluck);

        $this->assertSmartNull($result);
        $this->assertStringContainsString('wrap methods in braces', $output);
    }

    //endregion
    //region offsetGet (deprecated array syntax)

    #[DataProvider('modeProvider')]
    public function testOffsetGetReturnsValueAndNotifiesDeprecation(string $class): void
    {
        $this->assertSame('notify', SmartArrayBase::$onOffsetAccess, 'precondition: default mode');
        $sa = $class::new(['name' => 'Bob']);

        [[$result, $output], $deprecations] = $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $sa['name'])
        );

        $this->assertModeValue('Bob', $result, $class);
        $this->assertStringContainsString("Deprecated:", $output);
        $this->assertStringContainsString("Replace ['name'] with ->name", $output);
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString("Replace ['name'] with ->name", $deprecations[0]);
    }

    #[DataProvider('modeProvider')]
    public function testOffsetGetMissingKeyWarnsAfterTheDeprecationNotice(string $class): void
    {
        // Both signals fire: the deprecation notice for the [] syntax, then the
        // missing-key warning from the read itself
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [[$result, $output], $deprecations] = $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $sa['zzz'])
        );

        $this->assertSmartNull($result);
        $this->assertStringContainsString("Replace ['zzz'] with ->zzz", $output);
        $this->assertStringContainsString('Warning: zzz is undefined', $output);
        $this->assertCount(1, $deprecations);
    }

    //endregion
    //region __isset / offsetExists

    #[DataProvider('modeProvider')]
    public function testIssetTreatsStoredNullAsMissing(string $class): void
    {
        $sa = $class::new(['name' => 'Bob', 'middle' => null]);

        // Like plain PHP arrays, isset() on a stored null is false, so ??
        // fallbacks fire on stored nulls and missing keys alike
        $this->assertTrue(isset($sa->name));
        $this->assertFalse(isset($sa->middle));
        $this->assertFalse(isset($sa->zzz));

        // Array-syntax existence checks are signal-free: for `??` and empty()
        // PHP also calls offsetGet(), which carries the one notice
        [, $output] = $this->captureOutput(function () use ($sa) {
            $this->assertFalse($sa->offsetExists('middle'));
            $this->assertFalse($sa->offsetExists('zzz'));
            $this->assertTrue(isset($sa['name']));
        });
        $this->assertSame('', $output, 'existence checks emit nothing');
    }

    #[DataProvider('modeProvider')]
    public function testNullCoalescingFiresOnStoredNullAndMissingKeys(string $class): void
    {
        $sa = $class::new(['name' => 'Bob', 'middle' => null]);

        // ?? short-circuits on __isset() before any value is fetched or wrapped,
        // so the fallback comes through as-is (a raw string) in both modes, and
        // missing keys produce no undefined-key warning
        $this->assertSame('Bob', (string)($sa->name ?? '(fallback)'));
        $this->assertSame('(fallback)', $sa->middle ?? '(fallback)');
        $this->assertSame('(fallback)', $sa->zzz ?? '(fallback)');
    }

    //endregion
}
