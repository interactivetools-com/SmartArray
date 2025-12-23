<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use RuntimeException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::column() method.
 *
 * column($columnKey, $indexKey) mirrors PHP's array_column() function.
 * - column('name') is equivalent to pluck('name')
 * - column('name', 'id') is equivalent to pluck('name', 'id')
 * - column(null, 'id') is equivalent to indexBy('id')
 */
class ColumnTest extends SmartArrayTestCase
{

    /**
     * @dataProvider columnProvider
     */
    public function testColumn(array $input, string|int|null $columnKey, string|int|null $indexKey, array $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        $result = $smartArray->column($columnKey, $indexKey);

        $this->assertEquals($expected, $result->toArray(), "column() result does not match expected output");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified");
    }

    public function testColumnWithBothNullThrowsException(): void
    {
        $smartArray = new SmartArray([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("column() unexpected arguments");

        $smartArray->column(null, null);
    }

    public static function columnProvider(): array
    {
        return [
            'extract column values (mirrors pluck)' => [
                'input'     => [
                    ['id' => 1, 'name' => 'Alice', 'role' => 'admin'],
                    ['id' => 2, 'name' => 'Bob', 'role' => 'user'],
                    ['id' => 3, 'name' => 'Charlie', 'role' => 'moderator'],
                ],
                'columnKey' => 'name',
                'indexKey'  => null,
                'expected'  => ['Alice', 'Bob', 'Charlie'],
            ],
            'column indexed by another column' => [
                'input'     => [
                    ['id' => 1, 'name' => 'Alice', 'role' => 'admin'],
                    ['id' => 2, 'name' => 'Bob', 'role' => 'user'],
                    ['id' => 3, 'name' => 'Charlie', 'role' => 'moderator'],
                ],
                'columnKey' => 'name',
                'indexKey'  => 'id',
                'expected'  => [
                    1 => 'Alice',
                    2 => 'Bob',
                    3 => 'Charlie',
                ],
            ],
            'full rows indexed by column (mirrors indexBy)' => [
                'input'     => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'columnKey' => null,
                'indexKey'  => 'id',
                'expected'  => [
                    1 => ['id' => 1, 'name' => 'Alice'],
                    2 => ['id' => 2, 'name' => 'Bob'],
                    3 => ['id' => 3, 'name' => 'Charlie'],
                ],
            ],
            'empty array' => [
                'input'     => [],
                'columnKey' => 'name',
                'indexKey'  => null,
                'expected'  => [],
            ],
            'numeric column keys' => [
                'input'     => [
                    [0 => 'zero', 1 => 'one', 2 => 'two'],
                    [0 => 'a', 1 => 'b', 2 => 'c'],
                ],
                'columnKey' => 1,
                'indexKey'  => null,
                'expected'  => ['one', 'b'],
            ],
            'numeric index keys' => [
                'input'     => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
                'columnKey' => 'name',
                'indexKey'  => 'id',
                'expected'  => [
                    1 => 'Alice',
                    2 => 'Bob',
                ],
            ],
            'string index keys' => [
                'input'     => [
                    ['code' => 'a1', 'name' => 'Alice'],
                    ['code' => 'b2', 'name' => 'Bob'],
                ],
                'columnKey' => 'name',
                'indexKey'  => 'code',
                'expected'  => [
                    'a1' => 'Alice',
                    'b2' => 'Bob',
                ],
            ],
            'duplicate index keys (last wins)' => [
                'input'     => [
                    ['group' => 'A', 'name' => 'Alice'],
                    ['group' => 'B', 'name' => 'Bob'],
                    ['group' => 'A', 'name' => 'Alicia'],
                ],
                'columnKey' => 'name',
                'indexKey'  => 'group',
                'expected'  => [
                    'A' => 'Alicia',
                    'B' => 'Bob',
                ],
            ],
        ];
    }

}
