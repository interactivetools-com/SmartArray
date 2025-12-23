<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::last() method.
 *
 * last() returns the last element of the array, or SmartNull if empty.
 */
class LastTest extends SmartArrayTestCase
{

    /**
     * @dataProvider lastProvider
     */
    public function testLast(array $initialData, mixed $expected): void
    {
        $array   = new SmartArray($initialData);
        $arraySS = new SmartArrayHtml($initialData);

        // Compare Raw
        $result = $array->last();
        $this->assertSame(
            expected: $expected,
            actual  : $this->normalizeRaw($result),
        );

        // Compare SS
        $resultSS = $arraySS->last();
        $this->assertSame(
            expected: $this->htmlEncode($expected),
            actual  : $this->normalizeSS($resultSS)
        );
    }

    public static function lastProvider(): array
    {
        return [
            'flat array' => [
                'initialData' => ['first', 'second', 'third'],
                'expected'    => 'third',
            ],
            'associative array' => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'expected'    => 'gamma',
            ],
            'single element' => [
                'initialData' => ['only'],
                'expected'    => 'only',
            ],
            'empty array' => [
                'initialData' => [],
                'expected'    => null,
            ],
            'nested array' => [
                'initialData' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'expected'    => ['id' => 3, 'name' => 'Charlie'],
            ],
            'mixed keys' => [
                'initialData' => [10 => 'ten', 'twenty' => 'twenty', 30 => 'thirty'],
                'expected'    => 'thirty',
            ],
            'multi-dimensional array' => [
                'initialData' => [
                    'numbers' => [1, 2, 3],
                    'letters' => ['a', 'b', 'c'],
                    'symbols' => ['!', '@', '#'],
                ],
                'expected'    => ['!', '@', '#'],
            ],
            'last element is null' => [
                'initialData' => ['first', 'second', null],
                'expected'    => null,
            ],
            'array contains only null' => [
                'initialData' => [null],
                'expected'    => null,
            ],
            'duplicate values' => [
                'initialData' => ['value', 'value', 'last value'],
                'expected'    => 'last value',
            ],
            'numeric string keys' => [
                'initialData' => ['0' => 'zero', '1' => 'one', '2' => 'two'],
                'expected'    => 'two',
            ],
            'boolean values' => [
                'initialData' => [true, false, true],
                'expected'    => true,
            ],
        ];
    }

}
