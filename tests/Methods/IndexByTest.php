<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::indexBy() method.
 *
 * indexBy($key) re-indexes a nested array using the specified column as keys.
 * Duplicate keys will be overwritten (last value wins).
 */
class IndexByTest extends SmartArrayTestCase
{

    /**
     * @dataProvider indexByProvider
     */
    public function testIndexBy(array $input, string|int $key, array $expected): void
    {
        $smartArray = new SmartArray($input);
        $indexed    = $smartArray->indexBy($key);

        $expectedSmartArray = new SmartArray($expected);
        $this->assertSame($expectedSmartArray->toArray(), $indexed->toArray());
    }

    public static function indexByProvider(): array
    {
        return [
            'unique keys' => [
                'input'    => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'key'      => 'id',
                'expected' => [
                    1 => ['id' => 1, 'name' => 'Alice'],
                    2 => ['id' => 2, 'name' => 'Bob'],
                    3 => ['id' => 3, 'name' => 'Charlie'],
                ],
            ],
            'duplicate keys (last wins)' => [
                'input'    => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 1, 'name' => 'Alicia'],
                ],
                'key'      => 'id',
                'expected' => [
                    1 => ['id' => 1, 'name' => 'Alicia'],
                    2 => ['id' => 2, 'name' => 'Bob'],
                ],
            ],
            'empty array' => [
                'input'    => [],
                'key'      => 'id',
                'expected' => [],
            ],
            'mixed key types' => [
                'input'    => [
                    ['key' => 'alpha', 'value' => 'A'],
                    ['key' => 2, 'value' => 'B'],
                    ['key' => 'gamma', 'value' => 'C'],
                ],
                'key'      => 'key',
                'expected' => [
                    'alpha' => ['key' => 'alpha', 'value' => 'A'],
                    2       => ['key' => 2, 'value' => 'B'],
                    'gamma' => ['key' => 'gamma', 'value' => 'C'],
                ],
            ],
        ];
    }

}
