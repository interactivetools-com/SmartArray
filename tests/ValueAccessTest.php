<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use PHPUnit\Framework\TestCase;
use Itools\SmartArray\SmartArray;
use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\TestHelpers;

class ValueAccessTest extends TestCase
{
//region Value Access

    public function testGetDefaults(): void
    {
        $expected = "Bob";
        $default  = "Unknown Name";

        // Test defaults are ignored when key exists
        $smartArrays = [
            SmartArray::new(['name' => $expected, 'city' => 'Springfield']),
            SmartArray::newSS(['name' => $expected, 'city' => 'Springfield']),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value; // convert SmartString to string
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertSame($expected, $actual);
        }

        // Test defaults are ignored when key exists but is null
        $smartArrays = [
            SmartArray::new(['name' => null, 'city' => 'Springfield']),
            SmartArray::newSS(['name' => null, 'city' => 'Springfield']),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value; // convert SmartString to string
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertNull($actual);
        }

        // Test defaults are used when key doesn't exist
        $smartArrays = [
            SmartArray::new(['name2' => $expected, 'city' => 'Springfield']),
            SmartArray::newSS(['name2' => $expected, 'city' => 'Springfield']),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value; // convert SmartString to string
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertSame($default, $actual);
        }

        // Test defaults are used when array is empty
        $smartArrays = [
            SmartArray::new(),
            SmartArray::newSS(),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value; // convert SmartString to string
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertSame($default, $actual);
        }

    }

    /**
     * @dataProvider getProvider
     */
    public function testGet($initialData, $key, $expected): void
    {
        // Set up the initial data
        $array   = SmartArray::new($initialData);
        $arraySS = SmartArray::newSS($initialData);

        // Perform the get operation
        ob_start(); // capture any warnings output by SmartArray::get()
        $result   = $array->get($key);
        $resultSS = $arraySS->get($key);
        ob_end_clean(); // discard any warnings output by SmartArray::get()

        // Compare Raw
        $this->assertSame(
            expected: $expected,
            actual  : TestHelpers::normalizeRaw($result),
        );

        // Compare SS
        $this->assertSame(
            expected: TestHelpers::recursiveHtmlEncode($expected),
            actual  : TestHelpers::normalizeSS($resultSS)
        );
    }

    public function getProvider(): array
    {
        return [
            'Get existing key'                                => [
                'initialData' => ['name' => 'Alice', 'age' => 30],
                'key'         => 'name',
                'expected'    => 'Alice',
            ],
            'Get non-existing key'                            => [
                'initialData' => ['name' => 'Alice'],
                'key'         => 'email',
                'expected'    => null,
            ],
            'Get existing key with value null'                => [
                'initialData' => ['name' => 'Alice', 'nickname' => null],
                'key'         => 'nickname',
                'expected'    => null,
            ],
            'Get nested array value'                          => [
                'initialData' => ['user' => ['id' => 1, 'name' => 'Alice']],
                'key'         => 'user',
                'expected'    => ['id' => 1, 'name' => 'Alice'],
            ],
            'Get non-existing key from empty array'           => [
                'initialData' => [],
                'key'         => 'key',
                'expected'    => null,
            ],
            'Get existing key with boolean false'             => [
                'initialData' => ['isActive' => false],
                'key'         => 'isActive',
                'expected'    => false,
            ],
            'Get existing key with value zero'                => [
                'initialData' => ['count' => 0],
                'key'         => 'count',
                'expected'    => 0,
            ],
            'Get existing key with empty string'              => [
                'initialData' => ['title' => ''],
                'key'         => 'title',
                'expected'    => '',
            ],
            'Get existing key with integer key'               => [
                'initialData' => [0 => 'zero', 1 => 'one'],
                'key'         => 1,
                'expected'    => 'one',
            ],
            'Get non-existing key with integer key'           => [
                'initialData' => [0 => 'zero', 1 => 'one'],
                'key'         => 2,
                'expected'    => null,
            ],
            'Get existing key with numeric string key'        => [
                'initialData' => ['123' => 'numeric key'],
                'key'         => '123',
                'expected'    => 'numeric key',
            ],
            'Get existing key with special characters in key' => [
                'initialData' => ['key-with-dash' => 'special key'],
                'key'         => 'key-with-dash',
                'expected'    => 'special key',
            ],
            'Get existing key with empty string key'          => [
                'initialData' => ['' => 'empty key'],
                'key'         => '',
                'expected'    => 'empty key',
            ],
            'Get existing key where value is array'           => [
                'initialData' => ['data' => ['item1', 'item2']],
                'key'         => 'data',
                'expected'    => ['item1', 'item2'],
            ],
        ];
    }

