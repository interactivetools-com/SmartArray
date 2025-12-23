<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::first() method.
 *
 * first() returns the first element of the array, or SmartNull if empty.
 */
class FirstTest extends SmartArrayTestCase
{

    /**
     * @dataProvider firstProvider
     */
    public function testFirst(array $initialData, mixed $expected): void
    {
        $array   = new SmartArray($initialData);
        $arraySS = new SmartArrayHtml($initialData);

        // Compare Raw
        $result = $array->first();
        $this->assertSame(
            expected: $expected,
            actual  : $this->normalizeRaw($result),
        );

        // Compare SS
        $resultSS = $arraySS->first();
        $this->assertSame(
            expected: $this->htmlEncode($expected),
            actual  : $this->normalizeSS($resultSS)
        );
    }

    public static function firstProvider(): array
    {
        return [
            'flat array' => [
                'initialData' => ['first', 'second', 'third'],
                'expected'    => 'first',
            ],
            'associative array' => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'expected'    => 'alpha',
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
                'expected'    => ['id' => 1, 'name' => 'Alice'],
            ],
            'mixed keys' => [
                'initialData' => [10 => 'ten', 'twenty' => 'twenty', 30 => 'thirty'],
                'expected'    => 'ten',
            ],
            'multi-dimensional array' => [
                'initialData' => [
                    'numbers' => [1, 2, 3],
                    'letters' => ['a', 'b', 'c'],
                    'symbols' => ['!', '@', '#'],
                ],
                'expected'    => [1, 2, 3],
            ],
            'first element is null' => [
                'initialData' => [null, 'second', 'third'],
                'expected'    => null,
            ],
            'array contains only null' => [
                'initialData' => [null],
                'expected'    => null,
            ],
            'duplicate values' => [
                'initialData' => ['first value', 'value', 'value'],
                'expected'    => 'first value',
            ],
            'numeric string keys' => [
                'initialData' => ['0' => 'zero', '1' => 'one', '2' => 'two'],
                'expected'    => 'zero',
            ],
            'boolean values' => [
                'initialData' => [true, false, true],
                'expected'    => true,
            ],
        ];
    }

}
