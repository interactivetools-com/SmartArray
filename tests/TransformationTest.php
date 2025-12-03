<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Itools\SmartArray\SmartArray;
use Itools\SmartString\SmartString;
use RuntimeException;

class TransformationTest extends TestCase
{

//region Array Transformation

    /**
     * @dataProvider toArrayProvider
     */
    public function testToArray($input, $expectedOutput): void
    {
        $smartArray   = new SmartArray($input);
        $actualOutput = $smartArray->toArray();
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function toArrayProvider(): array
    {
        $testRecords               = TestHelpers::getTestRecords();
        $expectedTestRecordsOutput = $testRecords;
        foreach ($expectedTestRecordsOutput as $index => $record) { // Convert SmartString objects to their string values
            foreach ($record as $key => $value) {
                if ($value instanceof SmartString) {
                    $expectedTestRecordsOutput[$index][$key] = $value->value();
                }
            }
        }

        return [
            'empty array'                => [
                'input'          => [],
                'expectedOutput' => [],
            ],
            'flat array with primitives' => [
                'input'          => [1, 'string', true, null, 3.14],
                'expectedOutput' => [1, 'string', true, null, 3.14],
            ],
            'array with SmartString'     => [
                'input'          => [
                    'name' => 'John <b> Doe',
                    'age'  => 30,
                ],
                'expectedOutput' => [
                    'name' => 'John <b> Doe',
                    'age'  => 30,
                ],
            ],
            'nested SmartArray'          => [
                'input'          => [
                    'user'   => [
                        'name'  => 'Jane " Doe',
                        'email' => 'jane@example.com',
                    ],
                    'active' => true,
                ],
                'expectedOutput' => [
                    'user'   => [
                        'name'  => 'Jane " Doe',
                        'email' => 'jane@example.com',
                    ],
                    'active' => true,
                ],
            ],
            'deeply nested SmartArray'   => [
                'input'          => [
                    'level1' => [
                        'level2' => [
                            'level3' => [
                                'value' => '<deep> value',
                            ],
                        ],
                    ],
                ],
                'expectedOutput' => [
                    'level1' => [
                        'level2' => [
                            'level3' => [
                                'value' => '<deep> value',
                            ],
                        ],
                    ],
                ],
            ],
            'mixed types'                => [
                'input'          => [
                    'number'      => 42,
                    'string'      => 'hello',
                    'smartString' => '"world"',
                    'array'       => [1, 2, 3],
                    'smartArray'  => [4, 5, 6],
                ],
                'expectedOutput' => [
                    'number'      => 42,
                    'string'      => 'hello',
                    'smartString' => '"world"',
                    'array'       => [1, 2, 3],
                    'smartArray'  => [4, 5, 6],
                ],
            ],
            'using test records'         => [
                'input'          => $testRecords,
                'expectedOutput' => $expectedTestRecordsOutput,
            ],
        ];
    }

    /**
     * @dataProvider keysProvider
     */
    public function testKeys($input, $expected): void
    {
        $expectedSmartArray = new SmartArray($expected);
        $actualSmartArray   = (new SmartArray($input))->keys();
        $this->assertSame($expectedSmartArray->toArray(), $actualSmartArray->toArray());
    }

    public function keysProvider(): array
    {
        return [
            'empty array'                  => [
                'input'    => [],
                'expected' => [],
            ],
            'flat array with numeric keys' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'expected' => [0, 1, 2],
            ],
            'associative array'            => [
                'input'    => [
                    'first'  => 'apple',
                    'second' => 'banana',
                    'third'  => 'cherry',
                ],
                'expected' => ['first', 'second', 'third'],
            ],
            'nested array'                 => [
                'input'    => [
                    'fruits'     => ['apple', 'banana'],
                    'vegetables' => ['carrot', 'lettuce'],
                ],
                'expected' => ['fruits', 'vegetables'],
            ],
            'mixed keys'                   => [
                'input'    => [
                    0       => 'apple',
                    'one'   => 'banana',
                    2       => 'cherry',
                    'three' => 'date',
                ],
                'expected' => [0, 'one', 2, 'three'],
            ],
        ];
    }