    /**
     * @dataProvider firstProvider
     */
    public function testFirst($initialData, $expected): void
    {
        // Set up the initial data
        $array   = SmartArray::new($initialData);
        $arraySS = SmartArray::newSS($initialData);

        // Compare Raw
        $result   = $array->first();
        $this->assertSame(
            expected: $expected,
            actual  : TestHelpers::normalizeRaw($result),
        );

        // Compare SS
        $resultSS = $arraySS->first();
        $this->assertSame(
            expected: TestHelpers::recursiveHtmlEncode($expected),
            actual  : TestHelpers::normalizeSS($resultSS)
        );
    }

    public function firstProvider(): array
    {
        return [
            'Get first element from flat array'                     => [
                'initialData' => ['first', 'second', 'third'],
                'expected'    => 'first',
            ],
            'Get first element from associative array'              => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'expected'    => 'alpha',
            ],
            'Get first element from single-element array'           => [
                'initialData' => ['only'],
                'expected'    => 'only',
            ],
            'Get first element from empty array'                    => [
                'initialData' => [],
                'expected'    => null,
            ],
            'Get first element from nested array'                   => [
                'initialData' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'expected'    => ['id' => 1, 'name' => 'Alice'],
            ],
            'Get first element from array with mixed keys'          => [
                'initialData' => [10 => 'ten', 'twenty' => 'twenty', 30 => 'thirty'],
                'expected'    => 'ten',
            ],
            'Get first element from multi-dimensional array'        => [
                'initialData' => [
                    'numbers' => [1, 2, 3],
                    'letters' => ['a', 'b', 'c'],
                    'symbols' => ['!', '@', '#'],
                ],
                'expected'    => [1, 2, 3],
            ],
            'Get first element when first element is null'          => [
                'initialData' => [null, 'second', 'third'],
                'expected'    => null,
            ],
            'Get first element when array contains only null'       => [
                'initialData' => [null],
                'expected'    => null,
            ],
            'Get first element from array with duplicate values'    => [
                'initialData' => ['first value', 'value', 'value'],
                'expected'    => 'first value',
            ],
            'Get first element from array with numeric string keys' => [
                'initialData' => ['0' => 'zero', '1' => 'one', '2' => 'two'],
                'expected'    => 'zero',
            ],
            'Get first element from array with boolean values'      => [
                'initialData' => [true, false, true],
                'expected'    => true,
            ],
        ];
    }

    /**
     * @dataProvider lastProvider
     */
    public function testLast($initialData, $expected): void
    {
        // Set up the initial data
        $array   = SmartArray::new($initialData);
        $arraySS = SmartArray::newSS($initialData);

        // Compare Raw
        $result   = $array->last();
        $this->assertSame(
            expected: $expected,
            actual  : TestHelpers::normalizeRaw($result),
        );

        // Compare SS
        $resultSS = $arraySS->last();
        $this->assertSame(
            expected: TestHelpers::recursiveHtmlEncode($expected),
            actual  : TestHelpers::normalizeSS($resultSS)
        );
    }

    public function lastProvider(): array
    {
        return [
            'Get last element from flat array'                     => [
                'initialData' => ['first', 'second', 'third'],
                'expected'    => 'third',
            ],
            'Get last element from associative array'              => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'expected'    => 'gamma',
            ],
            'Get last element from single-element array'           => [
                'initialData' => ['only'],
                'expected'    => 'only',
            ],
            'Get last element from empty array'                    => [
                'initialData' => [],
                'expected'    => null,
            ],
            'Get last element from nested array'                   => [
                'initialData' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'expected'    => ['id' => 3, 'name' => 'Charlie'],
            ],
            'Get last element from array with mixed keys'          => [
                'initialData' => [10 => 'ten', 'twenty' => 'twenty', 30 => 'thirty'],
                'expected'    => 'thirty',
            ],
            'Get last element from multi-dimensional array'        => [
                'initialData' => [
                    'numbers' => [1, 2, 3],
                    'letters' => ['a', 'b', 'c'],
                    'symbols' => ['!', '@', '#'],
                ],
                'expected'    => ['!', '@', '#'],
            ],
            'Get last element when last element is null'           => [
                'initialData' => ['first', 'second', null],
                'expected'    => null,
            ],
            'Get last element when array contains only null'       => [
                'initialData' => [null],
                'expected'    => null,
            ],
            'Get last element from array with duplicate values'    => [
                'initialData' => ['value', 'value', 'last value'],
                'expected'    => 'last value',
            ],
            'Get last element from array with numeric string keys' => [
                'initialData' => ['0' => 'zero', '1' => 'one', '2' => 'two'],
                'expected'    => 'two',
            ],
            'Get last element from array with boolean values'      => [
                'initialData' => [true, false, true],
                'expected'    => true,
            ],
        ];
    }

    /**
     * @dataProvider nthProvider
     */
    public function testNth($initialData, $index, $expected): void
    {
        // Set up the initial data
        $array   = SmartArray::new($initialData);
        $arraySS = SmartArray::newSS($initialData);

        // Compare Raw
        $result   = $array->nth($index);
        $this->assertSame(
            expected: $expected,
            actual  : TestHelpers::normalizeRaw($result),
        );

        // Compare SS
        $resultSS = $arraySS->nth($index);
        $this->assertSame(
            expected: TestHelpers::recursiveHtmlEncode($expected),
            actual  : TestHelpers::normalizeSS($resultSS)
        );
    }

    public function nthProvider(): array
    {
        return [
            'Get first element (index 0) from flat array'                    => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => 0,
                'expected'    => 'first',
            ],
            'Get second element (index 1) from flat array'                   => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => 1,
                'expected'    => 'second',
            ],
            'Get last element using negative index -1'                       => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => -1,
                'expected'    => 'third',
            ],
            'Get second-to-last element using negative index -2'             => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => -2,
                'expected'    => 'second',
            ],
            'Get element with index out of bounds (positive index)'          => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => 5,
                'expected'    => null,
            ],
            'Get element with index out of bounds (negative index)'          => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => -5,
                'expected'    => null,
            ],
            'Get nth element from associative array'                         => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'index'       => 1,
                'expected'    => 'beta',
            ],
            'Get first element from associative array'                       => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'index'       => 0,
                'expected'    => 'alpha',
            ],
            'Get last element from associative array using negative index'   => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'index'       => -1,
                'expected'    => 'gamma',
            ],
            'Get nth element from nested array'                              => [
                'initialData' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'index'       => 2,
                'expected'    => ['id' => 3, 'name' => 'Charlie'],
            ],
            'Get element from empty array'                                   => [
                'initialData' => [],
                'index'       => 0,
                'expected'    => null,
            ],
            'Get element with index 0 from single-element array'             => [
                'initialData' => ['only'],
                'index'       => 0,
                'expected'    => 'only',
            ],
            'Get element with negative index -1 from single-element array'   => [
                'initialData' => ['only'],
                'index'       => -1,
                'expected'    => 'only',
            ],
            'Get element with index out of bounds from single-element array' => [
                'initialData' => ['only'],
                'index'       => 1,
                'expected'    => null,
            ],
            'Get nth element from mixed keys array'                          => [
                'initialData' => [10 => 'ten', 'twenty' => 'twenty', 30 => 'thirty'],
                'index'       => 1,
                'expected'    => 'twenty',
            ],
            'Get element from multi-dimensional array'                       => [
                'initialData' => [
                    'numbers' => [1, 2, 3],
                    'letters' => ['a', 'b', 'c'],
                    'symbols' => ['!', '@', '#'],
                ],
                'index'       => 1,
                'expected'    => ['a', 'b', 'c'],
            ],
            'Get element with out of order indexed array'                    => [
                'initialData' => [2 => 'first', 4 => 'second', 6 => 'third'],
                'index'       => 1,
                'expected'    => 'second',
            ],
            'Get element with index zero'                                    => [
                'initialData' => ['zero', 'one', 'two'],
                'index'       => 0,
                'expected'    => 'zero',
            ],
        ];
    }

    /**
     * @dataProvider offsetGetProvider
     */
    public function testOffsetGet($initialData, $get, $expected): void
    {
        // Set up the initial data
        $array   = SmartArray::new($initialData);
        $arraySS = SmartArray::newSS($initialData);

        // Perform the get operation using the provided closure
        ob_start(); // capture any warnings output by SmartArray::offsetGet()
        $result   = $get($array);
        $resultSS = $get($arraySS);
        ob_end_clean(); // discard any warnings output by SmartArray::offsetGet()

        // Compare Raw
        $this->assertSame(
            expected: $expected,
            actual  : TestHelpers::normalizeRaw($result),
        );

        // Compare SS
        $this->assertSame(
            expected: TestHelpers::recursiveHtmlEncode($expected),
            actual  : TestHelpers::normalizeSS($resultSS)
        );
    }

    public function offsetGetProvider(): array
    {
        return [
            'Get scalar value with array syntax'                     => [
                'initialData' => ['name' => 'Alice'],
                'get'         => fn($array) => $array['name'],
                'expected'    => 'Alice',
            ],
            'Get scalar value with property syntax'                  => [
                'initialData' => ['age' => 30],
                'get'         => fn($array) => $array->age,
                'expected'    => 30,
            ],
            'Get array value with array syntax'                      => [
                'initialData' => ['address' => ['city' => 'New York', 'zip' => '10001']],
                'get'         => fn($array) => $array['address'],
                'expected'    => ['city' => 'New York', 'zip' => '10001'],
            ],
            'Get array value with property syntax'                   => [
                'initialData' => ['contact' => ['email' => 'alice@example.com', 'phone' => '123-456-7890']],
                'get'         => fn($array) => $array->contact,
                'expected'    => ['email' => 'alice@example.com', 'phone' => '123-456-7890'],
            ],
            'Get value with integer key'                             => [
                'initialData' => [42 => 'answer'],
                'get'         => fn($array) => $array[42],
                'expected'    => 'answer',
            ],
            'Get value using numeric string key'                     => [
                'initialData' => ['123' => 'numeric string key'],
                'get'         => fn($array) => $array['123'],
                'expected'    => 'numeric string key',
            ],
            'Get value using special characters in key'              => [
                'initialData' => ['key-with-dash' => 'special key'],
                'get'         => fn($array) => $array['key-with-dash'],
                'expected'    => 'special key',
            ],
            'Get value with empty string key'                        => [
                'initialData' => ['' => 'empty key'],
                'get'         => fn($array) => $array[''],
                'expected'    => 'empty key',
            ],
            'Get nested SmartArray value'                            => [
                'initialData' => ['nested' => ['a' => 1, 'b' => 2]],
                'get'         => fn($array) => $array->nested,
                'expected'    => ['a' => 1, 'b' => 2],
            ],
            'Get value from nested SmartArray using array syntax'    => [
                'initialData' => ['nested' => ['key' => 'value']],
                'get'         => fn($array) => $array['nested']['key'],
                'expected'    => 'value',
            ],
            'Get value from nested SmartArray using property syntax' => [
                'initialData' => ['nested' => ['key' => 'value']],
                'get'         => fn($array) => $array->nested->key,
                'expected'    => 'value',
            ],
            'Get first element using numeric index'                  => [
                'initialData' => ['first', 'second', 'third'],
                'get'         => fn($array) => $array[0],
                'expected'    => 'first',
            ],
            'Get last element using calculated index'                => [
                'initialData' => ['first', 'second', 'third'],
                'get'         => fn($array) => $array[count($array) - 1],
                'expected'    => 'third',
            ],
            'Get non-existent key'                                   => [
                'initialData' => ['name' => 'Alice'],
                'get'         => fn($array) => $array->age,
                'expected'    => null,
            ],
            'Get null value'                                         => [
                'initialData' => ['nothing' => null],
                'get'         => fn($array) => $array->nothing,
                'expected'    => null,
            ],
            'Get boolean value'                                      => [
                'initialData' => ['isActive' => true],
                'get'         => fn($array) => $array->isActive,
                'expected'    => true,
            ],
            'Get float value'                                        => [
                'initialData' => ['pi' => 3.14],
                'get'         => fn($array) => $array->pi,
                'expected'    => 3.14,
            ],
        ];
    }

    /**
     * @dataProvider offsetSetProvider
     */
    public function testOffsetSet($initialData, $set, $expected): void
    {
        // Without SmartStrings
        $array = new SmartArray($initialData);
        $set($array); // Perform the operation using the provided closure
        $actual = $array->toArray();
        $this->assertEquals($expected, $actual);

        // With SmartStrings
        $array = SmartArray::newSS($initialData);
        $set($array); // Perform the operation using the provided closure
        $actual = TestHelpers::toArrayResolveSS($array);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @noinspection PhpArrayUsedOnlyForWriteInspection
     * @noinspection OnlyWritesOnParameterInspection
     * @noinspection PhpArrayWriteIsNotUsedInspection
     */
    public function offsetSetProvider(): array
    {
        return [
            'Set scalar value with array syntax'        => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array['name'] = 'Alice';
                },
                'expected'    => [
                    'name' => 'Alice',
                ],
            ],
            'Set scalar value with property syntax'     => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array->age = 30;
                },
                'expected'    => [
                    'age' => 30,
                ],
            ],
            'Set array value with array syntax'         => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array['address'] = ['city' => 'New York', 'zip' => '10001'];
                },
                'expected'    => [
                    'address' => ['city' => 'New York', 'zip' => '10001'],
                ],
            ],
            'Set array value with property syntax'      => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array->contact = ['email' => 'alice@example.com', 'phone' => '123-456-7890'];
                },
                'expected'    => [
                    'contact' => ['email' => 'alice@example.com', 'phone' => '123-456-7890'],
                ],
            ],
            'Set null key (append) with scalar value'   => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array[] = 'appended value';
                },
                'expected'    => [
                    'appended value',
                ],
            ],
            'Set null key (append) with array value'    => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array[] = ['item1', 'item2'];
                },
                'expected'    => [
                    ['item1', 'item2'],
                ],
            ],
            'Overwrite existing key'                    => [
                'initialData' => ['key1' => 'initial'],
                'set'         => fn($array) => $array['key1'] = 'overwritten',
                'expected'    => [
                    'key1' => 'overwritten',
                ],
            ],
            'Set integer key'                           => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array[42] = 'answer';
                },
                'expected'    => [
                    42 => 'answer',
                ],
            ],
            'Set float value'                           => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array->pi = 3.14;
                },
                'expected'    => [
                    'pi' => 3.14,
                ],
            ],
            'Set boolean value'                         => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array->isActive = true;
                },
                'expected'    => [
                    'isActive' => true,
                ],
            ],
            'Set null value'                            => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array->nothing = null;
                },
                'expected'    => [
                    'nothing' => null,
                ],
            ],
            'Set value using numeric string key'        => [
                'initialData' => [],
                'set'         => fn($array) => $array['123'] = 'numeric string key',
                'expected'    => [
                    '123' => 'numeric string key',
                ],
            ],
            'Set value using special characters in key' => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array['key-with-dash'] = 'special key';
                },
                'expected'    => [
                    'key-with-dash' => 'special key',
                ],
            ],
            'Set value with empty string key'           => [
                'initialData' => [],
                'set'         => fn($array) => $array[''] = 'empty key',
                'expected'    => [
                    '' => 'empty key',
                ],
            ],
            'Set value with overwriting different type' => [
                'initialData' => ['data' => ['initial' => 'array']],
                'set'         => function ($array) {
                    $array->data = 'now a string';
                },
                'expected'    => [
                    'data' => 'now a string',
                ],
            ],
        ];
    }

    /**
     * @dataProvider mergeProvider
     */
    public function testMerge($input, $arrays, $expected): void
    {
        // Test SmartArray without SmartStrings
        $smartArray = new SmartArray($input);
        $result = $smartArray->merge(...$arrays);
        $this->assertEquals($expected, $result->toArray());

        // Test SmartArray with SmartStrings
        $smartArraySS = SmartArray::newSS($input);
        $resultSS = $smartArraySS->merge(...$arrays);
        $this->assertEquals(TestHelpers::recursiveHtmlEncode($expected), TestHelpers::toArrayResolveSS($resultSS));
    }

    public function mergeProvider(): array
    {
        return [
            'merge with empty array' => [
                'input' => ['a' => 1, 'b' => 2],
                'arrays' => [[]],
                'expected' => ['a' => 1, 'b' => 2],
            ],
            'merge empty array with non-empty' => [
                'input' => [],
                'arrays' => [['a' => 1, 'b' => 2]],
                'expected' => ['a' => 1, 'b' => 2],
            ],
            'merge multiple arrays' => [
                'input' => ['a' => 1],
                'arrays' => [
                    ['b' => 2],
                    ['c' => 3],
                ],
                'expected' => ['a' => 1, 'b' => 2, 'c' => 3],
            ],
            'merge with string keys overwrites' => [
                'input' => ['name' => 'Alice', 'age' => 30],
                'arrays' => [['name' => 'Bob', 'city' => 'NY']],
                'expected' => ['name' => 'Bob', 'age' => 30, 'city' => 'NY'],
            ],
            'merge numeric arrays combines and reindexes' => [
                'input' => [1, 2, 3],
                'arrays' => [[4, 5, 6], [7, 8, 9]],
                'expected' => [1, 2, 3, 4, 5, 6, 7, 8, 9],
            ],
            'merge mixed numeric and string keys' => [
                'input' => ['a' => 1, 0 => 'first'],
                'arrays' => [['b' => 2, 0 => 'second']],
                'expected' => ['a' => 1, 0 => 'first', 'b' => 2, 1 => 'second'],
            ],
            'merge with nested arrays' => [
                'input' => ['user' => ['name' => 'Alice']],
                'arrays' => [['user' => ['age' => 30]]],
                'expected' => ['user' => ['age' => 30]],
            ],
            'merge SmartArrays' => [
                'input' => ['a' => 1],
                'arrays' => [
                    new SmartArray(['b' => 2]),
                    new SmartArray(['c' => 3]),
                ],
                'expected' => ['a' => 1, 'b' => 2, 'c' => 3],
            ],
            'merge with special characters' => [
                'input' => ['name' => "O'Connor"],
                'arrays' => [['company' => 'Smith & Sons', 'title' => '<CEO>']],
                'expected' => ['name' => "O'Connor", 'company' => 'Smith & Sons', 'title' => '<CEO>'],
            ],
            'merge with null values' => [
                'input' => ['a' => 1, 'b' => null],
                'arrays' => [['b' => 2, 'c' => null]],
                'expected' => ['a' => 1, 'b' => 2, 'c' => null],
            ],
            'merge with boolean values' => [
                'input' => ['active' => true],
                'arrays' => [['verified' => false]],
                'expected' => ['active' => true, 'verified' => false],
            ],
            'merge deeply nested arrays' => [
                'input' => [
                    'level1' => [
                        'level2' => ['a' => 1]
                    ]
                ],
                'arrays' => [[
                                 'level1' => [
                                     'level2' => ['b' => 2]
                                 ]
                             ]],
                'expected' => [
                    'level1' => [
                        'level2' => ['b' => 2]
                    ]
                ],
            ],
        ];
    }

//endregion
}
