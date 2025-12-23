<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::sort() method.
 *
 * sort($flags) sorts a flat array by value. Returns a new SmartArray (immutable).
 * Throws InvalidArgumentException for nested arrays.
 */
class SortTest extends SmartArrayTestCase
{

    /**
     * @dataProvider sortProvider
     */
    public function testSort(array $input, int $flags, array $expected, bool $shouldThrowException = false): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a flat array, but got a nested array");
            $smartArray->sort($flags);
            return;
        }

        $sorted = $smartArray->sort($flags);

        // Verify sort worked correctly
        $this->assertEquals($expected, $sorted->toArray(), "Sorted array does not match expected output");

        // Verify original array wasn't modified (immutable)
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public static function sortProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'flags'    => SORT_REGULAR,
                'expected' => [],
            ],
            'numeric array' => [
                'input'    => [3, 1, 4, 1, 5, 9, 2, 6, 5],
                'flags'    => SORT_NUMERIC,
                'expected' => [1, 1, 2, 3, 4, 5, 5, 6, 9],
            ],
            'string array' => [
                'input'    => ['banana', 'apple', 'Cherry', 'date', 'Apple'],
                'flags'    => SORT_STRING,
                'expected' => ['Apple', 'Cherry', 'apple', 'banana', 'date'],
            ],
            'case-insensitive sort' => [
                'input'    => ['banana', 'apple', 'Cherry', 'date', 'Apple'],
                'flags'    => SORT_STRING | SORT_FLAG_CASE,
                'expected' => ['apple', 'Apple', 'banana', 'Cherry', 'date'],
            ],
            'mixed types array' => [
                'input'    => ['10', 20, '5', 15, '25'],
                'flags'    => SORT_REGULAR,
                'expected' => ['5', '10', 15, 20, '25'],
            ],
            'array with null values' => [
                'input'    => [3, null, 1, null, 2],
                'flags'    => SORT_REGULAR,
                'expected' => [null, null, 1, 2, 3],
            ],
            'nested array throws exception' => [
                'input'                => [[1, 2], [3, 4]],
                'flags'                => SORT_REGULAR,
                'expected'             => [],
                'shouldThrowException' => true,
            ],
        ];
    }

}
