<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartArray\Tests\TestHelpers;

/**
 * Tests for SmartArray::isEmpty() and SmartArray::isNotEmpty() methods.
 *
 * isEmpty() returns true if the array has no elements.
 * isNotEmpty() returns true if the array has one or more elements.
 */
class IsEmptyTest extends SmartArrayTestCase
{

    /**
     * @dataProvider isEmptyProvider
     */
    public function testIsEmpty(array $input, bool $expected): void
    {
        foreach ([new SmartArray($input), new SmartArrayHtml($input)] as $object) {
            match ($expected) {
                true  => $this->assertTrue($object->isEmpty()),
                false => $this->assertFalse($object->isEmpty()),
            };
        }
    }

    /**
     * @dataProvider isEmptyProvider
     */
    public function testIsNotEmpty(array $input, bool $expected): void
    {
        foreach ([new SmartArray($input), new SmartArrayHtml($input)] as $object) {
            match (!$expected) {
                true  => $this->assertTrue($object->isNotEmpty()),
                false => $this->assertFalse($object->isNotEmpty()),
            };
        }
    }

    public static function isEmptyProvider(): array
    {
        return [
            'empty array'            => [[], true],
            'empty nested array'     => [[[]], false], // Nested empty array is not considered empty
            'single element array'   => [[1], false],
            'nested array'           => [[[1, 2]], false],
            'flat array'             => [[1, 2, 3], false],
            'mixed value array'      => [['Hello', 123, null], false],
            'test nested array data' => [TestHelpers::getTestRecords(), false],
            'test flat array data'   => [TestHelpers::getTestRecord(), false],
            'test empty array data'  => [[], true],
        ];
    }

}
