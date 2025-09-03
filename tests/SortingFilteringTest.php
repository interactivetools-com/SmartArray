<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Itools\SmartArray\SmartArray;
use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\TestHelpers;

class SortingFilteringTest extends TestCase
{
//region Sorting & Filtering

    /**
     * @dataProvider sortProvider
     */
    public function testSort($input, $flags, $expected, $shouldThrowException = false): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a flat array, but got a nested array");
            $smartArray->sort($flags);
            return;
        }

        $sorted = $smartArray->sort($flags);

        // Verify sort worked correctly
        $this->assertEquals($expected, $sorted->toArray(), "Sorted array does not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public function sortProvider(): array
    {
        return [
            'empty array' => [
                'input' => [],
                'flags' => SORT_REGULAR,
                'expected' => [],
            ],
            'numeric array' => [
                'input' => [3, 1, 4, 1, 5, 9, 2, 6, 5],
                'flags' => SORT_NUMERIC,
                'expected' => [1, 1, 2, 3, 4, 5, 5, 6, 9],
            ],
            'string array' => [
                'input' => ['banana', 'apple', 'Cherry', 'date', 'Apple'],
                'flags' => SORT_STRING,
                'expected' => ['Apple', 'Cherry', 'apple', 'banana', 'date'],
            ],
            'case-insensitive sort' => [
                'input' => ['banana', 'apple', 'Cherry', 'date', 'Apple'],
                'flags' => SORT_STRING | SORT_FLAG_CASE,
                'expected' => ['apple', 'Apple', 'banana', 'Cherry', 'date'],
            ],
            'mixed types array' => [
                'input' => ['10', 20, '5', 15, '25'],
                'flags' => SORT_REGULAR,
                'expected' => ['5', '10', 15, 20, '25'],
            ],
            'array with null values' => [
                'input' => [3, null, 1, null, 2],
                'flags' => SORT_REGULAR,
                'expected' => [null, null, 1, 2, 3],
            ],
            'nested array throws exception' => [
                'input' => [[1, 2], [3, 4]],
                'flags' => SORT_REGULAR,
                'expected' => [],
                'shouldThrowException' => true,
            ],
        ];
    }

    /**
     * @dataProvider sortByProvider
     */
    public function testSortBy(array $input, string $column, int $type = SORT_REGULAR, array $expected = [], bool $shouldThrowException = false): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a nested array, but got a flat array");
            $smartArray->sortBy($column, $type);
            return;
        }

        // Start output buffering to capture any warnings about missing columns
        ob_start();
        $sorted = $smartArray->sortBy($column, $type);
        ob_end_clean();

        // Verify sort worked correctly
        $this->assertEquals($expected, $sorted->toArray(), "Sorted array does not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public function sortByProvider(): array
    {
        return [
            'sort by string column' => [
                'input' => [
                    ['name' => 'Charlie', 'age' => 30],
                    ['name' => 'Alice', 'age' => 25],
                    ['name' => 'Bob', 'age' => 35],
                ],
                'column' => 'name',
                'type' => SORT_STRING,
                'expected' => [
                    ['name' => 'Alice', 'age' => 25],
                    ['name' => 'Bob', 'age' => 35],
                    ['name' => 'Charlie', 'age' => 30],
                ],
            ],
            'sort by numeric column' => [
                'input' => [
                    ['id' => 3, 'value' => 'c'],
                    ['id' => 1, 'value' => 'a'],
                    ['id' => 2, 'value' => 'b'],
                ],
                'column' => 'id',
                'type' => SORT_NUMERIC,
                'expected' => [
                    ['id' => 1, 'value' => 'a'],
                    ['id' => 2, 'value' => 'b'],
                    ['id' => 3, 'value' => 'c'],
                ],
            ],
            'empty array' => [
                'input' => [],
                'column' => 'name',
                'type'  => 0,  // e.g., default SORT_REGULAR
                'expected' => [],
            ],
            'flat array throws exception' => [
                'input' => [1, 2, 3],
                'column' => 'any',
                'type'  => 0,  // e.g., default SORT_REGULAR
                'expected' => [],
                'shouldThrowException' => true,
            ],
        ];
    }

    /**
     * @dataProvider uniqueProvider
     */
    public function testUnique($input, $expected, $shouldThrowException = false): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a flat array, but got a nested array");
            $smartArray->unique();
            return;
        }

        $unique = $smartArray->unique();

        // Verify unique worked correctly
        $this->assertEquals($expected, $unique->toArray(), "Array with duplicates removed does not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public function uniqueProvider(): array
    {
        return [
            'empty array' => [
                'input' => [],
                'expected' => [],
            ],
            'array with numeric duplicates' => [
                'input' => [1, 2, 2, 3, 3, 3, 4],
                'expected' => [0 => 1, 1 => 2, 3 => 3, 6 => 4],
            ],
            'array with string duplicates' => [
                'input' => ['apple', 'banana', 'apple', 'cherry', 'banana'],
                'expected' => [0 => 'apple', 1 => 'banana', 3 => 'cherry'],
            ],
            'array with mixed type duplicates' => [
                'input' => [1, '1', '2', 2, true, 1, '1', false],
                'expected' => [0 => 1, 2 => '2', 7 => false],
            ],
            'array with null values' => [
                'input' => [null, 1, null, 2, null],
                'expected' => [0 => null, 1 => 1, 3 => 2],
            ],
            'preserves keys' => [
                'input' => ['a' => 1, 'b' => 2, 'c' => 2, 'd' => 1],
                'expected' => ['a' => 1, 'b' => 2],
            ],
            'nested array throws exception' => [
                'input' => [[1, 2], [1, 2], [3, 4]],
                'expected' => [],
                'shouldThrowException' => true,
            ],
        ];
    }

    /**
     * @dataProvider filterProvider
     */
    public function testFilter($input, $callback, $expected): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        $filtered = $callback ? $smartArray->filter($callback) : $smartArray->filter();

        // Verify filter worked correctly
        $this->assertEquals($expected, $filtered->toArray(), "Filtered array does not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    /**
     * @return array[]
     * @noinspection SpellCheckingInspection // ignore test strings
     */
    public function filterProvider(): array
    {
        return [
            'empty array' => [
                'input' => [],
                'callback' => null,
                'expected' => [],
            ],
            'filter without callback' => [
                'input' => [1, 0, true, false, '', null, 'hello', [], '0'],
                'callback' => null,
                'expected' => [0 => 1, 2 => true, 6 => 'hello'], // PHP's default behavior keeps truthy values
            ],
            'filter numbers greater than 5' => [
                'input' => [1, 3, 5, 7, 9, 2, 4, 6, 8],
                'callback' => fn($value) => $value > 5,
                'expected' => [3 => 7, 4 => 9, 7 => 6, 8 => 8],
            ],
            'filter strings by length' => [
                'input' => ['a', 'abc', 'abcd', 'ab', 'abcde'],
                'callback' => fn($value) => strlen($value) > 3,
                'expected' => [2 => 'abcd', 4 => 'abcde'],
            ],
            'filter with key access' => [
                'input' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
                'callback' => fn($value, $key) => $key === 'a' || $value > 3,
                'expected' => ['a' => 1, 'd' => 4],
            ],
            'filter nested arrays' => [
                'input' => [
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
                'input' => [1, '2', 3, '4', 5],
                'callback' => fn($value) => is_string($value),
                'expected' => [1 => '2', 3 => '4'],
            ],
            'filter with complex condition' => [
                'input' => [
                    'test1' => ['value' => 10, 'active' => true],
                    'test2' => ['value' => 20, 'active' => false],
                    'test3' => ['value' => 30, 'active' => true],
                ],
                'callback' => fn($item) => $item['active'] && $item['value'] > 15,
                'expected' => ['test3' => ['value' => 30, 'active' => true]],
            ],
        ];
    }

    /**
     * @dataProvider whereProvider
     */
    public function testWhere($input, $conditions, $expected): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        $filtered = $smartArray->where($conditions);

        // Verify where clause worked correctly
        $this->assertEquals($expected, $filtered->toArray(), "Filtered array does not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public function whereProvider(): array
    {
        return [
            'empty array' => [
                'input' => [],
                'conditions' => ['status' => 'active'],
                'expected' => [],
            ],
            'single condition' => [
                'input' => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2, 'status' => 'inactive'],
                    ['id' => 3, 'status' => 'active'],
                ],
                'conditions' => ['status' => 'active'],
                'expected' => [
                    0 => ['id' => 1, 'status' => 'active'],
                    2 => ['id' => 3, 'status' => 'active'],
                ],
            ],
            'multiple conditions' => [
                'input' => [
                    ['id' => 1, 'status' => 'active', 'type' => 'user'],
                    ['id' => 2, 'status' => 'active', 'type' => 'admin'],
                    ['id' => 3, 'status' => 'inactive', 'type' => 'user'],
                ],
                'conditions' => ['status' => 'active', 'type' => 'user'],
                'expected' => [
                    0 => ['id' => 1, 'status' => 'active', 'type' => 'user'],
                ],
            ],
            'non-matching conditions' => [
                'input' => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2, 'status' => 'inactive'],
                ],
                'conditions' => ['status' => 'pending'],
                'expected' => [],
            ],
            'condition with null value' => [
                'input' => [
                    ['id' => 1, 'parent_id' => null],
                    ['id' => 2, 'parent_id' => 1],
                    ['id' => 3, 'parent_id' => null],
                ],
                'conditions' => ['parent_id' => null],
                'expected' => [
                    0 => ['id' => 1, 'parent_id' => null],
                    2 => ['id' => 3, 'parent_id' => null],
                ],
            ],
            'missing column' => [
                'input' => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2], // missing status
                    ['id' => 3, 'status' => 'active'],
                ],
                'conditions' => ['status' => 'active'],
                'expected' => [
                    0 => ['id' => 1, 'status' => 'active'],
                    2 => ['id' => 3, 'status' => 'active'],
                ],
            ],
            'non-array elements are skipped' => [
                'input' => [
                    ['id' => 1, 'status' => 'active'],
                    'not an array',
                    ['id' => 2, 'status' => 'active'],
                ],
                'conditions' => ['status' => 'active'],
                'expected' => [
                    0 => ['id' => 1, 'status' => 'active'],
                    2 => ['id' => 2, 'status' => 'active'],
                ],
            ],
            'exact value matching' => [
                'input' => [
                    ['count' => 0],
                    ['count' => '0'],
                    ['count' => null],
                    ['count' => false],
                    ['count' => ''],
                ],
                'conditions' => ['count' => 0],
                'expected' => [
                    0 => ['count' => 0],
                    1 => ['count' => '0'],
                    2 => ['count' => null],
                    3 => ['count' => false],
                ],
            ],
            'multiple exact value matching' => [
                'input' => [
                    ['status' => 'active', 'type' => 'user'],
                    ['status' => 'active', 'type' => 'admin'],
                    ['status' => 'inactive', 'type' => 'user'],
                    ['status' => 'active'], // missing type
                    ['type' => 'user'],    // missing status
                ],
                'conditions' => [
                    'status' => 'active',
                    'type' => 'user'
                ],
                'expected' => [
                    0 => ['status' => 'active', 'type' => 'user'],
                ],
            ],
        ];
    }

//endregion
}
