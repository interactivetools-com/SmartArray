<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartArray\Tests\TestHelpers;

/**
 * Tests for SmartArray::count() method and Countable interface.
 *
 * count() returns the number of elements. Works with both $arr->count() and count($arr).
 */
class CountTest extends SmartArrayTestCase
{

    /**
     * @dataProvider countProvider
     */
    public function testCountMethod(array $input, int $expected): void
    {
        foreach ([new SmartArray($input), new SmartArrayHtml($input)] as $object) {
            $this->assertSame($expected, $object->count());
        }
    }

    /**
     * @dataProvider countProvider
     * @noinspection PhpUnitTestsInspection
     */
    public function testCountFunction(array $input, int $expected): void
    {
        foreach ([new SmartArray($input), new SmartArrayHtml($input)] as $object) {
            $this->assertSame($expected, count($object));
        }
    }

    public static function countProvider(): array
    {
        return [
            'empty array'            => [[], 0],
            'empty nested array'     => [[[]], 1], // Outer array has one element (an empty array)
            'single element array'   => [[1], 1],
            'nested array'           => [[[1, 2]], 1],
            'flat array'             => [[1, 2, 3], 3],
            'mixed value array'      => [['Hello', 123, null], 3],
            'test nested array data' => [TestHelpers::getTestRecords(), 3],
            'test flat array data'   => [TestHelpers::getTestRecord(), 7],
            'test empty array data'  => [[], 0],
        ];
    }

}
