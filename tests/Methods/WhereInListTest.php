<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartString\SmartString;

/**
 * Tests for SmartArray::whereInList() method.
 *
 * whereInList($field, $value) filters a nested array where rows contain a
 * discrete value within a tab-delimited field (CMS Builder checkbox groups).
 * Returns a new SmartArray (immutable), preserving keys.
 *
 * @noinspection SpellCheckingInspection
 */
class WhereInListTest extends SmartArrayTestCase
{

    /**
     * @dataProvider whereInListProvider
     */
    public function testWhereInList(array $input, string $field, string|int|float $value, array $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray();

        $filtered = $smartArray->whereInList($field, $value);

        // Verify whereInList filtered correctly
        $this->assertEquals($expected, $filtered->toArray(), "Filtered array does not match expected output");

        // Verify original array wasn't modified (immutable)
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public static function whereInListProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'field'    => 'placement',
                'value'    => 'menu',
                'expected' => [],
            ],
            'single value field - match' => [
                'input'    => [
                    ['id' => 1, 'placement' => 'menu'],
                ],
                'field'    => 'placement',
                'value'    => 'menu',
                'expected' => [
                    0 => ['id' => 1, 'placement' => 'menu'],
                ],
            ],
            'single value field - no match' => [
                'input'    => [
                    ['id' => 1, 'placement' => 'footer'],
                ],
                'field'    => 'placement',
                'value'    => 'menu',
                'expected' => [],
            ],
            'tab-delimited field - match first value' => [
                'input'    => [
                    ['id' => 1, 'placement' => "\tmenu\tfooter\t"],
                ],
                'field'    => 'placement',
                'value'    => 'menu',
                'expected' => [
                    0 => ['id' => 1, 'placement' => "\tmenu\tfooter\t"],
                ],
            ],
            'tab-delimited field - match last value' => [
                'input'    => [
                    ['id' => 1, 'placement' => "\tmenu\tfooter\t"],
                ],
                'field'    => 'placement',
                'value'    => 'footer',
                'expected' => [
                    0 => ['id' => 1, 'placement' => "\tmenu\tfooter\t"],
                ],
            ],
            'tab-delimited field - match middle value' => [
                'input'    => [
                    ['id' => 1, 'placement' => "\theader\tmenu\tfooter\t"],
                ],
                'field'    => 'placement',
                'value'    => 'menu',
                'expected' => [
                    0 => ['id' => 1, 'placement' => "\theader\tmenu\tfooter\t"],
                ],
            ],
            'tab-delimited field - no match' => [
                'input'    => [
                    ['id' => 1, 'placement' => "\tmenu\tfooter\t"],
                ],
                'field'    => 'placement',
                'value'    => 'sidebar',
                'expected' => [],
            ],
            'no substring matching' => [
                'input'    => [
                    ['id' => 1, 'placement' => "\tmenu\t"],
                    ['id' => 2, 'placement' => "\tmenu\t"],
                ],
                'field'    => 'placement',
                'value'    => 'men',       // substring of "menu" should NOT match
                'expected' => [],
            ],
            'no substring matching - longer value' => [
                'input'    => [
                    ['id' => 1, 'placement' => "\tmenu\t"],
                    ['id' => 2, 'placement' => "\tmenu\t"],
                ],
                'field'    => 'placement',
                'value'    => 'menuitem',   // superstring of "menu" should NOT match
                'expected' => [],
            ],
            'multiple rows - mixed matches' => [
                'input'    => [
                    ['id' => 1, 'title' => 'Page A', 'placement' => "\tmenu\tfooter\t"],
                    ['id' => 2, 'title' => 'Page B', 'placement' => "\tsidebar\t"],
                    ['id' => 3, 'title' => 'Page C', 'placement' => 'menu'],
                    ['id' => 4, 'title' => 'Page D', 'placement' => "\theader\tfooter\t"],
                ],
                'field'    => 'placement',
                'value'    => 'menu',
                'expected' => [
                    0 => ['id' => 1, 'title' => 'Page A', 'placement' => "\tmenu\tfooter\t"],
                    2 => ['id' => 3, 'title' => 'Page C', 'placement' => 'menu'],
                ],
            ],
            'all rows match' => [
                'input'    => [
                    ['id' => 1, 'placement' => "\tmenu\tfooter\t"],
                    ['id' => 2, 'placement' => 'menu'],
                    ['id' => 3, 'placement' => "\tmenu\t"],
                ],
                'field'    => 'placement',
                'value'    => 'menu',
                'expected' => [
                    0 => ['id' => 1, 'placement' => "\tmenu\tfooter\t"],
                    1 => ['id' => 2, 'placement' => 'menu'],
                    2 => ['id' => 3, 'placement' => "\tmenu\t"],
                ],
            ],
            'no rows match' => [
                'input'    => [
                    ['id' => 1, 'placement' => "\tmenu\tfooter\t"],
                    ['id' => 2, 'placement' => 'sidebar'],
                ],
                'field'    => 'placement',
                'value'    => 'header',
                'expected' => [],
            ],
            'preserves original keys' => [
                'input'    => [
                    5 => ['id' => 10, 'placement' => "\tmenu\t"],
                    8 => ['id' => 20, 'placement' => "\tsidebar\t"],
                    9 => ['id' => 30, 'placement' => "\tmenu\tfooter\t"],
                ],
                'field'    => 'placement',
                'value'    => 'menu',
                'expected' => [
                    5 => ['id' => 10, 'placement' => "\tmenu\t"],
                    9 => ['id' => 30, 'placement' => "\tmenu\tfooter\t"],
                ],
            ],
            'integer search value' => [
                'input'    => [
                    ['id' => 1, 'category' => "\t1\t2\t"],
                    ['id' => 2, 'category' => "\t2\t3\t"],
                    ['id' => 3, 'category' => "\t4\t5\t"],
                ],
                'field'    => 'category',
                'value'    => 2,
                'expected' => [
                    0 => ['id' => 1, 'category' => "\t1\t2\t"],
                    1 => ['id' => 2, 'category' => "\t2\t3\t"],
                ],
            ],
            'float search value' => [
                'input'    => [
                    ['id' => 1, 'price' => "\t3.5\t7.0\t"],
                    ['id' => 2, 'price' => "\t5.0\t"],
                ],
                'field'    => 'price',
                'value'    => 3.5,
                'expected' => [
                    0 => ['id' => 1, 'price' => "\t3.5\t7.0\t"],
                ],
            ],
        ];
    }

    /**
     * Test that null field values are excluded from results
     */
    public function testWhereInListExcludesNullFieldValues(): void
    {
        $arr = new SmartArray([
            ['id' => 1, 'placement' => "\tmenu\t"],
            ['id' => 2, 'placement' => null],
            ['id' => 3, 'placement' => "\tmenu\tfooter\t"],
        ]);

        $result = $arr->whereInList('placement', 'menu');

        $this->assertEquals(
            [0 => ['id' => 1, 'placement' => "\tmenu\t"], 2 => ['id' => 3, 'placement' => "\tmenu\tfooter\t"]],
            $result->toArray()
        );
    }

    /**
     * Test that rows missing the field key are excluded
     */
    public function testWhereInListExcludesRowsWithMissingField(): void
    {
        $arr = new SmartArray([
            ['id' => 1, 'placement' => "\tmenu\t"],
            ['id' => 2],                              // missing 'placement' key
            ['id' => 3, 'placement' => "\tmenu\tfooter\t"],
        ]);

        $result = $arr->whereInList('placement', 'menu');

        $this->assertEquals(
            [0 => ['id' => 1, 'placement' => "\tmenu\t"], 2 => ['id' => 3, 'placement' => "\tmenu\tfooter\t"]],
            $result->toArray()
        );
    }

    /**
     * Test that non-array elements are silently skipped
     */
    public function testWhereInListSkipsNonArrayElements(): void
    {
        $arr = new SmartArray([
            ['id' => 1, 'placement' => "\tmenu\t"],
            'not an array',
            42,
            ['id' => 2, 'placement' => "\tmenu\tfooter\t"],
        ]);

        $result = $arr->whereInList('placement', 'menu');

        $this->assertEquals(
            [0 => ['id' => 1, 'placement' => "\tmenu\t"], 3 => ['id' => 2, 'placement' => "\tmenu\tfooter\t"]],
            $result->toArray()
        );
    }

    /**
     * Test that whereInList returns a new SmartArray instance
     */
    public function testWhereInListReturnsNewSmartArray(): void
    {
        $arr = new SmartArray([
            ['id' => 1, 'placement' => "\tmenu\t"],
        ]);

        $result = $arr->whereInList('placement', 'menu');

        $this->assertInstanceOf(SmartArray::class, $result);
        $this->assertNotSame($arr, $result);
    }

    /**
     * Test that the original SmartArray is not modified (immutability)
     */
    public function testWhereInListOriginalUnmodified(): void
    {
        $input = [
            ['id' => 1, 'placement' => "\tmenu\tfooter\t"],
            ['id' => 2, 'placement' => "\tsidebar\t"],
            ['id' => 3, 'placement' => "\tmenu\t"],
        ];
        $arr           = new SmartArray($input);
        $originalArray = $arr->toArray();

        $arr->whereInList('placement', 'menu');

        $this->assertEquals($originalArray, $arr->toArray(), "Original array should not be modified");
    }

    /**
     * Test that SmartString values are unwrapped via getRawValue()
     */
    public function testWhereInListWithSmartStringValue(): void
    {
        $arr = new SmartArray([
            ['id' => 1, 'placement' => "\tmenu\tfooter\t"],
            ['id' => 2, 'placement' => "\tsidebar\t"],
        ]);

        $searchValue = new SmartString('menu');
        $result      = $arr->whereInList('placement', $searchValue);

        $this->assertEquals(
            [0 => ['id' => 1, 'placement' => "\tmenu\tfooter\t"]],
            $result->toArray()
        );
    }

    /**
     * Test that whereInList on a flat array returns empty (no rows are arrays)
     */
    public function testWhereInListOnFlatArrayReturnsEmpty(): void
    {
        $arr = new SmartArray(['apple', 'banana', 'cherry']);

        $result = $arr->whereInList('placement', 'menu');

        $this->assertSame([], $result->toArray());
    }

    /**
     * Test that matching is case-sensitive
     */
    public function testWhereInListCaseSensitive(): void
    {
        $arr = new SmartArray([
            ['id' => 1, 'placement' => "\tmenu\t"],
            ['id' => 2, 'placement' => "\tMenu\t"],
            ['id' => 3, 'placement' => "\tMENU\t"],
        ]);

        $result = $arr->whereInList('placement', 'Menu');

        // Only exact case match should be returned
        $this->assertEquals(
            [1 => ['id' => 2, 'placement' => "\tMenu\t"]],
            $result->toArray()
        );
    }

}
