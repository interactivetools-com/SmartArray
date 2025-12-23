<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::groupBy() method.
 *
 * groupBy($key) groups a nested array by the values of a specific column.
 * Returns a SmartArray of SmartArrays, where each key is a group value.
 */
class GroupByTest extends SmartArrayTestCase
{

    /**
     * @dataProvider groupByProvider
     */
    public function testGroupBy(array $input, string|int $key, array $expected): void
    {
        $smartArray = new SmartArray($input);
        $grouped    = $smartArray->groupBy($key);

        $expectedSmartArray = new SmartArray($expected);
        $this->assertSame($expectedSmartArray->toArray(), $grouped->toArray());
    }

    public static function groupByProvider(): array
    {
        return [
            'unique group keys' => [
                'input'    => [
                    ['category' => 'Fruit', 'name' => 'Apple'],
                    ['category' => 'Vegetable', 'name' => 'Carrot'],
                    ['category' => 'Dairy', 'name' => 'Milk'],
                ],
                'key'      => 'category',
                'expected' => [
                    'Fruit'     => [
                        ['category' => 'Fruit', 'name' => 'Apple'],
                    ],
                    'Vegetable' => [
                        ['category' => 'Vegetable', 'name' => 'Carrot'],
                    ],
                    'Dairy'     => [
                        ['category' => 'Dairy', 'name' => 'Milk'],
                    ],
                ],
            ],
            'duplicate group keys' => [
                'input'    => [
                    ['category' => 'Fruit', 'name' => 'Apple'],
                    ['category' => 'Fruit', 'name' => 'Banana'],
                    ['category' => 'Vegetable', 'name' => 'Carrot'],
                    ['category' => 'Fruit', 'name' => 'Cherry'],
                    ['category' => 'Vegetable', 'name' => 'Lettuce'],
                ],
                'key'      => 'category',
                'expected' => [
                    'Fruit'     => [
                        ['category' => 'Fruit', 'name' => 'Apple'],
                        ['category' => 'Fruit', 'name' => 'Banana'],
                        ['category' => 'Fruit', 'name' => 'Cherry'],
                    ],
                    'Vegetable' => [
                        ['category' => 'Vegetable', 'name' => 'Carrot'],
                        ['category' => 'Vegetable', 'name' => 'Lettuce'],
                    ],
                ],
            ],
            'empty array' => [
                'input'    => [],
                'key'      => 'category',
                'expected' => [],
            ],
            'mixed group key types' => [
                'input'    => [
                    ['key' => 'alpha', 'value' => 'A'],
                    ['key' => 2, 'value' => 'B'],
                    ['key' => 'gamma', 'value' => 'C'],
                    ['key' => 2, 'value' => 'D'],
                ],
                'key'      => 'key',
                'expected' => [
                    'alpha' => [
                        ['key' => 'alpha', 'value' => 'A'],
                    ],
                    2       => [
                        ['key' => 2, 'value' => 'B'],
                        ['key' => 2, 'value' => 'D'],
                    ],
                    'gamma' => [
                        ['key' => 'gamma', 'value' => 'C'],
                    ],
                ],
            ],
        ];
    }

}
