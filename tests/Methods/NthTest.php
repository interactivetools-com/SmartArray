<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::nth() method.
 *
 * nth($index) returns the element at the given position (0-based).
 * Supports negative indices (-1 for last, -2 for second-to-last, etc.).
 */
class NthTest extends SmartArrayTestCase
{

    /**
     * @dataProvider nthProvider
     */
    public function testNth(array $initialData, int $index, mixed $expected): void
    {
        $array   = new SmartArray($initialData);
        $arraySS = new SmartArrayHtml($initialData);

        // Compare Raw
        $result = $array->nth($index);
        $this->assertSame(
            expected: $expected,
            actual  : $this->normalizeRaw($result),
        );

        // Compare SS
        $resultSS = $arraySS->nth($index);
        $this->assertSame(
            expected: $this->htmlEncode($expected),
            actual  : $this->normalizeSS($resultSS)
        );
    }

    public static function nthProvider(): array
    {
        return [
            'first element (index 0)' => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => 0,
                'expected'    => 'first',
            ],
            'second element (index 1)' => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => 1,
                'expected'    => 'second',
            ],
            'last element using negative index -1' => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => -1,
                'expected'    => 'third',
            ],
            'second-to-last using negative index -2' => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => -2,
                'expected'    => 'second',
            ],
            'index out of bounds (positive)' => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => 5,
                'expected'    => null,
            ],
            'index out of bounds (negative)' => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => -5,
                'expected'    => null,
            ],
            'associative array index 1' => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'index'       => 1,
                'expected'    => 'beta',
            ],
            'associative array index 0' => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'index'       => 0,
                'expected'    => 'alpha',
            ],
            'associative array negative index' => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'index'       => -1,
                'expected'    => 'gamma',
            ],
            'nested array' => [
                'initialData' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'index'       => 2,
                'expected'    => ['id' => 3, 'name' => 'Charlie'],
            ],
            'empty array' => [
                'initialData' => [],
                'index'       => 0,
                'expected'    => null,
            ],
            'single element index 0' => [
                'initialData' => ['only'],
                'index'       => 0,
                'expected'    => 'only',
            ],
            'single element negative index -1' => [
                'initialData' => ['only'],
                'index'       => -1,
                'expected'    => 'only',
            ],
            'single element out of bounds' => [
                'initialData' => ['only'],
                'index'       => 1,
                'expected'    => null,
            ],
            'mixed keys array' => [
                'initialData' => [10 => 'ten', 'twenty' => 'twenty', 30 => 'thirty'],
                'index'       => 1,
                'expected'    => 'twenty',
            ],
            'multi-dimensional array' => [
                'initialData' => [
                    'numbers' => [1, 2, 3],
                    'letters' => ['a', 'b', 'c'],
                    'symbols' => ['!', '@', '#'],
                ],
                'index'       => 1,
                'expected'    => ['a', 'b', 'c'],
            ],
            'out of order indexed array' => [
                'initialData' => [2 => 'first', 4 => 'second', 6 => 'third'],
                'index'       => 1,
                'expected'    => 'second',
            ],
            'index zero' => [
                'initialData' => ['zero', 'one', 'two'],
                'index'       => 0,
                'expected'    => 'zero',
            ],
        ];
    }

}
