<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::pluckNth() method.
 *
 * pluckNth($index) extracts the nth value (by position) from each row.
 * Useful for extracting single-column results like MySQL "SHOW TABLES".
 * Supports negative indices (-1 for last element).
 */
class PluckNthTest extends SmartArrayTestCase
{

    /**
     * @dataProvider pluckNthProvider
     */
    public function testPluckNth(array $input, int $index, array $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        ob_start();
        $result = $smartArray->pluckNth($index);
        ob_end_clean();

        $this->assertEquals($expected, $result->toArray(), "Plucked values do not match expected output");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    /** @noinspection SqlNoDataSourceInspection */
    public static function pluckNthProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'index'    => 0,
                'expected' => [],
            ],
            'first position (0)' => [
                'input'    => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => 0,
                'expected' => ['John', 'Jane', 'Bob'],
            ],
            'middle position (1)' => [
                'input'    => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => 1,
                'expected' => ['Doe', 'Smith', 'Brown'],
            ],
            'last position (2)' => [
                'input'    => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => 2,
                'expected' => ['Developer', 'Designer', 'Manager'],
            ],
            'negative index (-1)' => [
                'input'    => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => -1,
                'expected' => ['Developer', 'Designer', 'Manager'],
            ],
            'negative index (-2)' => [
                'input'    => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => -2,
                'expected' => ['Doe', 'Smith', 'Brown'],
            ],
            'index out of bounds (positive)' => [
                'input'    => [
                    ['John', 'Doe'],
                    ['Jane', 'Smith'],
                ],
                'index'    => 5,
                'expected' => [],
            ],
            'index out of bounds (negative)' => [
                'input'    => [
                    ['John', 'Doe'],
                    ['Jane', 'Smith'],
                ],
                'index'    => -5,
                'expected' => [],
            ],
            'rows with different lengths' => [
                'input'    => [
                    ['John', 'Doe', 'Developer', 'Team A'],
                    ['Jane', 'Smith'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index'    => 2,
                'expected' => ['Developer', 'Manager'],
            ],
            'single column simulation' => [
                'input'    => [
                    ['SHOW TABLES'],
                    ['DESCRIBE table'],
                    ['SELECT * FROM table'],
                ],
                'index'    => 0,
                'expected' => ['SHOW TABLES', 'DESCRIBE table', 'SELECT * FROM table'],
            ],
            'mixed value types' => [
                'input'    => [
                    [1, 'active', true],
                    [2, 'inactive', false],
                    [3, 'pending', null],
                ],
                'index'    => 1,
                'expected' => ['active', 'inactive', 'pending'],
            ],
            'MySQL SHOW TABLES simulation' => [
                'input'    => [
                    ['Tables_in_database' => 'users'],
                    ['Tables_in_database' => 'posts'],
                    ['Tables_in_database' => 'comments'],
                ],
                'index'    => 0,
                'expected' => ['users', 'posts', 'comments'],
            ],
            'nested objects at position' => [
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
            'associative arrays with numeric position' => [
                'input'    => [
                    ['first' => 'John', 'last' => 'Doe', 'role' => 'admin'],
                    ['first' => 'Jane', 'last' => 'Smith', 'role' => 'user'],
                ],
                'index'    => 0,
                'expected' => ['John', 'Jane'],
            ],
            'empty rows are skipped' => [
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

}
