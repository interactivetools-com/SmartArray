<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartArray\Tests\TestHelpers;
use stdClass;

/**
 * Tests for property assignment syntax: $arr->key = value
 *
 * Note: SmartArray extends ArrayObject with ARRAY_AS_PROPS flag, so both
 * ['key'] and ->key currently route through offsetSet(). These tests are
 * kept separate from OffsetSetTest for future refactoring flexibility.
 *
 * @see OffsetSetTest for array index syntax ($arr['key'] = value)
 *
 * @noinspection PhpArrayUsedOnlyForWriteInspection
 * @noinspection OnlyWritesOnParameterInspection
 * @noinspection PhpArrayWriteIsNotUsedInspection
 */
class PropertySetTest extends SmartArrayTestCase
{

    /**
     * @dataProvider propertySetProvider
     */
    public function testPropertySet(array $initialData, callable $set, array $expected): void
    {
        // Without SmartStrings
        $array = new SmartArray($initialData);
        $set($array);
        $actual = $array->toArray();
        $this->assertEquals($expected, $actual);

        // With SmartStrings
        $array = new SmartArrayHtml($initialData);
        $set($array);
        $actual = TestHelpers::toArrayResolveSS($array);
        $this->assertEquals($expected, $actual);
    }

    public static function propertySetProvider(): array
    {
        return [
            'scalar value' => [
                'initialData' => [],
                'set'         => fn($array) => $array->name = 'Alice',
                'expected'    => ['name' => 'Alice'],
            ],
            'integer value' => [
                'initialData' => [],
                'set'         => fn($array) => $array->age = 30,
                'expected'    => ['age' => 30],
            ],
            'array value' => [
                'initialData' => [],
                'set'         => fn($array) => $array->contact = ['email' => 'alice@example.com', 'phone' => '123-456-7890'],
                'expected'    => ['contact' => ['email' => 'alice@example.com', 'phone' => '123-456-7890']],
            ],
            'float value' => [
                'initialData' => [],
                'set'         => fn($array) => $array->pi = 3.14,
                'expected'    => ['pi' => 3.14],
            ],
            'boolean value' => [
                'initialData' => [],
                'set'         => fn($array) => $array->isActive = true,
                'expected'    => ['isActive' => true],
            ],
            'null value' => [
                'initialData' => [],
                'set'         => fn($array) => $array->nothing = null,
                'expected'    => ['nothing' => null],
            ],
            'overwrite existing key' => [
                'initialData' => ['key1' => 'initial'],
                'set'         => fn($array) => $array->key1 = 'overwritten',
                'expected'    => ['key1' => 'overwritten'],
            ],
            'overwrite with different type' => [
                'initialData' => ['data' => ['initial' => 'array']],
                'set'         => fn($array) => $array->data = 'now a string',
                'expected'    => ['data' => 'now a string'],
            ],
            'underscore in key' => [
                'initialData' => [],
                'set'         => fn($array) => $array->user_name = 'bob',
                'expected'    => ['user_name' => 'bob'],
            ],
            'nested array value' => [
                'initialData' => [],
                'set'         => fn($array) => $array->nested = ['a' => 1, 'b' => 2],
                'expected'    => ['nested' => ['a' => 1, 'b' => 2]],
            ],
        ];
    }

    /**
     * @dataProvider unsupportedTypesProvider
     */
    public function testPropertySetThrowsOnUnsupportedTypes(mixed $value): void
    {
        $array = new SmartArray([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("doesn't support " . get_debug_type($value));

        $array->key = $value;
    }

    public static function unsupportedTypesProvider(): array
    {
        return [
            'stdClass'          => [new stdClass()],
            'DateTime'          => [new \DateTime()],
            'Closure'           => [fn() => 'test'],
            'resource (stream)' => [fopen('php://memory', 'r')],
        ];
    }

}
