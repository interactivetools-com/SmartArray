<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::isMultipleOf() method.
 *
 * isMultipleOf($n) returns true when the element's position is a multiple of $n.
 * Useful for alternating row styles, inserting breaks every N items, etc.
 */
class IsMultipleOfTest extends SmartArrayTestCase
{

    /**
     * @dataProvider isMultipleOfProvider
     */
    public function testIsMultipleOf(array $initialData, int $number, array $expectedResults): void
    {
        $array         = new SmartArray($initialData);
        $actualResults = [];

        foreach ($array as $element) {
            if ($element instanceof SmartArray) {
                $actualResults[] = $element->isMultipleOf($number);
            }
            else {
                $actualResults[] = null; // Show ignored elements as null for easier comparison
            }
        }

        $this->assertEquals($expectedResults, $actualResults);
    }

    public static function isMultipleOfProvider(): array
    {
        return [
            'nested SmartArrays with number 2' => [
                'initialData'     => [
                    ['id' => 1, 'name' => 'Alice'],   // Position 1
                    ['id' => 2, 'name' => 'Bob'],     // Position 2
                    ['id' => 3, 'name' => 'Charlie'], // Position 3
                    ['id' => 4, 'name' => 'Dave'],    // Position 4
                ],
                'number'          => 2,
                'expectedResults' => [false, true, false, true],
            ],
            'mixed elements with number 3' => [
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
            'empty array' => [
                'initialData'     => [],
                'number'          => 2,
                'expectedResults' => [],
            ],
            'single nested SmartArray with number 1' => [
                'initialData'     => [
                    ['id' => 1, 'name' => 'Single'], // Position 1
                ],
                'number'          => 1,
                'expectedResults' => [true],
            ],
            'non-sequential positions with number 2' => [
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
     * Test that isMultipleOf() throws on zero
     */
    public function testIsMultipleOfThrowsOnZero(): void
    {
        $array = new SmartArray([['id' => 1]]);
        $first = $array->first();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("must be greater than 0");

        $first->isMultipleOf(0);
    }

    /**
     * Test that isMultipleOf() throws on negative number
     */
    public function testIsMultipleOfThrowsOnNegative(): void
    {
        $array = new SmartArray([['id' => 1]]);
        $first = $array->first();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("must be greater than 0");

        $first->isMultipleOf(-5);
    }

    /**
     * Test isMultipleOf() when position is 0 (standalone array not in a parent)
     *
     * Note: position 0 % n === 0 is always true mathematically, so this returns true
     * when the position is 0. This is correct but may be surprising - a standalone
     * array is considered a "multiple" of any number since 0 % n = 0.
     */
    public function testIsMultipleOfWhenPositionIsZero(): void
    {
        // A standalone SmartArray has position = 0
        $standalone = new SmartArray(['id' => 1, 'name' => 'Test']);

        // 0 % n === 0, so mathematically this is true
        $this->assertTrue($standalone->isMultipleOf(2));
        $this->assertTrue($standalone->isMultipleOf(1));

        // Verify position is indeed 0
        $this->assertSame(0, $standalone->position());
    }

}
