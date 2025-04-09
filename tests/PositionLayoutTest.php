<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Itools\SmartArray\SmartArray;
use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\TestHelpers;

class PositionLayoutTest extends TestCase
{
//region Position & Layout

    /**
     * This test manually loops through the elements of the SmartArray and checks the isFirst() and isLast() methods match the expected values (if they exist).
     *
     * @dataProvider isFirstAndIsLastProvider
     */
    public function testIsFirstAndIsLast($input): void
    {
        $smartArray = new SmartArray($input);

        // Check isFirst() and isLast() for each element
        $smartArrayData = $smartArray->getArrayCopy();
        $assertionMade  = false;
        foreach ($smartArray as $key => $value) {
            // skip if the element is not a nested SmartArray
            if (!$value instanceof SmartArray) {
                continue;
            }

            // Determine if the element is the first or last element
            $isFirstExpected = $key === array_key_first($smartArrayData);
            $isLastExpected  = $key === array_key_last($smartArrayData);
            $this->assertSame($isFirstExpected, $value->isFirst(), "Element at position $key: 'isFirst()' mismatch.");
            $this->assertSame($isLastExpected, $value->isLast(), "Element at position $key: 'isLast()' mismatch." . print_r($value, true));

            $assertionMade = true;
        }

        // If no assertions were made in the loop, make an assertion here to avoid "This test did not perform any assertions" warning
        if (!$assertionMade) {
            $this->assertTrue(true, "No elements with isFirst/isLast methods to test.");
        }
    }

    public function isFirstAndIsLastProvider(): array
    {
        return [
            'empty array'                             => [
                [],
            ],
            'single element array'                    => [
                [TestHelpers::getTestRecord()],
            ],
            'multiple elements array'                 => [
                TestHelpers::getTestRecords(),
            ],
            'non-sequential integer keys'             => [
                [
                    20 => ['data' => 'second'],
                    10 => ['data' => 'first'],
                    30 => ['data' => 'third'],
                ],
            ],
            'associative array with string keys'      => [
                [
                    'first'  => ['data' => 'first'],
                    'middle' => ['data' => 'middle'],
                    'last'   => ['data' => 'last'],
                ],
            ],
            'mixed elements'                          => [
                [
                    'a' => ['data' => 'first'],
                    'b' => 'string value',
                    'c' => 123,
                    'd' => null,
                    'e' => ['data' => 'last'],
                ],
            ],
            'mixed elements 2'                        => [
                [
                    'a' => ['data' => 'first'],
                    'b' => 'string value',
                    'c' => 123,
                    'e' => ['data' => 'last'],
                    'd' => null,
                ],
            ],
            'elements without isFirst/isLast methods' => [
                [
                    'x' => 'string value',
                    'y' => 123,
                    'z' => null,
                ],
            ],
            'nested arrays'                           => [
                [
                    'nested' => TestHelpers::getTestRecords(),
                    'single' => [TestHelpers::getTestRecord()],
                ],
            ],
        ];
    }

    /**
     * @dataProvider positionProvider
     */
    public function testPosition($initialData, $expectedPositions): void
    {
        // Set up the initial data
        $array = new SmartArray($initialData);

        $actualPositions = [];
        foreach ($array as $element) {
            if ($element instanceof SmartArray) {
                $actualPositions[] = $element->position();
            }
        }

        // Assert that the actual positions match the expected positions
        $this->assertEquals($expectedPositions, $actualPositions);
    }

    public function positionProvider(): array
    {
        return [
            'Nested SmartArrays'                 => [
                'initialData'       => [
                    'first'  => ['id' => 1, 'name' => 'Alice'],
                    'second' => ['id' => 2, 'name' => 'Bob'],
                    'third'  => ['id' => 3, 'name' => 'Charlie'],
                ],
                'expectedPositions' => [1, 2, 3],
            ],
            'Only some elements are SmartArrays' => [
                'initialData'       => [
                    'group1' => ['member1' => 'Alice', 'member2' => 'Bob'],
                    'group2' => 'Not a SmartArray',
                    'group3' => ['member3' => 'Charlie'],
                ],
                'expectedPositions' => [1, 3],
            ],
            'Empty array'                        => [
                'initialData'       => [],
                'expectedPositions' => [],
            ],
            'Single nested SmartArray'           => [
                'initialData'       => [
                    'only' => ['id' => 1, 'name' => 'Single'],
                ],
                'expectedPositions' => [1],
            ],
            'Mixed element types'                => [
                'initialData'       => [
                    'nestedArray'  => ['key' => 'value'],
                    'stringValue'  => 'Just a string',
                    'intValue'     => 42,
                    'nullValue'    => null,
                    'anotherArray' => ['foo' => 'bar'],
                ],
                'expectedPositions' => [1, 5],
            ],
        ];
    }

    /**
     * @dataProvider isMultipleOfProvider
     */
    public function testIsMultipleOf($initialData, $number, $expectedResults): void
    {
        // Set up the initial data
        $array = new SmartArray($initialData);

        $actualResults = [];

        // Iterate over the elements in the SmartArray
        foreach ($array as $element) {
            // Check if the element is a SmartArray
            if ($element instanceof SmartArray) {
                // Get whether the element's position is a multiple of the given number
                $isMultiple      = $element->isMultipleOf($number);
                $actualResults[] = $isMultiple;
            } else {
                $actualResults[] = null; // for testing show ignored elements as null for easier comparison
            }
        }

        // Assert that the actual results match the expected results
        $this->assertEquals($expectedResults, $actualResults);
    }

