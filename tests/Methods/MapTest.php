<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::map() method.
 *
 * map($callback) transforms each element using a callback.
 * Callback receives raw values (not SmartStrings).
 * Returns a new SmartArray (immutable).
 */
class MapTest extends SmartArrayTestCase
{

    /**
     * @dataProvider mapProvider
     */
    public function testMap(array $input, callable $callback, array $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        $mapped = $smartArray->map($callback);

        $this->assertEquals($expected, $mapped->toArray(), "Mapped SmartArray does not match expected output.");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified.");
    }

    /**
     * @noinspection SpellCheckingInspection
     */
    public static function mapProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'callback' => fn($value) => $value,
                'expected' => [],
            ],
            'uppercase strings' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'callback' => fn($value) => strtoupper($value),
                'expected' => ['APPLE', 'BANANA', 'CHERRY'],
            ],
            'mixed data types' => [
                'input'    => [1, 'two', true, null, 5.5],
                'callback' => fn($value) => is_string($value) ? $value . ' mapped' : $value,
                'expected' => [1, 'two mapped', true, null, 5.5],
            ],
            'keyed array' => [
                'input'    => ['name' => 'Alice', 'age' => 30],
                'callback' => fn($value) => "<td>$value</td>",
                'expected' => ['name' => '<td>Alice</td>', 'age' => '<td>30</td>'],
            ],
            'nested array transformation' => [
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
            'string reversal' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'callback' => fn($value) => strrev($value),
                'expected' => ['elppa', 'ananab', 'yrrehc'],
            ],
            'null coalescing' => [
                'input'    => [null, 'hello', null],
                'callback' => fn($value) => $value ?? 'default',
                'expected' => ['default', 'hello', 'default'],
            ],
        ];
    }

}