    /**
     * @dataProvider valuesProvider
     */
    public function testValues($input, $expected): void
    {
        $expectedSmartArray = new SmartArray($expected);
        $actualSmartArray   = (new SmartArray($input))->values();
        $this->assertSame($expectedSmartArray->toArray(), $actualSmartArray->toArray());
    }

    public function valuesProvider(): array
    {
        return [
            'empty array'                => [
                'input'    => [],
                'expected' => [],
            ],
            'flat array with primitives' => [
                'input'    => [1, 'string', true, null, 3.14],
                'expected' => [1, 'string', true, null, 3.14],
            ],
            'associative array'          => [
                'input'    => [
                    'first'  => 'apple',
                    'second' => 'banana',
                    'third'  => 'cherry',
                ],
                'expected' => ['apple', 'banana', 'cherry'],
            ],
            'nested array'               => [
                'input'    => [
                    'fruits'     => ['apple', 'banana'],
                    'vegetables' => ['carrot', 'lettuce'],
                ],
                'expected' => [
                    ['apple', 'banana'],
                    ['carrot', 'lettuce'],
                ],
            ],
            'mixed keys'                 => [
                'input'    => [
                    0       => 'apple',
                    'one'   => 'banana',
                    2       => 'cherry',
                    'three' => 'date',
                ],
                'expected' => ['apple', 'banana', 'cherry', 'date'],
            ],
            'mixed data types'           => [
                'input'    => [
                    'name'    => 'John Doe',
                    'age'     => 30,
                    'emails'  => ['john@example.com', 'doe@example.com'],
                    'active'  => true,
                    'profile' => null,
                ],
                'expected' => [
                    'John Doe',
                    30,
                    ['john@example.com', 'doe@example.com'],
                    true,
                    null,
                ],
            ],
        ];
    }

    /**
     * @dataProvider indexByProvider
     */
    public function testIndexBy($input, $key, $expected): void
    {
        $smartArray = new SmartArray($input);
        $indexed    = $smartArray->indexBy($key);

        $expectedSmartArray = new SmartArray($expected);
        $this->assertSame($expectedSmartArray->toArray(), $indexed->toArray());
    }

