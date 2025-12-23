<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartString\SmartString;

/**
 * Tests for SmartArray::contains() method.
 *
 * contains($value) checks if the array contains a specific value.
 * Uses loose comparison (==) for database/form data tolerance.
 */
class ContainsTest extends SmartArrayTestCase
{

    /**
     * @dataProvider containsProvider
     */
    public function testContains(array $input, mixed $value, bool $expected): void
    {
        foreach ([new SmartArray($input), new SmartArrayHtml($input)] as $smartArray) {
            $result = $smartArray->contains($value);
            $this->assertSame($expected, $result, "Failed asserting contains() for " . $smartArray::class);
        }
    }

    public static function containsProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'value'    => 'anything',
                'expected' => false,
            ],
            'flat array with existing value' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'value'    => 'banana',
                'expected' => true,
            ],
            'flat array with non-existing value' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'value'    => 'orange',
                'expected' => false,
            ],
            'numeric array with existing value' => [
                'input'    => [1, 2, 3, 4, 5],
                'value'    => 3,
                'expected' => true,
            ],
            'numeric array with non-existing value' => [
                'input'    => [1, 2, 3, 4, 5],
                'value'    => 6,
                'expected' => false,
            ],
            'mixed array with existing value' => [
                'input'    => [1, 'two', true, null, 5.5],
                'value'    => true,
                'expected' => true,
            ],
            'type-juggled match (loose comparison)' => [
                'input'    => [1, 'two', true, null, 5.5],
                'value'    => '1', // String '1' matches integer 1 in loose comparison
                'expected' => true,
            ],
            'SmartString input' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'value'    => new SmartString('banana'),
                'expected' => true,
            ],
            'associative array with existing value' => [
                'input'    => ['a' => 'apple', 'b' => 'banana', 'c' => 'cherry'],
                'value'    => 'cherry',
                'expected' => true,
            ],
            'associative array - keys not searched' => [
                'input'    => ['a' => 'apple', 'b' => 'banana', 'c' => 'cherry'],
                'value'    => 'a',
                'expected' => false, // Keys are not searched, only values
            ],
            'array with null value' => [
                'input'    => ['apple', null, 'cherry'],
                'value'    => null,
                'expected' => true,
            ],
        ];
    }

}
