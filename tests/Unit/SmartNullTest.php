<?php
/** @noinspection PhpDeprecationInspection */ // get() is a Silent alias; SmartNull results from it are pinned here
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Error;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\SmartNull;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * SmartNull: the chainable null object returned for missing elements.
 *
 * The contract is "chain anything, fatal nothing": property access, array
 * access, and method calls all keep working, and the chain resolves to an
 * empty/null value of the right type for the mode it was born in.
 *
 * Method calls are handled by __call. In HTML mode, public SmartString methods
 * are tried first and the result decides what comes back: value producers like
 * or() return their fallback, terminals like int() return their scalar, and
 * transforms like trim() propagate the same SmartNull so the chain stays open
 * for either a value or a collection ending. map() propagates without running
 * its callback (a missing key has no value to pass). Everything else delegates
 * to an empty SmartArray/SmartArrayHtml of the same mode, and unknown names
 * throw the library's undefined-method Error.
 */
class SmartNullTest extends SmartArrayTestCase
{
    //region Helpers

    /**
     * A SmartNull born from an array of the given mode, carrying query metadata.
     */
    private function smartNullFrom(string $class, array $mysqli = []): SmartNull
    {
        $result = $class::new([], ['mysqli' => $mysqli])->first();
        $this->assertInstanceOf(SmartNull::class, $result, 'precondition: first() on empty returns SmartNull');
        return $result;
    }

    //endregion
    //region Sources: how SmartNulls arise

    #[DataProvider('modeProvider')]
    public function testMissingPropertyWarnsAndReturnsSmartNull(string $class): void
    {
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [$result, $output] = $this->captureOutput(fn() => $sa->zzz);

        $this->assertInstanceOf(SmartNull::class, $result);
        $this->assertMatchesRegularExpression('/^\nWarning: zzz is undefined in SmartNullTest\.php:\d+\n\n$/', $output);
    }

    #[DataProvider('modeProvider')]
    public function testGetWithoutDefaultWarnsAndReturnsSmartNull(string $class): void
    {
        $sa = $class::new([['name' => 'Bob']])->first();  // only result-set rows warn

        [$result, $output] = $this->captureOutput(fn() => $sa->get('zzz'));

        $this->assertInstanceOf(SmartNull::class, $result);
        $this->assertMatchesRegularExpression('/^\nWarning: zzz is undefined in SmartNullTest\.php:\d+\n\n$/', $output);
    }

    #[DataProvider('modeProvider')]
    public function testFirstAndLastOnEmptyReturnSmartNullSilently(string $class): void
    {
        $empty = $class::new([]);

        [, $output] = $this->captureOutput(function () use ($empty) {
            $this->assertInstanceOf(SmartNull::class, $empty->first());
            $this->assertInstanceOf(SmartNull::class, $empty->last());
        });

        $this->assertSame('', $output, 'an empty result set is expected, not a mistake: no warning');
    }

    #[DataProvider('modeProvider')]
    public function testOutOfRangePositionReturnsSmartNullSilently(string $class): void
    {
        $sa = $class::new(['a', 'b']);

        [$result, $output] = $this->captureOutput(fn() => $sa->at(9));

        $this->assertInstanceOf(SmartNull::class, $result);
        $this->assertSame('', $output);
    }

    //endregion
    //region Value and emptiness

    #[DataProvider('modeProvider')]
    public function testValueReturnsNull(string $class): void
    {
        $this->assertNull($this->smartNullFrom($class)->value());
    }

    #[DataProvider('modeProvider')]
    public function testCountIsZeroThroughMethodAndCountable(string $class): void
    {
        $smartNull = $this->smartNullFrom($class);

        $this->assertSame(0, $smartNull->count());
        $this->assertSame(0, count($smartNull), 'Countable interface');
    }

