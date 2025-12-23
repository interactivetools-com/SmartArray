<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::where() method.
 *
 * where($conditions) filters a nested array where rows match all conditions.
 * Uses loose comparison (==) for database/form data tolerance.
 * Returns a new SmartArray (immutable), preserving keys.
 */
class WhereTest extends SmartArrayTestCase
{

    /**
     * @dataProvider whereProvider
     */
    public function testWhere(array $input, array $conditions, array $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        $filtered = $smartArray->where($conditions);

        // Verify where clause worked correctly
        $this->assertEquals($expected, $filtered->toArray(), "Filtered array does not match expected output");

        // Verify original array wasn't modified (immutable)
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public static function whereProvider(): array
    {
        return [
            'empty array' => [
                'input'      => [],
                'conditions' => ['status' => 'active'],
                'expected'   => [],
            ],
            'single condition' => [
                'input'      => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2, 'status' => 'inactive'],
                    ['id' => 3, 'status' => 'active'],
                ],
                'conditions' => ['status' => 'active'],
                'expected'   => [
                    0 => ['id' => 1, 'status' => 'active'],
                    2 => ['id' => 3, 'status' => 'active'],
                ],
            ],
            'multiple conditions' => [
                'input'      => [
                    ['id' => 1, 'status' => 'active', 'type' => 'user'],
                    ['id' => 2, 'status' => 'active', 'type' => 'admin'],
                    ['id' => 3, 'status' => 'inactive', 'type' => 'user'],
                ],
                'conditions' => ['status' => 'active', 'type' => 'user'],
                'expected'   => [
                    0 => ['id' => 1, 'status' => 'active', 'type' => 'user'],
                ],
            ],
            'non-matching conditions' => [
                'input'      => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2, 'status' => 'inactive'],
                ],
                'conditions' => ['status' => 'pending'],
                'expected'   => [],
            ],
            'condition with null value' => [
                'input'      => [
                    ['id' => 1, 'parent_id' => null],
                    ['id' => 2, 'parent_id' => 1],
                    ['id' => 3, 'parent_id' => null],
                ],
                'conditions' => ['parent_id' => null],
                'expected'   => [
                    0 => ['id' => 1, 'parent_id' => null],
                    2 => ['id' => 3, 'parent_id' => null],
                ],
            ],
            'missing column' => [
                'input'      => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2], // missing status
                    ['id' => 3, 'status' => 'active'],
                ],
                'conditions' => ['status' => 'active'],
                'expected'   => [
                    0 => ['id' => 1, 'status' => 'active'],
                    2 => ['id' => 3, 'status' => 'active'],
                ],
            ],
            'non-array elements are skipped' => [
                'input'      => [
                    ['id' => 1, 'status' => 'active'],
                    'not an array',
                    ['id' => 2, 'status' => 'active'],
                ],
                'conditions' => ['status' => 'active'],
                'expected'   => [
                    0 => ['id' => 1, 'status' => 'active'],
                    2 => ['id' => 2, 'status' => 'active'],
                ],
            ],
            'loose comparison matching' => [
                'input'      => [
                    ['count' => 0],
                    ['count' => '0'],
                    ['count' => null],
                    ['count' => false],
                    ['count' => ''],
                ],
                'conditions' => ['count' => 0],
                'expected'   => [
                    0 => ['count' => 0],
                    1 => ['count' => '0'],
                    2 => ['count' => null],
                    3 => ['count' => false],
                ],
            ],
            'multiple conditions with missing columns' => [
                'input'      => [
                    ['status' => 'active', 'type' => 'user'],
                    ['status' => 'active', 'type' => 'admin'],
                    ['status' => 'inactive', 'type' => 'user'],
                    ['status' => 'active'], // missing type
                    ['type' => 'user'],     // missing status
                ],
                'conditions' => ['status' => 'active', 'type' => 'user'],
                'expected'   => [
                    0 => ['status' => 'active', 'type' => 'user'],
                ],
            ],
        ];
    }

    /**
     * Test that where() throws on list array format (common mistake)
     */
    public function testWhereThrowsOnListArrayConditions(): void
    {
        $arr = new SmartArray([
            ['id' => 1, 'status' => 'active'],
            ['id' => 2, 'status' => 'inactive'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Got a list array");

        // Wrong format: should be ['status' => 'active'] not ['status', 'active']
        $arr->where(['status', 'active']);
    }

    /**
     * Test where() with flat array returns empty (doesn't throw)
     */
    public function testWhereOnFlatArrayReturnsEmpty(): void
    {
        $arr = new SmartArray(['a', 'b', 'c']);

        // Flat array elements are skipped (not arrays), so result is empty
        $result = $arr->where(['key' => 'value']);

        $this->assertSame([], $result->toArray());
    }

    /**
     * Test where() shorthand syntax (two arguments)
     */
    public function testWhereShorthandSyntax(): void
    {
        $arr = new SmartArray([
            ['id' => 1, 'status' => 'active'],
            ['id' => 2, 'status' => 'inactive'],
            ['id' => 3, 'status' => 'active'],
        ]);

        $result = $arr->where('status', 'active');

        $this->assertSame(
            [0 => ['id' => 1, 'status' => 'active'], 2 => ['id' => 3, 'status' => 'active']],
            $result->toArray()
        );
    }

}
