<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::filter() method.
 *
 * filter($callback) filters elements using a callback function.
 * Without callback, removes falsy values (PHP's default array_filter behavior).
 * Returns a new SmartArray (immutable), preserving keys.
 */
class FilterTest extends SmartArrayTestCase
{

    /**
     * @dataProvider filterProvider
     */
    public function testFilter(array $input, ?callable $callback, array $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        $filtered = $callback ? $smartArray->filter($callback) : $smartArray->filter();

        // Verify filter worked correctly
        $this->assertEquals($expected, $filtered->toArray(), "Filtered array does not match expected output");

        // Verify original array wasn't modified (immutable)
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    /**
     * @noinspection SpellCheckingInspection
     */
    public static function filterProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'callback' => null,
                'expected' => [],
            ],
            'filter without callback (removes falsy)' => [
                'input'    => [1, 0, true, false, '', null, 'hello', [], '0'],
                'callback' => null,
                'expected' => [0 => 1, 2 => true, 6 => 'hello'],
            ],
            'filter numbers greater than 5' => [
                'input'    => [1, 3, 5, 7, 9, 2, 4, 6, 8],
                'callback' => fn($value) => $value > 5,
                'expected' => [3 => 7, 4 => 9, 7 => 6, 8 => 8],
            ],
            'filter strings by length' => [
                'input'    => ['a', 'abc', 'abcd', 'ab', 'abcde'],
                'callback' => fn($value) => strlen($value) > 3,
                'expected' => [2 => 'abcd', 4 => 'abcde'],
            ],
            'filter with key access' => [
                'input'    => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
                'callback' => fn($value, $key) => $key === 'a' || $value > 3,
                'expected' => ['a' => 1, 'd' => 4],
            ],
            'filter nested arrays' => [
                'input'    => [
                    ['id' => 1, 'data' => []],
                    ['id' => 2, 'data' => ['a']],
                    ['id' => 3, 'data' => ['b', 'c']],
                ],
                'callback' => fn($row) => count($row['data']) > 0,
                'expected' => [
                    1 => ['id' => 2, 'data' => ['a']],
                    2 => ['id' => 3, 'data' => ['b', 'c']],
                ],
            ],
            'filter with type checking' => [
                'input'    => [1, '2', 3, '4', 5],
                'callback' => fn($value) => is_string($value),
                'expected' => [1 => '2', 3 => '4'],
            ],
            'filter with complex condition' => [
                'input'    => [
                    'test1' => ['value' => 10, 'active' => true],
                    'test2' => ['value' => 20, 'active' => false],
                    'test3' => ['value' => 30, 'active' => true],
                ],
                'callback' => fn($item) => $item['active'] && $item['value'] > 15,
                'expected' => ['test3' => ['value' => 30, 'active' => true]],
            ],
        ];
    }

}