    #[DataProvider('modeProvider')]
    public function testToArrayReturnsEmptyArray(string $class): void
    {
        $this->assertSame([], $this->smartNullFrom($class)->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testIsEmptyTrueAndIsNotEmptyFalse(string $class): void
    {
        $smartNull = $this->smartNullFrom($class);

        $this->assertTrue($smartNull->isEmpty());
        $this->assertFalse($smartNull->isNotEmpty());
    }

    //endregion
    //region Chaining

    #[DataProvider('modeProvider')]
    public function testPropertyChainingReturnsTheSameSmartNull(string $class): void
    {
        $smartNull = $this->smartNullFrom($class);

        [$result, $output] = $this->captureOutput(fn() => $smartNull->a->b->c);

        $this->assertSame($smartNull, $result, 'property access returns $this, so chains never allocate or warn');
        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testArrayAccessChainingReturnsTheSameSmartNull(string $class): void
    {
        $smartNull = $this->smartNullFrom($class);

        [$result, $output] = $this->captureOutput(fn() => $smartNull['a']['b'][0]);

        $this->assertSame($smartNull, $result, 'array reads return $this, so chains keep working');
        $this->assertSame(3, substr_count($output, "\nDeprecated: "), 'bracket syntax notifies on missing-data paths like anywhere else');
    }

    #[DataProvider('modeProvider')]
    public function testMixedPropertyArrayAndMethodChainResolvesToNull(string $class): void
    {
        $smartNull = $this->smartNullFrom($class);

        [$result, $output] = $this->captureOutput(fn() => $smartNull['rows']->first()->name->value());

        $this->assertNull($result, 'the whole chain resolves to null with no fatal');
        $this->assertSame(1, substr_count($output, "\nDeprecated: "), 'the one bracket read notifies');
    }

    #[DataProvider('modeProvider')]
    public function testOffsetExistsIsAlwaysFalse(string $class): void
    {
        $smartNull = $this->smartNullFrom($class);

        $this->assertFalse($smartNull->offsetExists('anything'));
        $this->assertFalse($smartNull->offsetExists(0));
    }

    #[DataProvider('modeProvider')]
    public function testForeachIteratesZeroTimes(string $class): void
    {
        $smartNull = $this->smartNullFrom($class);

        $seen = [];
        foreach ($smartNull as $key => $value) {
            $seen[] = [$key, $value];
        }

        $this->assertSame([], $seen);
    }

    #[DataProvider('modeProvider')]
    public function testGetIteratorReturnsEmptyCollectionIterator(string $class): void
    {
        // getIterator exists on SmartString too, but a missing value iterates like
        // an empty collection instead of throwing SmartString's can't-foreach error
        $smartNull = $this->smartNullFrom($class);

        $this->assertSame([], iterator_to_array($smartNull->getIterator()));
    }

    //endregion
    //region Method delegation: SmartArray methods and mode inheritance

    #[DataProvider('modeProvider')]
    public function testSmartArrayMethodsDelegateToAnEmptyArrayOfTheSameMode(string $class): void
    {
        // Mode inheritance in both directions: a SmartNull born from SmartArrayHtml
        // keeps returning SmartArrayHtml, one born from SmartArray keeps returning SmartArray
        $smartNull = $this->smartNullFrom($class);

        $filtered = $smartNull->filter(fn() => true);
        $keys     = $smartNull->keys();
        $sorted   = $smartNull->sort();

        $this->assertSame($class, get_class($filtered));
        $this->assertSame($class, get_class($keys));
        $this->assertSame($class, get_class($sorted));
        $this->assertSame([], $filtered->toArray());
    }

    public function testImplodeFollowsTheModeOfTheSourceArray(): void
    {
        // implode is the clearest mode tell: raw returns a string, html a SmartString
        $this->assertSame('', $this->smartNullFrom(SmartArray::class)->implode(', '));

        $htmlResult = $this->smartNullFrom(SmartArrayHtml::class)->implode(', ');
        $this->assertInstanceOf(SmartString::class, $htmlResult);
        $this->assertSame('', $htmlResult->value());
    }

    #[DataProvider('modeProvider')]
    public function testDelegatedArraysCarryTheSourceMetadata(string $class): void
    {
        // __call passes mysqli/root/loadHandler to the delegate array, same as
        // asRaw()/asHtml(), so metadata answers don't depend on which path a
        // chain took through SmartNull.
        $smartNull = $this->smartNullFrom($class, ['insert_id' => 42]);

        $this->assertSame(['insert_id' => 42], $smartNull->mysqli());
        $this->assertSame(['insert_id' => 42], $smartNull->filter(fn() => true)->mysqli());
    }

    //endregion
    //region Method delegation: SmartString methods

    public function testTransformsPropagateTheSameSmartNullInHtmlMode(): void
    {
        // A missing key stays missing through a chain: there is nothing to
        // transform, so the SmartNull itself comes back and the chain stays
        // open for either ending, ->or() for a value or ->implode() for a collection
        $smartNull = $this->smartNullFrom(SmartArrayHtml::class);

        $transforms = ['trim' => [], 'maxChars' => [5], 'dateFormat' => ['Y-m-d'], 'numberFormat' => [], 'add' => [5]];
        foreach ($transforms as $method => $args) {
            $this->assertSame($smartNull, $smartNull->$method(...$args), "->$method() propagates");
        }

        $this->assertSame('n/a', $smartNull->trim()->maxChars(5)->or('n/a')->value(), 'chain resolves at the end');
        $this->assertSame('', $smartNull->trim()->implode(', ')->value(), 'collection ending still works after a transform');
    }

    public function testValueProducersEndTheChainInHtmlMode(): void
    {
        $smartNull = $this->smartNullFrom(SmartArrayHtml::class);

        $fallback = $smartNull->or('n/a');
        $this->assertInstanceOf(SmartString::class, $fallback);
        $this->assertSame('n/a', $fallback->value());
        $this->assertSame('n/a', $smartNull->ifNull('n/a')->value());
    }

    public function testOrThrowThrowsInHtmlMode(): void
    {
        $smartNull = $this->smartNullFrom(SmartArrayHtml::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no user found');

        $smartNull->orThrow('no user found');
    }

    public function testMapPropagatesWithoutRunningTheCallbackInHtmlMode(): void
    {
        // map() takes a user callback, and a missing key has no value to pass it
        $smartNull = $this->smartNullFrom(SmartArrayHtml::class);
        $calls     = 0;

        $result = $smartNull->map(function ($value) use (&$calls) {
            $calls++;
            return 'computed';
        });

        $this->assertSame($smartNull, $result);
        $this->assertSame(0, $calls, 'the callback never runs on a missing key');
        $this->assertSame('n/a', $smartNull->map('strtoupper')->or('n/a')->value(), 'chain stays open after map');
    }

    public function testApplyAliasPropagatesLikeMapInHtmlMode(): void
    {
        // apply() is SmartString's deprecated alias for map(); both spellings
        // propagate the same way on a missing key
        $smartNull = $this->smartNullFrom(SmartArrayHtml::class);
        $calls     = 0;

        $result = $smartNull->apply(function ($value) use (&$calls) {
            $calls++;
            return 'computed';
        });

        $this->assertSame($smartNull, $result);
        $this->assertSame(0, $calls, 'the callback never runs on a missing key');
        $this->assertSame('n/a', $smartNull->apply('strtoupper')->or('n/a')->value(), 'chain stays open after apply');
    }

    public function testDeprecatedSmartStringShimsWorkOnMissingKeysInHtmlMode(): void
    {
        // noEncode/toString/jsEncode/stripTags only exist inside SmartString's
        // __call, which method_exists() can't see, so they need their own list in
        // SmartNull::__call. Each one answers like it would on a present null
        // value and logs its normal deprecation instead of throwing.
        $smartNull = $this->smartNullFrom(SmartArrayHtml::class);

        [$result, $messages] = $this->captureDeprecations(fn() => $smartNull->noEncode());
        $this->assertNull($result, 'noEncode() returns null, same as on a present null value');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('rawHtml()', $messages[0]);

        [$result, $messages] = $this->captureDeprecations(fn() => $smartNull->toString());
        $this->assertSame('', $result);
        $this->assertCount(1, $messages);

        [$result, $messages] = $this->captureDeprecations(fn() => $smartNull->jsEncode());
        $this->assertSame('', $result);
        $this->assertCount(1, $messages);

        [$result, $messages] = $this->captureDeprecations(fn() => $smartNull->stripTags());
        $this->assertSame($smartNull, $result, 'stripTags() propagates, chain stays open');
        $this->assertCount(1, $messages);
    }

    public function testMapStillRunsOnAKeyThatExistsWithANullValue(): void
    {
        // The boundary map() propagation must not cross: NULL is a present value
        // (SmartString(null), the ordinary path), only an absent key is a SmartNull
        $row    = SmartArrayHtml::new(['bio' => null]);
        $result = $row->bio->map(fn($value) => $value ?? 'default');

        $this->assertInstanceOf(SmartString::class, $result);
        $this->assertSame('default', $result->value());
    }

    public function testMapOnACollectionShapedSmartNullKeepsCollectionChainsWorking(): void
    {
        // first() on an empty result, then a per-element map: the SmartNull
        // propagates, so collection endings and iteration still degrade gracefully
        $smartNull = $this->smartNullFrom(SmartArrayHtml::class);

        $result = $smartNull->map(fn($value) => strtoupper((string)$value))->implode(', ');
        $this->assertInstanceOf(SmartString::class, $result);
        $this->assertSame('', $result->value());
    }

    public function testMapDelegatesToAnEmptyArrayInRawMode(): void
    {
        // Raw mode has no SmartString delegation: map is SmartArray's per-element
        // map, which returns an empty array of the same mode
        $result = $this->smartNullFrom(SmartArray::class)->map(fn($value) => $value);

        $this->assertSame(SmartArray::class, get_class($result));
        $this->assertSame([], $result->toArray());
    }

    public function testHtmlEncodeReturnsEmptyStringInHtmlMode(): void
    {
        $this->assertSame('', $this->smartNullFrom(SmartArrayHtml::class)->htmlEncode());
    }

    public function testSmartStringTypeCastsReturnEmptyScalarsInHtmlMode(): void
    {
        $smartNull = $this->smartNullFrom(SmartArrayHtml::class);

        $this->assertSame('', $smartNull->string());
        $this->assertSame(0, $smartNull->int());
        $this->assertSame(0.0, $smartNull->float());
        $this->assertFalse($smartNull->bool());
    }

    public function testSmartStringMethodsThrowInRawMode(): void
    {
        // Raw values are plain scalars with no methods, so a miss answers
        // SmartString calls with the same Error as any unknown method
        $smartNull = $this->smartNullFrom(SmartArray::class);

        foreach (['trim', 'or', 'string'] as $method) {
            try {
                $smartNull->$method('n/a');
                $this->fail("expected an Error from ->$method()");
            } catch (Error $e) {
                $this->assertStringStartsWith("Call to undefined method SmartArray->$method(), ", $e->getMessage());
            }
        }
    }

    //endregion
    //region Method delegation: unknown methods

    #[DataProvider('modeProvider')]
    public function testUnknownMethodThrowsErrorWithHelpPointer(string $class): void
    {
        // The message names the delegate class ("SmartArray->bogusMethod()") rather
        // than SmartNull - accurate to where dispatch landed, and an undefined method
        // is a typo to fix regardless of which class reports it.
        $smartNull     = $this->smartNullFrom($class);
        $delegateClass = $class === SmartArrayHtml::class ? 'SmartArrayHtml' : 'SmartArray';

        try {
            $smartNull->bogusMethod();
            $this->fail('expected an Error');
        } catch (Error $e) {
            $this->assertStringStartsWith('Call to undefined method ' . $delegateClass . "->bogusMethod(), see the SmartArray docs for available methods.\n", $e->getMessage());
        }
    }

    #[DataProvider('modeProvider')]
    public function testUnknownMethodSuggestsTheRealMethodName(string $class): void
    {
        $smartNull     = $this->smartNullFrom($class);
        $delegateClass = $class === SmartArrayHtml::class ? 'SmartArrayHtml' : 'SmartArray';

        try {
            $smartNull->head();
            $this->fail('expected an Error');
        } catch (Error $e) {
            $this->assertStringStartsWith('Call to undefined method ' . $delegateClass . "->head(), did you mean ->first()?\n", $e->getMessage());
        }
    }

    public function testUnknownMethodErrorReportsTheCallersFileAndLine(): void
    {
        $smartNull = $this->smartNullFrom(SmartArray::class);

        try {
            $smartNull->bogusMethod();
            $this->fail('expected an Error');
        } catch (Error $e) {
            $line = __LINE__ - 3;
            $this->assertStringContainsString("\nOccurred in " . __FILE__ . ":$line in " . self::class . '->' . __FUNCTION__ . "()\n", $e->getMessage());
        }
    }

    //endregion
    //region Metadata and typed conversion

    #[DataProvider('modeProvider')]
    public function testMysqliPassesThroughTheSourceArraysMetadata(string $class): void
    {
        $smartNull = $this->smartNullFrom($class, ['affected_rows' => 0, 'insert_id' => 7]);

        $this->assertSame(['affected_rows' => 0, 'insert_id' => 7], $smartNull->mysqli());
        $this->assertSame(7, $smartNull->mysqli('insert_id'));
        $this->assertNull($smartNull->mysqli('errno'), 'missing metadata key returns null, no warning');
    }

    #[DataProvider('modeProvider')]
    public function testMysqliIsAnEmptyArrayWhenTheSourceHadNoMetadata(string $class): void
    {
        $this->assertSame([], $this->smartNullFrom($class)->mysqli());
        $this->assertNull($this->smartNullFrom($class)->mysqli('affected_rows'));
    }

    #[DataProvider('modeProvider')]
    public function testAsRawAndAsHtmlReturnEmptyArraysOfTheRequestedClass(string $class): void
    {
        // Fixed return types regardless of the mode the SmartNull came from:
        // this is how `DB::get(...)->first()->asHtml()` normalizes empty results
        $smartNull = $this->smartNullFrom($class);

        $raw  = $smartNull->asRaw();
        $html = $smartNull->asHtml();

        $this->assertSame(SmartArray::class, get_class($raw));
        $this->assertSame(SmartArrayHtml::class, get_class($html));
        $this->assertSame([], $raw->toArray());
        $this->assertSame([], $html->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testAsRawAndAsHtmlPreserveQueryMetadataAndRoot(string $class): void
    {
        $source    = $class::new([], ['mysqli' => ['affected_rows' => 0, 'insert_id' => 7]]);
        $smartNull = $source->first();

        foreach ([$smartNull->asRaw(), $smartNull->asHtml()] as $converted) {
            $this->assertSame(['affected_rows' => 0, 'insert_id' => 7], $converted->mysqli());
            $this->assertSame($source, $converted->root(), 'root reference survives the conversion');
        }
    }

    //endregion
    //region Writes

    #[DataProvider('modeProvider')]
    public function testArrayWriteThrowsRuntimeException(string $class): void
    {
        $smartNull = $this->smartNullFrom($class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot set values on SmartNull - this value came from a missing key or empty result, check ->isNotEmpty() first');

        $smartNull['key'] = 'value';
    }

    #[DataProvider('modeProvider')]
    public function testTwoArgumentSetThrowsTheSameGuardAsArraySyntax(string $class): void
    {
        // Two arguments is SmartArray's set($key, $value), a write, and all
        // writes throw the same guard: property, set(), and array syntax
        $smartNull = $this->smartNullFrom($class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot set values on SmartNull - this value came from a missing key or empty result, check ->isNotEmpty() first');

        $smartNull->set('key', 'value');
    }

    public function testOneArgumentSetProducesTheValueInHtmlMode(): void
    {
        // One argument is SmartString's set($value): not a write, it produces
        // a new value and ends the chain, like or()
        $smartNull = $this->smartNullFrom(SmartArrayHtml::class);

        $result = $smartNull->set('fallback');
        $this->assertInstanceOf(SmartString::class, $result);
        $this->assertSame('fallback', $result->value());

        $this->assertSame($smartNull, $smartNull->set(null), 'set(null) produces nothing, so the chain stays missing');
    }

    public function testOneArgumentSetThrowsInRawMode(): void
    {
        // Raw mode has no SmartString delegation, so the write guard answers
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot set values on SmartNull - this value came from a missing key or empty result, check ->isNotEmpty() first');

        $this->smartNullFrom(SmartArray::class)->set('value');
    }

    #[DataProvider('modeProvider')]
    public function testPropertyWriteThrowsTheSameGuardAsArraySyntax(string $class): void
    {
        // __set throws, so a write can't land as a dynamic property and shadow
        // __get's chainable SmartNull
        $smartNull = $this->smartNullFrom($class);

        try {
            $smartNull->color = 'blue';
            $this->fail('expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame('Cannot set values on SmartNull - this value came from a missing key or empty result, check ->isNotEmpty() first', $e->getMessage());
        }

        $this->assertNull($smartNull->color->value(), 'the name still chains as a SmartNull afterward');
    }

    //endregion
    //region Comparison, casting, and output

    #[DataProvider('modeProvider')]
    public function testIsTruthyInConditionals(string $class): void
    {
        // SmartNull is an object, and every object is truthy. Use ->value() === null
        // or instanceof SmartNull to test for a missing value.
        $smartNull = $this->smartNullFrom($class);

        $this->assertTrue((bool)$smartNull);
        $this->assertSame('truthy', $smartNull ? 'truthy' : 'falsy');
        $this->assertFalse(is_null($smartNull));
        $this->assertFalse(empty($smartNull));
    }

    #[DataProvider('modeProvider')]
    public function testLooseComparisons(string $class): void
    {
        $smartNull = $this->smartNullFrom($class);

        $this->assertTrue($smartNull == '', "PHP compares the object to a string via __toString, which returns ''");
        $this->assertFalse($smartNull == null, 'objects are never loosely equal to null');
        $this->assertFalse($smartNull == false, 'the object casts to true, and true != false');
        $this->assertFalse($smartNull === '');
        $this->assertFalse($smartNull === null);
    }

    #[DataProvider('modeProvider')]
    public function testToStringIsEmptyAndSilent(string $class): void
    {
        // SmartArray warns when interpolated into a string; SmartNull just prints nothing
        $smartNull = $this->smartNullFrom($class);

        [$result, $output] = $this->captureOutput(fn() => "[$smartNull]");

        $this->assertSame('[]', $result);
        $this->assertSame('', $output);
        $this->assertSame('', $smartNull->__toString());
    }

    #[DataProvider('modeProvider')]
    public function testJsonEncodesAsNull(string $class): void
    {
        $smartNull = $this->smartNullFrom($class);

        $this->assertNull($smartNull->jsonSerialize());
        $this->assertSame('null', json_encode($smartNull, JSON_THROW_ON_ERROR));
        $this->assertSame('{"row":null}', json_encode(['row' => $smartNull], JSON_THROW_ON_ERROR));
    }

    #[DataProvider('modeProvider')]
    public function testPrintRShowsOnlyTheNullValue(string $class): void
    {
        $smartNull = $this->smartNullFrom($class);

        $expected = "Itools\\SmartArray\\SmartNull Object\n(\n    [value] => \n)\n";

        $this->assertSame($expected, print_r($smartNull, true), 'internal properties stay hidden, matching SmartString');
    }

    //endregion
}