    public function indexByProvider(): array
    {
        return [
            'unique keys'    => [
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
            'duplicate keys' => [
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
            'empty array'    => [
                'input'    => [],
                'key'      => 'id',
                'expected' => [],
            ],
            'mixed keys'     => [
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

    /**
     * @dataProvider groupByProvider
     */
    public function testGroupBy($input, $key, $expected): void
    {
        $smartArray = new SmartArray($input);
        $grouped    = $smartArray->groupBy($key);

        $expectedSmartArray = new SmartArray($expected);
        $this->assertSame($expectedSmartArray->toArray(), $grouped->toArray());
    }

    public function groupByProvider(): array
    {
        return [
            'unique group keys'    => [
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
            'empty array'          => [
                'input'    => [],
                'key'      => 'category',
                'expected' => [],
            ],
            'mixed group keys'     => [
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

    /**
     * @dataProvider pluckProvider
     */
    public function testPluck($input, $key, $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of the original array

        // Start output buffering to capture the warnings when requesting non-existing keys from non-empty arrays
        ob_start();
        $plucked = $smartArray->pluck($key);
        ob_end_clean();

        // compare
        $this->assertEquals($expected, $plucked->toArray(), "Plucked SmartArray does not match expected output.");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified.");
    }

    /**
     * @dataProvider pluckWithKeyColumnProvider
     */
    public function testPluckWithKeyColumn($input, $valueColumn, $keyColumn, $expected): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of the original array

        // Start output buffering to capture any warnings
        ob_start();
        $plucked = $smartArray->pluck($valueColumn, $keyColumn);
        ob_end_clean();

        // Compare results
        $this->assertEquals($expected, $plucked->toArray(), "Plucked SmartArray with key column does not match expected output.");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified.");
    }

    public function pluckWithKeyColumnProvider(): array
    {
        return [
            'pluck with key column from nested array' => [
                'input'    => [
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
            'pluck with string key column' => [
                'input'    => [
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
            'pluck with key column and non-scalar values' => [
                'input'    => [
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
            'pluck with key column (associative array)' => [
                'input'    => [
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
            'pluck with duplicate key column values' => [
                'input'    => [
                    ['group' => 'A', 'name' => 'Alice', 'score' => 90],
                    ['group' => 'B', 'name' => 'Bob', 'score' => 85],
                    ['group' => 'A', 'name' => 'Charlie', 'score' => 95], // Duplicate group 'A'
                ],
                'valueColumn' => 'score',
                'keyColumn'   => 'group',
                'expected'    => [
                    'A' => 95, // Charlie's score overwrites Alice's
                    'B' => 85,
                ],
            ],
            'pluck with empty array' => [
                'input'    => [],
                'valueColumn' => 'name',
                'keyColumn'   => 'id',
                'expected'    => [],
            ],
        ];
    }

    public function pluckProvider(): array
    {
        return [
            'pluck on empty array'                     => [
                'input'    => [],
                'key'      => 'id',
                'expected' => [],
            ],
            'pluck existing key from nested array'     => [
                'input'    => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'key'      => 'name',
                'expected' => ['Alice', 'Bob', 'Charlie'],
            ],
            'pluck non-existing key from nested array' => [
                'input'    => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'key'      => 'name',
                'expected' => ['Alice', 'Charlie'],
            ],
            'pluck key missing in all elements'        => [
                'input'    => [
                    ['id' => 1],
                    ['id' => 2],
                    ['id' => 3],
                ],
                'key'      => 'name',
                'expected' => [],
            ],
            'pluck with mixed types in nested array'   => [
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
            'pluck with numeric keys'                  => [
                'input'    => [
                    [0 => 'zero', 1 => 'one'],
                    [0 => 'zero', 1 => 'one'],
                    [0 => 'zero', 1 => 'one'],
                ],
                'key'      => 1,
                'expected' => ['one', 'one', 'one'],
            ],
            'pluck with key as integer string'         => [
                'input'    => [
                    ['0' => 'zero', '1' => 'one'],
                    ['0' => 'zero', '1' => 'one'],
                ],
                'key'      => '1',
                'expected' => ['one', 'one'],
            ],
        ];
    }

    public function testPluckOnNonNestedArrayThrowsException(): void
    {
        $smartArray    = new SmartArray(['id' => 1, 'name' => 'Alice']);
        $originalArray = $smartArray->toArray();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Expected a nested array, but got a flat array");

        $smartArray->pluck('name');

        // Ensure original SmartArray remains unchanged
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified.");
    }

    public function testPluckMissingKeyOutputsWarning(): void
    {
        $smartArray = new SmartArray([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Charlie'],
        ]);

        // Start output buffering to capture the warning output
        ob_start();
        $plucked = $smartArray->pluck('city');
        $output  = ob_get_clean();

        // Assert that the output contains the expected warning message
        $expectedWarningPattern = "/Warning: pluck\(\): 'city' doesn't exist/s";
        $this->assertMatchesRegularExpression($expectedWarningPattern, $output, "Expected warning message not found in output.");

        // Assert that the plucked SmartArray matches the expected output
        $expected = [];
        $this->assertEquals($expected, $plucked->toArray(), "Plucked SmartArray does not match expected output.");
    }

    public function testPluckMissingKeyWithWarnIfMissingDisabled(): void
    {
        $smartArray = new SmartArray([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Charlie'],
        ]);

        // Store original setting
        $originalWarnIfMissing = SmartArray::$warnIfMissing;

        try {
            // Disable warnings
            SmartArray::$warnIfMissing = false;

            // But pluck() should still show argument warnings regardless of setting
            ob_start();
            $plucked = $smartArray->pluck('city');
            $output = ob_get_clean();

            // Assert that the warning is still shown (method argument warnings always show)
            $expectedWarningPattern = "/Warning: pluck\(\): 'city' doesn't exist/s";
            $this->assertMatchesRegularExpression($expectedWarningPattern, $output,
                "Method argument warnings should still be shown for pluck() even with warnIfMissing disabled");

            // Assert that the plucked SmartArray matches the expected output
            $expected = [];
            $this->assertEquals($expected, $plucked->toArray(), "Plucked SmartArray should be empty when key doesn't exist");
        } finally {
            // Restore original setting
            SmartArray::$warnIfMissing = $originalWarnIfMissing;
        }
    }

    /**
     * @dataProvider pluckNthProvider
     */
    public function testPluckNth($input, $index, $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        // Start output buffering to capture any warnings
        ob_start();
        $result = $smartArray->pluckNth($index);
        ob_end_clean();

        // Verify the plucked values match expected output
        $this->assertEquals($expected, $result->toArray(), "Plucked values do not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    /** @noinspection SqlNoDataSourceInspection */
    public function pluckNthProvider(): array
    {
        return [
            'empty array'                                      => [
                'input'    => [],
                'index'    => 0,
                'expected' => [],
            ],
            'first position (0) from flat values'              => [
                'input'    => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => 0,
                'expected' => ['John', 'Jane', 'Bob'],
            ],
            'middle position (1) from flat values'             => [
                'input'    => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => 1,
                'expected' => ['Doe', 'Smith', 'Brown'],
            ],
            'last position (2) from flat values'               => [
                'input'    => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => 2,
                'expected' => ['Developer', 'Designer', 'Manager'],
            ],
            'negative index (-1) gets last element'            => [
                'input'    => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => -1,
                'expected' => ['Developer', 'Designer', 'Manager'],
            ],
            'negative index (-2) gets second to last element'  => [
                'input'    => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => -2,
                'expected' => ['Doe', 'Smith', 'Brown'],
            ],
            'index beyond array bounds returns empty array'    => [
                'input'    => [
                    ['John', 'Doe'],
                    ['Jane', 'Smith'],
                ],
                'index'    => 5,
                'expected' => [],
            ],
            'negative index beyond bounds returns empty array' => [
                'input'    => [
                    ['John', 'Doe'],
                    ['Jane', 'Smith'],
                ],
                'index'    => -5,
                'expected' => [],
            ],
            'rows with different lengths'                      => [
                'input'    => [
                    ['John', 'Doe', 'Developer', 'Team A'],
                    ['Jane', 'Smith'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => 2,
                'expected' => ['Developer', 'Manager'], // Skip row without index 2
            ],
            'single column result'                             => [
                'input'    => [
                    ['SHOW TABLES'],
                    ['DESCRIBE table'],
                    ['SELECT * FROM table'],
                ],
                'index'    => 0,
                'expected' => ['SHOW TABLES', 'DESCRIBE table', 'SELECT * FROM table'],
            ],
            'mixed value types'                                => [
                'input'    => [
                    [1, 'active', true],
                    [2, 'inactive', false],
                    [3, 'pending', null],
                ],
                'index'    => 1,
                'expected' => ['active', 'inactive', 'pending'],
            ],
            'MySQL SHOW TABLES simulation'                     => [
                'input'    => [
                    ['Tables_in_database' => 'users'],
                    ['Tables_in_database' => 'posts'],
                    ['Tables_in_database' => 'comments'],
                ],
                'index'    => 0,
                'expected' => ['users', 'posts', 'comments'],
            ],
            'nested objects at position'                       => [
                'input'    => [
                    ['id' => 1, 'meta' => ['type' => 'user']],
                    ['id' => 2, 'meta' => ['type' => 'admin']],
                    ['id' => 3, 'meta' => ['type' => 'guest']],
                ],
                'index'    => 1,
                'expected' => [
                    ['type' => 'user'],
                    ['type' => 'admin'],
                    ['type' => 'guest'],
                ],
            ],
            'associative arrays with numeric position'         => [
                'input'    => [
                    ['first' => 'John', 'last' => 'Doe', 'role' => 'admin'],
                    ['first' => 'Jane', 'last' => 'Smith', 'role' => 'user'],
                ],
                'index'    => 0,
                'expected' => ['John', 'Jane'],
            ],
            'empty rows are skipped'                           => [
                'input'    => [
                    ['John', 'Doe'],
                    [],
                    ['Jane', 'Smith'],
                ],
                'index'    => 0,
                'expected' => ['John', 'Jane'],
            ],
        ];
    }

    /**
     * @dataProvider columnProvider
     */
    public function testColumn($input, $columnKey, $indexKey, $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        $result = $smartArray->column($columnKey, $indexKey);

        $this->assertEquals($expected, $result->toArray(), "column() result does not match expected output");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified");
    }

    public function columnProvider(): array
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
            'extract column indexed by another column' => [
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

    /**
     * @dataProvider implodeProvider
     */
    public function testImplode($input, $separator, $expected, $shouldThrowException = false): void
    {
        $smartArray = new SmartArray($input);

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a flat array, but got a nested array");
            /** @noinspection UnusedFunctionResultInspection */
            $smartArray->implode($separator);
            return;
        }

        $actual = $smartArray->implode($separator);
        $this->assertSame($expected, $actual);
    }

    public function implodeProvider(): array
    {
        return [
            'empty array'                                   => [
                'input'                => [],
                'separator'            => ', ',
                'expected'             => '',
                'shouldThrowException' => false,
            ],
            'flat array with primitives'                    => [
                'input'                => ['apple', 'banana', 'cherry'],
                'separator'            => ', ',
                'expected'             => 'apple, banana, cherry',
                'shouldThrowException' => false,
            ],
            'implode with comma separator'                  => [
                'input'                => ['apple', 'banana', 'cherry'],
                'separator'            => ', ',
                'expected'             => 'apple, banana, cherry',
                'shouldThrowException' => false,
            ],
            'implode with space separator'                  => [
                'input'                => ['apple', 'banana', 'cherry'],
                'separator'            => ' ',
                'expected'             => 'apple banana cherry',
                'shouldThrowException' => false,
            ],
            'implode with hyphen separator'                 => [
                'input'                => ['apple', 'banana', 'cherry'],
                'separator'            => ' - ',
                'expected'             => 'apple - banana - cherry',
                'shouldThrowException' => false,
            ],
            'implode with special characters'               => [
                'input'                => ['He said "Hello"', "It's a test", 'Line1\nLine2', 'Comma, separated'],
                'separator'            => '; ',
                'expected'             => 'He said "Hello"; It\'s a test; Line1\nLine2; Comma, separated',
                'shouldThrowException' => false,
            ],
            'implode single element array'                  => [
                'input'                => ['onlyOne'],
                'separator'            => ', ',
                'expected'             => 'onlyOne',
                'shouldThrowException' => false,
            ],
            'implode with numeric elements'                 => [
                'input'                => [100, 200.5, 300],
                'separator'            => '-',
                'expected'             => '100-200.5-300',
                'shouldThrowException' => false,
            ],
            'implode nested array (should throw exception)' => [
                'input'                => [
                    ['apple', 'banana'],
                    ['cherry', 'date'],
                ],
                'separator'            => ', ',
                'expected'             => '', // Irrelevant since exception is expected
                'shouldThrowException' => true,
            ],
        ];
    }

    /**
     * @dataProvider mapProvider
     */
    public function testMap($input, $callback, $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of the original array

        $mapped = $smartArray->map($callback);

        $this->assertEquals($expected, $mapped->toArray(), "Mapped SmartArray does not match expected output.");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified.");
    }

    /**
     * @noinspection SpellCheckingInspection // ignore test strings
     */
    public function mapProvider(): array
    {
        return [
            'map empty array with identity callback'                    => [
                'input'    => [],
                'callback' => fn($value) => $value,
                'expected' => [],
            ],
            'map flat array with primitive values to uppercase strings' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'callback' => fn($value) => strtoupper($value),
                'expected' => ['APPLE', 'BANANA', 'CHERRY'],
            ],
            'map flat array with mixed data types'                      => [
                'input'    => [1, 'two', true, null, 5.5],
                'callback' => fn($value) => is_string($value) ? $value . ' mapped' : $value,
                'expected' => [1, 'two mapped', true, null, 5.5],
            ],
            'map keyed array'                                           => [
                'input'    => ['name' => 'Alice', 'age' => 30],
                'callback' => fn($value) => "<td>$value</td>",
                'expected' => ['name' => '<td>Alice</td>', 'age' => '<td>30</td>'],
            ],
            'map nested array adding ageGroup'                          => [
                'input'    => [
                    ['name' => 'Alice', 'age' => 30],
                    ['name' => 'Bob', 'age' => 25],
                    ['name' => 'Charlie', 'age' => 35],
                ],
                'callback' => fn($record) => array_merge($record, ['ageGroup' => $record['age'] >= 30 ? 'Adult' : 'Young']),
                'expected' => [
                    ['name' => 'Alice', 'age' => 30, 'ageGroup' => 'Adult'],
                    ['name' => 'Bob', 'age' => 25, 'ageGroup' => 'Young'],
                    ['name' => 'Charlie', 'age' => 35, 'ageGroup' => 'Adult'],
                ],
            ],
            'map with callback that returns different data types'       => [
                'input'    => ['apple', 'banana', 'cherry'],
                'callback' => fn($value) => strrev($value),
                'expected' => ['elppa', 'ananab', 'yrrehc'],
            ],
            'map with null elements'                                    => [
                'input'    => [null, 'hello', null],
                'callback' => fn($value) => $value ?? 'default',
                'expected' => ['default', 'hello', 'default'],
            ],
        ];
    }

    /**
     * @dataProvider smartMapProvider
     */
    public function testSmartMap($input, $callback, $expected, $useSmartStrings = true): void
    {
        $smartArray = new SmartArray($input);

        if ($useSmartStrings) {
            $smartArray = $smartArray->enableSmartStrings();
        }

        $originalArray = $smartArray->toArray(); // Copy of the original array
        $mapped = $smartArray->smartMap($callback);

        // Check the result matches expected output
        $this->assertEquals($expected, $mapped->toArray(), "SmartMap result doesn't match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified");

        // Verify returned object is a SmartArray
        $this->assertInstanceOf(SmartArray::class, $mapped, "smartMap() should return a SmartArray");
    }

    public function smartMapProvider(): array
    {
        return [
            'empty array' => [
                'input'          => [],
                'callback'       => function ($value, $key) {
                    return $value;
                },
                'expected'       => [],
            ],
            'flat array with SS string operation' => [
                'input'          => ['hello', 'world'],
                'callback'       => function ($value, $key) {
                    return strtoupper($value->value()); // Use PHP's strtoupper on the raw value
                },
                'expected'       => ['HELLO', 'WORLD'],
            ],
            'flat array with keys in transform' => [
                'input'          => ['a' => 'hello', 'b' => 'world'],
                'callback'       => function ($value, $key) {
                    return "$key: $value";
                },
                'expected'       => ['a' => 'a: hello', 'b' => 'b: world'],
            ],
            'mixed types with smart transformations' => [
                'input'          => ['text' => 'hello', 'num' => 42, 'bool' => true],
                'callback'       => function ($value, $key) {
                    if ($value instanceof SmartString && is_string($value->value())) {
                        return strtoupper($value->value());
                    }
                    return "Value: $value";
                },
                'expected'       => ['text' => 'HELLO', 'num' => 'Value: 42', 'bool' => 'Value: 1'],
            ],
            'nested array access' => [
                'input'          => [
                    ['name' => 'John', 'email' => 'john@example.com'],
                    ['name' => 'Jane', 'email' => 'jane@example.com'],
                ],
                'callback'       => function ($row, $key) {
                    return strtoupper($row->name->value()) . ' - ' . $row->email;
                },
                'expected'       => ['JOHN - john@example.com', 'JANE - jane@example.com'],
            ],
            'HTML handling' => [
                'input'          => ['<p>Hello</p>', '<div>World</div>'],
                'callback'       => function ($value, $key) {
                    return $value->value(); // Access raw HTML
                },
                'expected'       => ['<p>Hello</p>', '<div>World</div>'],
                'useSmartStrings' => true,
            ],
            'without SmartStrings enabled' => [
                'input'          => ['hello', 'world'],
                'callback'       => function ($value, $key) {
                    // In this case $value is not a SmartString, should throw error if we try to call ->upper()
                    return strtoupper($value);
                },
                'expected'       => ['HELLO', 'WORLD'],
                'useSmartStrings' => false,
            ],
        ];
    }

    /**
     * @dataProvider eachProvider
     */
    public function testEach($input, $expectedResults): void
    {
        $smartArray = new SmartArray($input);
        $smartArray = $smartArray->enableSmartStrings();

        $results = [];
        $returnValue = $smartArray->each(function ($value, $key) use (&$results) {
            // Store the results to verify later
            $results[$key] = [
                'key' => $key,
                'isSmartString' => $value instanceof SmartString,
                'isSmartArray' => $value instanceof SmartArray,
                'value' => $value instanceof SmartString ? $value->value() : ($value instanceof SmartArray ? 'SmartArray' : $value),
            ];
        });

        // Verify each() returns the original SmartArray for chaining
        $this->assertSame($smartArray, $returnValue, "each() should return the original SmartArray for chaining");

        // Verify results match expectations
        $this->assertEquals($expectedResults, $results, "each() callback should receive SmartString/SmartArray objects");
    }

    public function eachProvider(): array
    {
        return [
            'empty array' => [
                'input' => [],
                'expectedResults' => [],
            ],
            'flat array with strings' => [
                'input' => ['hello', 'world'],
                'expectedResults' => [
                    0 => [
                        'key' => 0,
                        'isSmartString' => true,
                        'isSmartArray' => false,
                        'value' => 'hello',
                    ],
                    1 => [
                        'key' => 1,
                        'isSmartString' => true,
                        'isSmartArray' => false,
                        'value' => 'world',
                    ],
                ],
            ],
            'mixed types' => [
                'input' => ['text' => 'hello', 'num' => 42, 'bool' => true],
                'expectedResults' => [
                    'text' => [
                        'key' => 'text',
                        'isSmartString' => true,
                        'isSmartArray' => false,
                        'value' => 'hello',
                    ],
                    'num' => [
                        'key' => 'num',
                        'isSmartString' => true,
                        'isSmartArray' => false,
                        'value' => 42,
                    ],
                    'bool' => [
                        'key' => 'bool',
                        'isSmartString' => true,
                        'isSmartArray' => false,
                        'value' => true,
                    ],
                ],
            ],
            'nested array' => [
                'input' => [
                    'user' => ['name' => 'John', 'age' => 30],
                ],
                'expectedResults' => [
                    'user' => [
                        'key' => 'user',
                        'isSmartString' => false,
                        'isSmartArray' => true,
                        'value' => 'SmartArray',
                    ],
                ],
            ],
        ];
    }

//endregion
}
