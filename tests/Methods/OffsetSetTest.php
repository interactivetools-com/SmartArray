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
 * Tests for array index assignment: $arr['key'] = value (deprecated)
 *
 * Array syntax is deprecated - use $arr->key = value instead.
 * Array syntax routes through offsetSet() which triggers deprecation warnings
 * which always triggers deprecation warnings.
 *
 * @see PropertySetTest for preferred property syntax ($arr->key = value)
 *
 * @noinspection PhpArrayUsedOnlyForWriteInspection
 * @noinspection OnlyWritesOnParameterInspection
 * @noinspection PhpArrayWriteIsNotUsedInspection
 */
class OffsetSetTest extends SmartArrayTestCase
{

    /**
     * @dataProvider offsetSetProvider
     */
    public function testOffsetSet(array $initialData, callable $set, array $expected): void
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

    public static function offsetSetProvider(): array
    {
        return [
            'scalar value' => [
                'initialData' => [],
                'set'         => fn($array) => $array['name'] = 'Alice',
                'expected'    => ['name' => 'Alice'],
            ],
            'array value' => [
                'initialData' => [],
                'set'         => fn($array) => $array['address'] = ['city' => 'New York', 'zip' => '10001'],
                'expected'    => ['address' => ['city' => 'New York', 'zip' => '10001']],
            ],
            'append with null key' => [
                'initialData' => [],
                'set'         => fn($array) => $array[] = 'appended value',
                'expected'    => ['appended value'],
            ],
            'append array with null key' => [
                'initialData' => [],
                'set'         => fn($array) => $array[] = ['item1', 'item2'],
                'expected'    => [['item1', 'item2']],
            ],
            'overwrite existing key' => [
                'initialData' => ['key1' => 'initial'],
                'set'         => fn($array) => $array['key1'] = 'overwritten',
                'expected'    => ['key1' => 'overwritten'],
            ],
            'integer key' => [
                'initialData' => [],
                'set'         => fn($array) => $array[42] = 'answer',
                'expected'    => [42 => 'answer'],
            ],
            'numeric string key' => [
                'initialData' => [],
                'set'         => fn($array) => $array['123'] = 'numeric string key',
                'expected'    => ['123' => 'numeric string key'],
            ],
            'special characters in key' => [
                'initialData' => [],
                'set'         => fn($array) => $array['key-with-dash'] = 'special key',
                'expected'    => ['key-with-dash' => 'special key'],
            ],
            'empty string key' => [
                'initialData' => [],
                'set'         => fn($array) => $array[''] = 'empty key',
                'expected'    => ['' => 'empty key'],
            ],
            'overwrite with different type' => [
                'initialData' => ['data' => ['initial' => 'array']],
                'set'         => fn($array) => $array['data'] = 'now a string',
                'expected'    => ['data' => 'now a string'],
            ],
            'float value' => [
                'initialData' => [],
                'set'         => fn($array) => $array['pi'] = 3.14,
                'expected'    => ['pi' => 3.14],
            ],
            'boolean value' => [
                'initialData' => [],
                'set'         => fn($array) => $array['isActive'] = true,
                'expected'    => ['isActive' => true],
            ],
            'null value' => [
                'initialData' => [],
                'set'         => fn($array) => $array['nothing'] = null,
                'expected'    => ['nothing' => null],
            ],
        ];
    }

    /**
     * @dataProvider unsupportedTypesProvider
     */
    public function testOffsetSetThrowsOnUnsupportedTypes(mixed $value): void
    {
        $array = new SmartArray([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("doesn't support " . get_debug_type($value));

        $array['key'] = $value;
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