    public function isMultipleOfProvider(): array
    {
        return [
            'Nested SmartArrays with number 2'       => [
                'initialData'     => [
                    ['id' => 1, 'name' => 'Alice'],   // Position 1
                    ['id' => 2, 'name' => 'Bob'],     // Position 2
                    ['id' => 3, 'name' => 'Charlie'], // Position 3
                    ['id' => 4, 'name' => 'Dave'],    // Position 4
                ],
                'number'          => 2,
                'expectedResults' => [false, true, false, true],
            ],
            'Mixed elements with number 3'           => [
                'initialData'     => [
                    ['item' => 'A'],      // Position 1
                    'Not a SmartArray',   // Position 2 (ignored)
                    ['item' => 'B'],      // Position 3
                    ['item' => 'C'],      // Position 4
                    ['item' => 'D'],      // Position 5
                    ['item' => 'E'],      // Position 6
                    ['item' => 'F'],      // Position 7
                    ['item' => 'G'],      // Position 8
                ],
                'number'          => 3,
                'expectedResults' => [false, null, true, false, false, true, false, false],
            ],
            'Empty array'                            => [
                'initialData'     => [],
                'number'          => 2,
                'expectedResults' => [],
            ],
            'Single nested SmartArray with number 1' => [
                'initialData'     => [
                    ['id' => 1, 'name' => 'Single'], // Position 1
                ],
                'number'          => 1,
                'expectedResults' => [true],
            ],
            'Non-sequential positions with number 2' => [
                'initialData'     => [
                    ['group' => 'A'],  // Position 1
                    ['group' => 'B'],  // Position 2
                    ['group' => 'C'],  // Position 3
                ],
                'number'          => 2,
                'expectedResults' => [false, true, false],
            ],
        ];
    }

    /**
     * @dataProvider chunkProvider
     */
    public function testChunk($input, $size, $expected, $shouldThrowException = false, $expectedExceptionMessage = ''): void
    {
        $smartArray = new SmartArray($input);

        // test for exceptions
        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
            $smartArray->chunk($size);
            return;
        }

        // get actual output
        $actual = $smartArray->chunk($size)->toArray();

        // compare
        $this->assertEquals($expected, $actual, "Chunked SmartArray does not match expected output.");
    }

    public function chunkProvider(): array
    {
        return [
            'empty array'                  => [
                'input'                => [],
                'size'                 => 3,
                'expected'             => [],
                'shouldThrowException' => false,
            ],
            'size greater than array size' => [
                'input'                => [1, 2, 3, 4, 5],
                'size'                 => 10,
                'expected'             => [[1, 2, 3, 4, 5]],
                'shouldThrowException' => false,
            ],
            'size less than array size'    => [
                'input'                => [1, 2, 3, 4, 5],
                'size'                 => 2,
                'expected'             => [[1, 2], [3, 4], [5]],
                'shouldThrowException' => false,
            ],
            'size equal to array size'     => [
                'input'                => [1, 2, 3, 4, 5],
                'size'                 => 5,
                'expected'             => [[1, 2, 3, 4, 5]],
                'shouldThrowException' => false,
            ],
            'size is one'                  => [
                'input'                => [1, 2, 3, 4, 5],
                'size'                 => 1,
                'expected'             => [[1], [2], [3], [4], [5]],
                'shouldThrowException' => false,
            ],
            'negative size'                => [
                'input'                    => [1, 2, 3],
                'size'                     => -2,
                'expected'                 => [],
                'shouldThrowException'     => true,
                'expectedExceptionMessage' => "Chunk size must be greater than 0.",
            ],
            'zero size'                    => [
                'input'                    => [1, 2, 3],
                'size'                     => 0,
                'expected'                 => [],
                'shouldThrowException'     => true,
                'expectedExceptionMessage' => "Chunk size must be greater than 0.",
            ],
            'nested arrays'                => [
                'input'                => [[1, 2], [3, 4], [5, 6]],
                'size'                 => 2,
                'expected'             => [
                    [[1, 2], [3, 4]],
                    [[5, 6]],
                ],
                'shouldThrowException' => false,
            ],
            'non-integer elements'         => [
                'input'                => ['a', 'b', 'c', 'd', 'e'],
                'size'                 => 2,
                'expected'             => [['a', 'b'], ['c', 'd'], ['e']],
                'shouldThrowException' => false,
            ],
            'large size'                   => [
                'input'                => range(1, 100),
                'size'                 => 15,
                'expected'             => array_chunk(range(1, 100), 15),
                'shouldThrowException' => false,
            ],
            'user array chunks'            => [
                'input'                => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                    ['id' => 4, 'name' => 'David'],
                    ['id' => 5, 'name' => 'Eve'],
                ],
                'size'                 => 2,
                'expected'             => [
                    [
                        ['id' => 1, 'name' => 'Alice'],
                        ['id' => 2, 'name' => 'Bob'],
                    ],
                    [
                        ['id' => 3, 'name' => 'Charlie'],
                        ['id' => 4, 'name' => 'David'],
                    ],
                    [
                        ['id' => 5, 'name' => 'Eve'],
                    ],
                ],
                'shouldThrowException' => false,
            ],
        ];
    }

//endregion
}
