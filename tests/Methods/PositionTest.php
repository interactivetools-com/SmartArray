<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::position() method.
 *
 * position() returns the 1-based position of a nested SmartArray element
 * within its parent array.
 */
class PositionTest extends SmartArrayTestCase
{

    /**
     * @dataProvider positionProvider
     */
    public function testPosition(array $initialData, array $expectedPositions): void
    {
        $array           = new SmartArray($initialData);
        $actualPositions = [];

        foreach ($array as $element) {
            if ($element instanceof SmartArray) {
                $actualPositions[] = $element->position();
            }
        }

        $this->assertEquals($expectedPositions, $actualPositions);
    }

    public static function positionProvider(): array
    {
        return [
            'nested SmartArrays' => [
                'initialData'       => [
                    'first'  => ['id' => 1, 'name' => 'Alice'],
                    'second' => ['id' => 2, 'name' => 'Bob'],
                    'third'  => ['id' => 3, 'name' => 'Charlie'],
                ],
                'expectedPositions' => [1, 2, 3],
            ],
            'only some elements are SmartArrays' => [
                'initialData'       => [
                    'group1' => ['member1' => 'Alice', 'member2' => 'Bob'],
                    'group2' => 'Not a SmartArray',
                    'group3' => ['member3' => 'Charlie'],
                ],
                'expectedPositions' => [1, 3],
            ],
            'empty array' => [
                'initialData'       => [],
                'expectedPositions' => [],
            ],
            'single nested SmartArray' => [
                'initialData'       => [
                    'only' => ['id' => 1, 'name' => 'Single'],
                ],
                'expectedPositions' => [1],
            ],
            'mixed element types' => [
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

}
