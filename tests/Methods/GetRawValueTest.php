<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartNull;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartString\SmartString;
use stdClass;

/**
 * Tests for SmartArray::getRawValue() static method.
 *
 * getRawValue($value) extracts the raw/underlying value from SmartString,
 * SmartArray, or SmartNull objects. Scalar values and nulls pass through unchanged.
 */
class GetRawValueTest extends SmartArrayTestCase
{

    //region SmartString conversion

    public function testGetRawValueExtractsFromSmartString(): void
    {
        $smartString = new SmartString('Hello World');

        $result = SmartArray::getRawValue($smartString);

        $this->assertSame('Hello World', $result);
    }

    public function testGetRawValueExtractsNumericFromSmartString(): void
    {
        $smartString = new SmartString(42);

        $result = SmartArray::getRawValue($smartString);

        $this->assertSame(42, $result);
    }

    public function testGetRawValueExtractsNullFromSmartString(): void
    {
        $smartString = new SmartString(null);

        $result = SmartArray::getRawValue($smartString);

        $this->assertNull($result);
    }

    //endregion
    //region SmartArray conversion

    public function testGetRawValueExtractsArrayFromSmartArray(): void
    {
        $smartArray = new SmartArray(['name' => 'John', 'age' => 30]);

        $result = SmartArray::getRawValue($smartArray);

        $this->assertSame(['name' => 'John', 'age' => 30], $result);
    }

    public function testGetRawValueExtractsNestedArrayFromSmartArray(): void
    {
        $smartArray = new SmartArray([
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
        ]);

        $result = SmartArray::getRawValue($smartArray);

        $this->assertSame([
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
        ], $result);
    }

    public function testGetRawValueExtractsEmptyArrayFromSmartArray(): void
    {
        $smartArray = new SmartArray([]);

        $result = SmartArray::getRawValue($smartArray);

        $this->assertSame([], $result);
    }

    //endregion
    //region SmartNull conversion

    public function testGetRawValueReturnsNullFromSmartNull(): void
    {
        $smartNull = new SmartNull();

        $result = SmartArray::getRawValue($smartNull);

        $this->assertNull($result);
    }

    //endregion
    //region Scalar passthrough

    public function testGetRawValuePassesThroughString(): void
    {
        $result = SmartArray::getRawValue('hello');

        $this->assertSame('hello', $result);
    }

    public function testGetRawValuePassesThroughInteger(): void
    {
        $result = SmartArray::getRawValue(42);

        $this->assertSame(42, $result);
    }

    public function testGetRawValuePassesThroughFloat(): void
    {
        $result = SmartArray::getRawValue(3.14);

        $this->assertSame(3.14, $result);
    }

    public function testGetRawValuePassesThroughBoolean(): void
    {
        $this->assertTrue(SmartArray::getRawValue(true));
        $this->assertFalse(SmartArray::getRawValue(false));
    }

    public function testGetRawValuePassesThroughNull(): void
    {
        $result = SmartArray::getRawValue(null);

        $this->assertNull($result);
    }

    //endregion
    //region Array conversion

    public function testGetRawValueConvertsArrayRecursively(): void
    {
        $smartString = new SmartString('nested value');
        $array = [
            'plain'  => 'text',
            'smart'  => $smartString,
            'number' => 42,
        ];

        $result = SmartArray::getRawValue($array);

        $this->assertSame([
            'plain'  => 'text',
            'smart'  => 'nested value',
            'number' => 42,
        ], $result);
    }

    public function testGetRawValueConvertsNestedArrayRecursively(): void
    {
        $array = [
            'level1' => [
                'smart' => new SmartString('deep value'),
            ],
        ];

        $result = SmartArray::getRawValue($array);

        $this->assertSame([
            'level1' => [
                'smart' => 'deep value',
            ],
        ], $result);
    }

    //endregion
    //region Error handling

    public function testGetRawValueThrowsForUnsupportedType(): void
    {
        $object = new stdClass();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value type: stdClass');

        SmartArray::getRawValue($object);
    }

    public function testGetRawValueThrowsForResource(): void
    {
        $resource = fopen('php://memory', 'r');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value type: resource');

        try {
            SmartArray::getRawValue($resource);
        } finally {
            fclose($resource);
        }
    }

    public function testGetRawValueThrowsForClosure(): void
    {
        $closure = fn() => 'test';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value type: Closure');

        SmartArray::getRawValue($closure);
    }

    //endregion

}
