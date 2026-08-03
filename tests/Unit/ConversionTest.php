<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\SmartNull;
use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\Support\Fixtures;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * Conversion out of the object graph: asRaw()/asHtml(), toArray(),
 * getRawValue(), and jsonSerialize()/json_encode().
 *
 * The shared rule across all four: values leave as raw PHP types. Converting
 * modes never encodes anything (encoding happens when a SmartString is output),
 * toArray() and json_encode() hand back the stored values in both modes, and
 * getRawValue() unwraps any Smart* object to its raw equivalent.
 */
class ConversionTest extends SmartArrayTestCase
{
    //region Helpers

    /** The class of the opposite mode: SmartArray <-> SmartArrayHtml. */
    private static function otherMode(string $class): string
    {
        return $class === SmartArray::class ? SmartArrayHtml::class : SmartArray::class;
    }

    /** Convert to the opposite mode, whichever direction that is for $sa. */
    private static function convert(SmartArrayBase $sa): SmartArrayBase
    {
        return $sa instanceof SmartArray ? $sa->asHtml() : $sa->asRaw();
    }

    /**
     * @return array<string, array{string|int|float|bool|null}>
     */
    public static function edgeScalarProvider(): array
    {
        $cases = [];
        foreach (Fixtures::edgeScalars() as $label => $value) {
            $cases[$label] = [$value];
        }
        return $cases;
    }

    //endregion
    //region asRaw() / asHtml(): identity and new instances

    public function testAsRawReturnsSameInstanceWhenAlreadyRaw(): void
    {
        $raw = SmartArray::new(Fixtures::records());

        $this->assertSame($raw, $raw->asRaw(), 'already raw: no copy is made');
    }

    public function testAsHtmlReturnsSameInstanceWhenAlreadyHtml(): void
    {
        $html = SmartArrayHtml::new(Fixtures::records());

        $this->assertSame($html, $html->asHtml(), 'already HTML: no copy is made');
    }

    public function testAsHtmlOnRawReturnsNewInstanceAndLeavesSourceRaw(): void
    {
        $raw = SmartArray::new(['name' => 'Bob']);

        $html = $raw->asHtml();

        $this->assertInstanceOf(SmartArrayHtml::class, $html);
        $this->assertNotSame($raw, $html);
        $this->assertInstanceOf(SmartArray::class, $raw, 'source keeps its own mode');
        $this->assertSame('Bob', $raw->name, 'source still returns raw values');
        $this->assertInstanceOf(SmartString::class, $html->name, 'result returns SmartStrings');
    }

    public function testAsRawOnHtmlReturnsNewInstanceAndLeavesSourceHtml(): void
    {
        $html = SmartArrayHtml::new(['name' => 'Bob']);

        $raw = $html->asRaw();

        $this->assertInstanceOf(SmartArray::class, $raw);
        $this->assertNotSame($html, $raw);
        $this->assertInstanceOf(SmartArrayHtml::class, $html, 'source keeps its own mode');
        $this->assertInstanceOf(SmartString::class, $html->name, 'source still returns SmartStrings');
        $this->assertSame('Bob', $raw->name, 'result returns raw values');
    }

    #[DataProvider('modeProvider')]
    public function testConversionPreservesDataExactly(string $class): void
    {
        $sa = $class::new(Fixtures::records());

        $converted = self::convert($sa);

        $this->assertInstanceOf(self::otherMode($class), $converted);
        $this->assertSame(Fixtures::records(), $converted->toArray(), 'values cross unchanged, nothing encoded');
        $this->assertSame(Fixtures::records(), $sa->toArray(), 'source unchanged');
    }

