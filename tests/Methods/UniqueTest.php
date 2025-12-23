<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::unique() method.
 *
 * unique() removes duplicate values from a flat array, preserving keys.
 * Returns a new SmartArray (immutable). Throws InvalidArgumentException for nested arrays.
 */
class UniqueTest extends SmartArrayTestCase
{

    /**
     * @dataProvider uniqueProvider
     */
    public function testUnique(array $input, array $expected, bool $shouldThrowException = false): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a flat array, but got a nested array");
            $smartArray->unique();
            return;
        }

        $unique = $smartArray->unique();

        // Verify unique worked correctly
        $this->assertEquals($expected, $unique->toArray(), "Array with duplicates removed does not match expected output");

        // Verify original array wasn't modified (immutable)
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public static function uniqueProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'expected' => [],
            ],
            'numeric duplicates' => [
                'input'    => [1, 2, 2, 3, 3, 3, 4],
                'expected' => [0 => 1, 1 => 2, 3 => 3, 6 => 4],
            ],
            'string duplicates' => [
                'input'    => ['apple', 'banana', 'apple', 'cherry', 'banana'],
                'expected' => [0 => 'apple', 1 => 'banana', 3 => 'cherry'],
            ],
            'mixed type duplicates' => [
                'input'    => [1, '1', '2', 2, true, 1, '1', false],
                'expected' => [0 => 1, 2 => '2', 7 => false],
            ],
            'null values' => [
                'input'    => [null, 1, null, 2, null],
                'expected' => [0 => null, 1 => 1, 3 => 2],
            ],
            'preserves keys' => [
                'input'    => ['a' => 1, 'b' => 2, 'c' => 2, 'd' => 1],
                'expected' => ['a' => 1, 'b' => 2],
            ],
            'nested array throws exception' => [
                'input'                => [[1, 2], [1, 2], [3, 4]],
                'expected'             => [],
                'shouldThrowException' => true,
            ],
        ];
    }

}
