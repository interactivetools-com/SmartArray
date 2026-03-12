<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartString\SmartString;

/**
 * Tests for SmartArray::whereNot() method.
 *
 * whereNot($field, $value) filters a nested array, excluding rows where field matches value.
 * The inverse of where() - uses loose comparison (==).
 * Returns a new SmartArray (immutable), preserving keys.
 */
class WhereNotTest extends SmartArrayTestCase
{

    /**
     * @dataProvider whereNotProvider
     */
    public function testWhereNot(array $input, string $field, mixed $value, array $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        $filtered = $smartArray->whereNot($field, $value);

        // Verify whereNot filtered correctly
        $this->assertEquals($expected, $filtered->toArray(), "Filtered array does not match expected output");

        // Verify original array wasn't modified (immutable)
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public static function whereNotProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'field'    => 'status',
                'value'    => 'active',
                'expected' => [],
            ],
            'excludes matching rows' => [
                'input'    => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2, 'status' => 'inactive'],
                    ['id' => 3, 'status' => 'active'],
                ],
                'field'    => 'status',
                'value'    => 'active',
                'expected' => [
                    1 => ['id' => 2, 'status' => 'inactive'],
                ],
            ],
            'no rows match - all kept' => [
                'input'    => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2, 'status' => 'inactive'],
                ],
                'field'    => 'status',
                'value'    => 'pending',
                'expected' => [
                    0 => ['id' => 1, 'status' => 'active'],
                    1 => ['id' => 2, 'status' => 'inactive'],
                ],
            ],
            'all rows match - none kept' => [
                'input'    => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2, 'status' => 'active'],
                ],
                'field'    => 'status',
                'value'    => 'active',
                'expected' => [],
            ],
            'single row excluded' => [
                'input'    => [
                    ['id' => 1, 'role' => 'admin'],
                ],
                'field'    => 'role',
                'value'    => 'admin',
                'expected' => [],
            ],
            'single row kept' => [
                'input'    => [
                    ['id' => 1, 'role' => 'user'],
                ],
                'field'    => 'role',
                'value'    => 'admin',
                'expected' => [
                    0 => ['id' => 1, 'role' => 'user'],
                ],
            ],
            'missing field - row is kept' => [
                'input'    => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2], // missing status field
                    ['id' => 3, 'status' => 'active'],
                ],
                'field'    => 'status',
                'value'    => 'active',
                'expected' => [
                    1 => ['id' => 2], // kept because field is missing
                ],
            ],
            'non-array elements are skipped' => [
                'input'    => [
                    ['id' => 1, 'status' => 'active'],
                    'not an array',
                    42,
                    ['id' => 2, 'status' => 'inactive'],
                ],
                'field'    => 'status',
                'value'    => 'active',
                'expected' => [
                    3 => ['id' => 2, 'status' => 'inactive'],
                ],
            ],
            'condition with null value' => [
                'input'    => [
                    ['id' => 1, 'parent_id' => null],
                    ['id' => 2, 'parent_id' => 1],
                    ['id' => 3, 'parent_id' => null],
                ],
                'field'    => 'parent_id',
                'value'    => null,
                'expected' => [
                    1 => ['id' => 2, 'parent_id' => 1],
                ],
            ],
            'loose comparison - int vs string' => [
                'input'    => [
                    ['id' => 1, 'count' => 1],
                    ['id' => 2, 'count' => '1'],
                    ['id' => 3, 'count' => 2],
                ],
                'field'    => 'count',
                'value'    => 1,
                'expected' => [
                    2 => ['id' => 3, 'count' => 2],
                ],
            ],
            'loose comparison - zero equivalents' => [
                // PHP 8: 0 == '' is false, so empty string row is KEPT
                // 0, '0', null, false are all == 0, so they are excluded
                'input'    => [
                    ['id' => 1, 'count' => 0],
                    ['id' => 2, 'count' => '0'],
                    ['id' => 3, 'count' => null],
                    ['id' => 4, 'count' => false],
                    ['id' => 5, 'count' => ''],
                ],
                'field'    => 'count',
                'value'    => 0,
                'expected' => [
                    4 => ['id' => 5, 'count' => ''], // PHP 8: 0 == '' is false, so kept
                ],
            ],
            'preserves original keys' => [
                'input'    => [
                    ['id' => 1, 'status' => 'draft'],
                    ['id' => 2, 'status' => 'active'],
                    ['id' => 3, 'status' => 'draft'],
                    ['id' => 4, 'status' => 'active'],
                ],
                'field'    => 'status',
                'value'    => 'draft',
                'expected' => [
                    1 => ['id' => 2, 'status' => 'active'],
                    3 => ['id' => 4, 'status' => 'active'],
                ],
            ],
            'excludes by numeric value' => [
                'input'    => [
                    ['id' => 1, 'priority' => 5],
                    ['id' => 2, 'priority' => 10],
                    ['id' => 3, 'priority' => 5],
                ],
                'field'    => 'priority',
                'value'    => 5,
                'expected' => [
                    1 => ['id' => 2, 'priority' => 10],
                ],
            ],
            'excludes by boolean value' => [
                'input'    => [
                    ['id' => 1, 'active' => true],
                    ['id' => 2, 'active' => false],
                    ['id' => 3, 'active' => true],
                ],
                'field'    => 'active',
                'value'    => true,
                'expected' => [
                    1 => ['id' => 2, 'active' => false],
                ],
            ],
        ];
    }

    /**
     * Test that whereNot() returns a new instance (not the same object)
     */
    public function testWhereNotReturnsNewInstance(): void
    {
        $arr = new SmartArray([
            ['id' => 1, 'status' => 'active'],
            ['id' => 2, 'status' => 'inactive'],
        ]);

        $result = $arr->whereNot('status', 'active');

        $this->assertNotSame($arr, $result);
        $this->assertInstanceOf(SmartArray::class, $result);
    }

    /**
     * Test that whereNot() does not modify the original SmartArray
     */
    public function testWhereNotDoesNotModifyOriginal(): void
    {
        $input = [
            ['id' => 1, 'status' => 'active'],
            ['id' => 2, 'status' => 'inactive'],
            ['id' => 3, 'status' => 'active'],
        ];
        $arr          = new SmartArray($input);
        $originalData = $arr->toArray();

        $arr->whereNot('status', 'active');

        $this->assertSame($originalData, $arr->toArray());
    }

    /**
     * Test whereNot() throws on flat array
     */
    public function testWhereNotThrowsOnFlatArray(): void
    {
        $arr = new SmartArray(['a', 'b', 'c']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Expected a nested array, but got a flat array");

        $arr->whereNot('key', 'value');
    }

    /**
     * Test that whereNot() unwraps SmartString values passed as the value argument
     */
    public function testWhereNotUnwrapsSmartStringValue(): void
    {
        $arr = new SmartArray([
            ['id' => 1, 'status' => 'active'],
            ['id' => 2, 'status' => 'inactive'],
            ['id' => 3, 'status' => 'active'],
        ]);

        $smartValue = new SmartString('active');
        $result     = $arr->whereNot('status', $smartValue);

        $expected = [
            1 => ['id' => 2, 'status' => 'inactive'],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test that whereNot() is the true inverse of where() - their results
     * partition the original array rows with no overlap and no gaps.
     */
    public function testWhereNotIsInverseOfWhere(): void
    {
        $input = [
            ['id' => 1, 'status' => 'active'],
            ['id' => 2, 'status' => 'inactive'],
            ['id' => 3, 'status' => 'active'],
            ['id' => 4, 'status' => 'pending'],
        ];
        $arr = new SmartArray($input);

        $whereResult    = $arr->where('status', 'active');
        $whereNotResult = $arr->whereNot('status', 'active');

        $whereKeys    = array_keys($whereResult->toArray());
        $whereNotKeys = array_keys($whereNotResult->toArray());
        $originalKeys = array_keys($arr->toArray());

        // No overlap between where() and whereNot() keys
        $this->assertSame([], array_intersect($whereKeys, $whereNotKeys), "where() and whereNot() keys should not overlap");

        // Union of keys equals original keys
        $combinedKeys = array_merge($whereKeys, $whereNotKeys);
        sort($combinedKeys);
        sort($originalKeys);
        $this->assertSame($originalKeys, $combinedKeys, "Union of where() and whereNot() keys should equal original keys");
    }

}
