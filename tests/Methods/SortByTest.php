<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::sortBy() method.
 *
 * sortBy($column, $flags) sorts a nested array by a specific column.
 * Returns a new SmartArray (immutable). Throws InvalidArgumentException for flat arrays.
 */
class SortByTest extends SmartArrayTestCase
{

    /**
     * @dataProvider sortByProvider
     */
    public function testSortBy(
        array $input,
        string $column,
        int $type = SORT_REGULAR,
        array $expected = [],
        bool $shouldThrowException = false
    ): void {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a nested array, but got a flat array");
            $smartArray->sortBy($column, $type);
            return;
        }

        // Capture any warnings about missing columns
        ob_start();
        $sorted = $smartArray->sortBy($column, $type);
        ob_end_clean();

        // Verify sort worked correctly
        $this->assertEquals($expected, $sorted->toArray(), "Sorted array does not match expected output");

        // Verify original array wasn't modified (immutable)
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public static function sortByProvider(): array
    {
        return [
            'sort by string column' => [
                'input'    => [
                    ['name' => 'Charlie', 'age' => 30],
                    ['name' => 'Alice', 'age' => 25],
                    ['name' => 'Bob', 'age' => 35],
                ],
                'column'   => 'name',
                'type'     => SORT_STRING,
                'expected' => [
                    ['name' => 'Alice', 'age' => 25],
                    ['name' => 'Bob', 'age' => 35],
                    ['name' => 'Charlie', 'age' => 30],
                ],
            ],
            'sort by numeric column' => [
                'input'    => [
                    ['id' => 3, 'value' => 'c'],
                    ['id' => 1, 'value' => 'a'],
                    ['id' => 2, 'value' => 'b'],
                ],
                'column'   => 'id',
                'type'     => SORT_NUMERIC,
                'expected' => [
                    ['id' => 1, 'value' => 'a'],
                    ['id' => 2, 'value' => 'b'],
                    ['id' => 3, 'value' => 'c'],
                ],
            ],
            'empty array' => [
                'input'    => [],
                'column'   => 'name',
                'type'     => SORT_REGULAR,
                'expected' => [],
            ],
            'flat array throws exception' => [
                'input'                => [1, 2, 3],
                'column'               => 'any',
                'type'                 => SORT_REGULAR,
                'expected'             => [],
                'shouldThrowException' => true,
            ],
        ];
    }

}