    #[DataProvider('modeProvider')]
    public function testRoundTripConversionReturnsToStartingMode(string $class): void
    {
        $sa = $class::new(Fixtures::records());

        $roundTripped = self::convert(self::convert($sa));

        $this->assertInstanceOf($class, $roundTripped);
        $this->assertNotSame($sa, $roundTripped, 'each direction builds a new object');
        $this->assertSame(Fixtures::records(), $roundTripped->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testConversionRebuildsChildRowsInTheNewMode(string $class): void
    {
        $sa = $class::new([['html' => '<b>x</b>']]);

        $converted  = self::convert($sa);
        $otherClass = self::otherMode($class);

        $this->assertInstanceOf($otherClass, $converted->first(), 'rows take the new mode, not the source mode');
        $this->assertModeValue('<b>x</b>', $converted->first()->html, $otherClass, 'and so do their values');
    }

    #[DataProvider('modeProvider')]
    public function testConversionCopiesRowsSoWritesDoNotAffectSource(string $class): void
    {
        $sa = $class::new([['a' => 1], ['a' => 2]]);

        $converted = self::convert($sa);
        $converted->first()->set('a', 99);
        $converted->set('extra', 'added');

        $this->assertSame([['a' => 1], ['a' => 2]], $sa->toArray(), 'source rows are copies, not shared objects');
        $this->assertSame([['a' => 99], ['a' => 2], 'extra' => 'added'], $converted->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testConversionOfEmptyArrayGivesEmptyArrayOfOtherMode(string $class): void
    {
        $converted = self::convert($class::new([]));

        $this->assertInstanceOf(self::otherMode($class), $converted);
        $this->assertSame([], $converted->toArray());
        $this->assertSame(0, $converted->count());
    }

    #[DataProvider('modeProvider')]
    public function testConversionEmitsNoOutputOrDeprecations(string $class): void
    {
        $sa = $class::new(Fixtures::records());

        [[, $output], $deprecations] = $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => [$sa->asRaw(), $sa->asHtml()]),
        );

        $this->assertSame('', $output);
        $this->assertSame([], $deprecations);
    }

    //endregion
    //region asRaw() / asHtml(): metadata

    #[DataProvider('modeProvider')]
    public function testConversionPreservesMysqliMetadata(string $class): void
    {
        $metadata = ['affected_rows' => 5, 'insert_id' => 42];
        $sa       = $class::new([['id' => 1], ['id' => 2]], ['mysqli' => $metadata]);

        $converted = self::convert($sa);

        $this->assertSame($metadata, $converted->mysqli());
        $this->assertSame(42, $converted->mysqli('insert_id'));
        $this->assertSame($metadata, $converted->first()->mysqli(), 'rows of the converted array carry it too');
    }

    #[DataProvider('modeProvider')]
    public function testConversionPreservesLoadHandler(string $class): void
    {
        // The handler is not readable from outside, so a working load() call is the observable proof
        $handler = fn(SmartArrayBase $row, string $field) => [[['loaded' => $field]], ['insert_id' => 9]];
        $sa      = $class::new(['id' => 1], ['loadHandler' => $handler]);

        $loaded = self::convert($sa)->load('orders');

        $this->assertInstanceOf(self::otherMode($class), $loaded, 'loaded data takes the converted array\'s mode');
        $this->assertSame([['loaded' => 'orders']], $loaded->toArray());
        $this->assertSame(['insert_id' => 9], $loaded->mysqli(), 'handler metadata replaces the source metadata');
    }

    #[DataProvider('modeProvider')]
    public function testConversionKeepsTheSourceAsRootEvenThoughItIsTheOtherMode(string $class): void
    {
        // REVIEW: conversion copies the source's root pointer instead of making
        // the converted array its own root, so root() hands back an object of the
        // mode you just converted away from. $row->root()->column('x') after
        // ->asRaw() still yields SmartStrings. This is also why
        // assertValidStructure() cannot run on a converted nested array: it
        // requires every descendant to share the root's class.
        $sa = $class::new(Fixtures::records());

        $converted = self::convert($sa);

        $this->assertSame($sa, $converted->root(), 'root points at the source object');
        $this->assertInstanceOf($class, $converted->root(), 'so root() is the mode we converted away from');
        $this->assertSame($sa, $converted->first()->root(), 'rows of the converted array point there too');
    }

    #[DataProvider('modeProvider')]
    public function testConversionResetsRowPositionMetadata(string $class): void
    {
        // REVIEW: position metadata is set by the parent during construction and
        // is not among the properties conversion copies, so converting a single
        // row loses its place in the result set. Alternative: carry position,
        // isFirst and isLast through asRaw()/asHtml() like mysqli and root.
        $rows = $class::new([['a' => 1], ['a' => 2]]);
        $row  = $rows->last();

        $this->assertSame(2, $row->position(), 'precondition: the row knows its position');
        $this->assertTrue($row->isLast());

        $converted = self::convert($row);

        $this->assertSame(0, $converted->position());
        $this->assertFalse($converted->isFirst());
        $this->assertFalse($converted->isLast());
    }

    //endregion
    //region toArray()

    #[DataProvider('modeProvider')]
    public function testToArrayReturnsRawPhpValuesInBothModes(string $class): void
    {
        $sa = $class::new([
            'html'  => '<p>"It\'s"</p>',
            'int'   => 0,
            'float' => 1.23,
            'bool'  => false,
            'null'  => null,
            'empty' => '',
        ]);

        $this->assertSame([
            'html'  => '<p>"It\'s"</p>',
            'int'   => 0,
            'float' => 1.23,
            'bool'  => false,
            'null'  => null,
            'empty' => '',
        ], $sa->toArray(), 'no SmartStrings, no encoding, falsy values intact');
    }

    #[DataProvider('modeProvider')]
    public function testToArrayConvertsNestedRowsToPlainArrays(string $class): void
    {
        $sa = $class::new(['level1' => ['level2' => ['level3' => '<deep>']]]);

        $array = $sa->toArray();

        $this->assertSame(['level1' => ['level2' => ['level3' => '<deep>']]], $array);
        $this->assertIsArray($array['level1'], 'child SmartArrays become plain arrays');
        $this->assertIsArray($array['level1']['level2'], 'all the way down');
    }

    #[DataProvider('modeProvider')]
    public function testToArrayPreservesKeyOrderAndKeyTypes(string $class): void
    {
        $sa = $class::new([
            'zebra'    => 1,
            0          => 2,
            'dash-key' => 3,
            ''         => 4,
            '7'        => 5,
            'apple'    => 6,
        ]);

        $array = $sa->toArray();

        $this->assertSame(['zebra', 0, 'dash-key', '', 7, 'apple'], array_keys($array), 'insertion order kept; numeric string keys are int keys');
        $this->assertSame([1, 2, 3, 4, 5, 6], array_values($array));
    }

    #[DataProvider('modeProvider')]
    public function testToArrayOnEmptyReturnsEmptyArray(string $class): void
    {
        $this->assertSame([], $class::new([])->toArray());
    }

    //endregion
    //region getRawValue()

    public function testGetRawValueUnwrapsSmartStringToItsValue(): void
    {
        $this->assertSame('Hello', SmartArrayBase::getRawValue(new SmartString('Hello')));
        $this->assertSame(42, SmartArrayBase::getRawValue(new SmartString(42)));
        $this->assertNull(SmartArrayBase::getRawValue(new SmartString(null)));
    }

    #[DataProvider('modeProvider')]
    public function testGetRawValueIsCallableOnBothModeClasses(string $class): void
    {
        // Callers reach it through the concrete classes: SmartArray::getRawValue(...)
        $this->assertSame('Hello', $class::getRawValue(new SmartString('Hello')));
    }

    #[DataProvider('modeProvider')]
    public function testGetRawValueUnwrapsSmartArrayToPlainNestedArray(string $class): void
    {
        $sa = $class::new(Fixtures::records());

        $this->assertSame(Fixtures::records(), SmartArrayBase::getRawValue($sa), 'nested rows unwrap too');
        $this->assertSame([], SmartArrayBase::getRawValue($class::new([])));
    }

    #[DataProvider('modeProvider')]
    public function testGetRawValueUnwrapsSmartNullToNull(string $class): void
    {
        $smartNull = $class::new([])->first();

        $this->assertSmartNull($smartNull, 'precondition: first() on an empty array is a SmartNull');
        $this->assertNull(SmartArrayBase::getRawValue($smartNull));
        $this->assertNull(SmartArrayBase::getRawValue(new SmartNull()));
    }

    #[DataProvider('edgeScalarProvider')]
    public function testGetRawValuePassesScalarsAndNullThrough(string|int|float|bool|null $value): void
    {
        $this->assertSame($value, SmartArrayBase::getRawValue($value));
    }

    public function testGetRawValueUnwrapsSmartValuesInsidePlainArraysRecursively(): void
    {
        $input = [
            'plain'  => 'text',
            'string' => new SmartString('smart'),
            'row'    => SmartArrayHtml::new(['id' => 1, 'tags' => ['a', 'b']]),
            'nested' => ['deep' => new SmartString('deeper'), 'null' => new SmartNull()],
        ];

        $this->assertSame([
            'plain'  => 'text',
            'string' => 'smart',
            'row'    => ['id' => 1, 'tags' => ['a', 'b']],
            'nested' => ['deep' => 'deeper', 'null' => null],
        ], SmartArrayBase::getRawValue($input));
    }

    public function testGetRawValueThrowsOnUnsupportedType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value type: stdClass');

        SmartArrayBase::getRawValue(new stdClass());
    }

    //endregion
    //region jsonSerialize() and json_encode()

    #[DataProvider('modeProvider')]
    public function testJsonEncodeReturnsUnencodedValuesInBothModes(string $class): void
    {
        // JSON is a data format: SmartArrayHtml's encoding applies to HTML output, not here
        $sa = $class::new(['html' => '<p>"It\'s"</p>', 'int' => 0, 'bool' => false, 'null' => null]);

        $this->assertSame('{"html":"<p>\"It\'s\"<\/p>","int":0,"bool":false,"null":null}', json_encode($sa));
    }

    #[DataProvider('modeProvider')]
    public function testJsonEncodeDescendsIntoNestedRows(string $class): void
    {
        $sa = $class::new(['rows' => [['a' => 1], ['a' => 2]], 'total' => 2]);

        $this->assertSame('{"rows":[{"a":1},{"a":2}],"total":2}', json_encode($sa));
    }

    #[DataProvider('modeProvider')]
    public function testJsonEncodeOnEmptyArrayReturnsEmptyJsonArray(string $class): void
    {
        $this->assertSame('[]', json_encode($class::new([])));
    }

    #[DataProvider('modeProvider')]
    public function testJsonSerializeReturnsChildRowsAsObjects(string $class): void
    {
        // jsonSerialize() hands back the internal data: scalars raw, rows still
        // SmartArrays that json_encode() serializes by calling their own jsonSerialize()
        $sa = $class::new(['rows' => [['a' => 1]], 'total' => 2]);

        $data = $sa->jsonSerialize();

        $this->assertSame(['rows', 'total'], array_keys($data));
        $this->assertInstanceOf($class, $data['rows']);
        $this->assertSame(2, $data['total']);
    }

    #[DataProvider('modeProvider')]
    public function testJsonEncodeSubstitutesMalformedUtf8(string $class): void
    {
        // One corrupt byte used to make json_encode() return false for the whole page
        $flat = $class::new(['name' => "caf\xE9", 'id' => 7]);
        $this->assertSame('{"name":"caf\\ufffd","id":7}', json_encode($flat));

        $nested = $class::new(['rows' => [['title' => "caf\xE9"]]]);
        $this->assertSame('{"rows":[{"title":"caf\\ufffd"}]}', json_encode($nested), 'rows scrub themselves as json_encode descends');
    }

    #[DataProvider('modeProvider')]
    public function testJsonSerializeReplacesMalformedUtf8WithReplacementCharacter(string $class): void
    {
        $sa = $class::new(['name' => "caf\xE9", 'ok' => "caf\u{00E9}"]);

        $data = $sa->jsonSerialize();

        $this->assertSame("caf\u{FFFD}", $data['name'], 'the invalid byte becomes U+FFFD');
        $this->assertSame("caf\u{00E9}", $data['ok'], 'valid UTF-8 is left alone');
        $this->assertSame("caf\xE9", $sa->toArray()['name'], 'the stored value is not modified');
    }

    #[DataProvider('modeProvider')]
    public function testJsonEncodeFailsOnMalformedUtf8Key(string $class): void
    {
        // REVIEW: the substitution covers values only, so one corrupt byte in a
        // KEY still returns false and loses the whole document - the failure the
        // value scrubbing exists to prevent. Keys come from column names, so this
        // is rare, but jsonSerialize() could scrub them the same way.
        $sa = $class::new(["caf\xE9" => 'value']);

        $this->assertFalse(json_encode($sa));
        $this->assertSame('Malformed UTF-8 characters, possibly incorrectly encoded', json_last_error_msg());
    }

    //endregion
}
