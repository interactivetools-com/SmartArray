<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::pluck() method.
 *
 * pluck($valueColumn, $keyColumn) extracts a single column from a nested array.
 * Optionally re-indexes the result using another column as keys.
 */
class PluckTest extends SmartArrayTestCase
{

    /**
     * @dataProvider pluckProvider
     */
    public function testPluck(array $input, string|int $key, array $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        // Capture warnings for missing keys
        ob_start();
        $plucked = $smartArray->pluck($key);
        ob_end_clean();

        $this->assertEquals($expected, $plucked->toArray(), "Plucked SmartArray does not match expected output.");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified.");
    }

    /**
     * @dataProvider pluckWithKeyColumnProvider
     */
    public function testPluckWithKeyColumn(array $input, string|int $valueColumn, string|int $keyColumn, array $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        ob_start();
        $plucked = $smartArray->pluck($valueColumn, $keyColumn);
        ob_end_clean();

        $this->assertEquals($expected, $plucked->toArray(), "Plucked SmartArray with key column does not match expected output.");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified.");
    }

    public function testPluckOnNonNestedArrayThrowsException(): void
    {
        $smartArray    = new SmartArray(['id' => 1, 'name' => 'Alice']);
        $originalArray = $smartArray->toArray();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Expected a nested array, but got a flat array");

        $smartArray->pluck('name');

        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified.");
    }

    public function testPluckMissingKeyOutputsWarning(): void
    {
        $smartArray = new SmartArray([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Charlie'],
        ]);

        ob_start();
        $plucked = $smartArray->pluck('city');
        $output  = ob_get_clean();

        $expectedWarningPattern = "/Warning: pluck\(\): 'city' doesn't exist/s";
        $this->assertMatchesRegularExpression($expectedWarningPattern, $output, "Expected warning message not found in output.");

        $expected = [];
        $this->assertEquals($expected, $plucked->toArray(), "Plucked SmartArray does not match expected output.");
    }

    public static function pluckProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'key'      => 'id',
                'expected' => [],
            ],
            'existing key' => [
                'input'    => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'key'      => 'name',
                'expected' => ['Alice', 'Bob', 'Charlie'],
            ],
            'partially missing key' => [
                'input'    => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'key'      => 'name',
                'expected' => ['Alice', 'Charlie'],
            ],
            'key missing in all elements' => [
                'input'    => [
                    ['id' => 1],
                    ['id' => 2],
                    ['id' => 3],
                ],
                'key'      => 'name',
                'expected' => [],
            ],
            'nested array values' => [
                'input'    => [
                    ['id' => 1, 'data' => ['value' => 10]],
                    ['id' => 2, 'data' => ['value' => 20]],
                    ['id' => 3, 'data' => ['value' => 30]],
                ],
                'key'      => 'data',
                'expected' => [
                    ['value' => 10],
                    ['value' => 20],
                    ['value' => 30],
                ],
            ],
            'numeric keys' => [
                'input'    => [
                    [0 => 'zero', 1 => 'one'],
                    [0 => 'zero', 1 => 'one'],
                    [0 => 'zero', 1 => 'one'],
                ],
                'key'      => 1,
                'expected' => ['one', 'one', 'one'],
            ],
            'numeric string key' => [
                'input'    => [
                    ['0' => 'zero', '1' => 'one'],
                    ['0' => 'zero', '1' => 'one'],
                ],
                'key'      => '1',
                'expected' => ['one', 'one'],
            ],
        ];
    }

    public static function pluckWithKeyColumnProvider(): array
    {
        return [
            'with key column' => [
                'input'       => [
                    ['id' => 1, 'name' => 'Alice', 'role' => 'admin'],
                    ['id' => 2, 'name' => 'Bob', 'role' => 'user'],
                    ['id' => 3, 'name' => 'Charlie', 'role' => 'moderator'],
                ],
                'valueColumn' => 'name',
                'keyColumn'   => 'id',
                'expected'    => [
                    1 => 'Alice',
                    2 => 'Bob',
                    3 => 'Charlie',
                ],
            ],
            'with string key column' => [
                'input'       => [
                    ['id' => 'a1', 'name' => 'Alice', 'role' => 'admin'],
                    ['id' => 'b2', 'name' => 'Bob', 'role' => 'user'],
                    ['id' => 'c3', 'name' => 'Charlie', 'role' => 'moderator'],
                ],
                'valueColumn' => 'role',
                'keyColumn'   => 'id',
                'expected'    => [
                    'a1' => 'admin',
                    'b2' => 'user',
                    'c3' => 'moderator',
                ],
            ],
            'with non-scalar values' => [
                'input'       => [
                    ['id' => 1, 'name' => 'Alice', 'info' => ['email' => 'alice@example.com']],
                    ['id' => 2, 'name' => 'Bob', 'info' => ['email' => 'bob@example.com']],
                ],
                'valueColumn' => 'info',
                'keyColumn'   => 'id',
                'expected'    => [
                    1 => ['email' => 'alice@example.com'],
                    2 => ['email' => 'bob@example.com'],
                ],
            ],
            'associative input array' => [
                'input'       => [
                    'a' => ['id' => 1, 'name' => 'Alice'],
                    'b' => ['id' => 2, 'name' => 'Bob'],
                    'c' => ['id' => 3, 'name' => 'Charlie'],
                ],
                'valueColumn' => 'name',
                'keyColumn'   => 'id',
                'expected'    => [
                    1 => 'Alice',
                    2 => 'Bob',
                    3 => 'Charlie',
                ],
            ],
            'duplicate keys (last wins)' => [
                'input'       => [
                    ['group' => 'A', 'name' => 'Alice', 'score' => 90],
                    ['group' => 'B', 'name' => 'Bob', 'score' => 85],
                    ['group' => 'A', 'name' => 'Charlie', 'score' => 95],
                ],
                'valueColumn' => 'score',
                'keyColumn'   => 'group',
                'expected'    => [
                    'A' => 95,
                    'B' => 85,
                ],
            ],
            'empty array' => [
                'input'       => [],
                'valueColumn' => 'name',
                'keyColumn'   => 'id',
                'expected'    => [],
            ],
        ];
    }

}
