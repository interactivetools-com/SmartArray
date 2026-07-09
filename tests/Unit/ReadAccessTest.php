<?php
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
 * Read access: get(), first(), last(), nth(), __get, offsetGet, __isset.
 *
 * Pins exact return types per mode (raw scalar vs SmartString, SmartNull vs
 * null) and the warning contract (missing keys warn with the caller's
 * file:line; happy paths and empty arrays stay silent).
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
    public function testGetMissingKeyWarnsAndReturnsSmartNull(string $class): void
    {
        $sa = $class::new(['name' => 'Bob']);

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
    public function testGetSmartObjectDefaultPassesThrough(string $class): void
    {
        // Matching-mode Smart defaults pass through as the same instance.
        // Cross-mode defaults (a SmartString default on SmartArray, a raw
        // SmartArray default on SmartArrayHtml) currently throw a TypeError
        // from the subclass return declarations - pinned pending Q14.
        $sa = $class::new(['name' => 'Bob']);

        $default = $class === SmartArrayHtml::class ? new SmartString('fallback') : SmartArray::new(['x']);
        $this->assertSame($default, $sa->get('zzz', $default));
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
    //region nth()

    #[DataProvider('modeProvider')]
    public function testNthIsPositionalNotKeyBased(string $class): void
    {
        // Non-sequential integer keys: nth() must count positions, not look up keys
        $sa = $class::new([2 => 'first', 4 => 'second', 6 => 'third']);

        $this->assertModeValue('first', $sa->nth(0), $class);
        $this->assertModeValue('second', $sa->nth(1), $class);
        $this->assertModeValue('third', $sa->nth(2), $class);

        $assoc = $class::new(['a' => 'alpha', 'b' => 'beta']);
        $this->assertModeValue('beta', $assoc->nth(1), $class);
    }

    #[DataProvider('modeProvider')]
    public function testNthNegativeCountsFromEnd(string $class): void
    {
        $sa = $class::new(['a', 'b', 'c']);

        $this->assertModeValue('c', $sa->nth(-1), $class);
        $this->assertModeValue('b', $sa->nth(-2), $class);
        $this->assertModeValue('a', $sa->nth(-3), $class);
    }

    #[DataProvider('modeProvider')]
    public function testNthOutOfBoundsReturnsSmartNull(string $class): void
    {
        $sa = $class::new(['a', 'b']);

        $this->assertSmartNull($sa->nth(2));
        $this->assertSmartNull($sa->nth(-3));
        $this->assertSmartNull($class::new([])->nth(0));
        $this->assertSmartNull($class::new([])->nth(-1));
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
        $sa = $class::new(['name' => 'Bob']);

        [$result, $output] = $this->captureOutput(fn() => $sa->missing->deeper->deepest);

        $this->assertSmartNull($result, 'chaining off a missing key must not fatal');
        $this->assertSame(1, substr_count($output, 'Warning:'), 'only the first missing access warns; SmartNull chains silently');
        $this->assertMatchesRegularExpression('/Warning: missing is undefined in ReadAccessTest\.php:\d+/', $output);
    }

    #[DataProvider('modeProvider')]
    public function testPropertyGetMissingMethodNameKeySuggestsBraces(string $class): void
    {
        // "$sa->pluck" in a string is a common mistake for "{$sa->pluck(...)}"
        $sa = $class::new(['name' => 'Bob']);

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

    //endregion
    //region __isset / offsetExists

    #[DataProvider('modeProvider')]
    public function testIssetChecksKeyExistenceNotNullness(string $class): void
    {
        $sa = $class::new(['name' => 'Bob', 'middle' => null]);

        // Unlike plain PHP arrays, isset() on a stored null is true: it checks
        // key existence (so ?? only fires for missing keys, per the get() docblock)
        $this->assertTrue(isset($sa->name));
        $this->assertTrue(isset($sa->middle));
        $this->assertFalse(isset($sa->zzz));

        [, $output] = $this->captureOutput(function () use ($sa) {
            $this->assertTrue($sa->offsetExists('middle'));
            $this->assertFalse($sa->offsetExists('zzz'));
            $this->assertTrue(isset($sa['middle']));
        });
        $this->assertSame('', $output, 'existence checks never warn or notify, even via array syntax');
    }

    //endregion
}
