<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::keys() method.
 *
 * keys() returns a SmartArray containing all the keys of the array.
 */
class KeysTest extends SmartArrayTestCase
{

    /**
     * @dataProvider keysProvider
     */
    public function testKeys(array $input, array $expected): void
    {
        $expectedSmartArray = new SmartArray($expected);
        $actualSmartArray   = (new SmartArray($input))->keys();
        $this->assertSame($expectedSmartArray->toArray(), $actualSmartArray->toArray());
    }

    public static function keysProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'expected' => [],
            ],
            'flat array with numeric keys' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'expected' => [0, 1, 2],
            ],
            'associative array' => [
                'input'    => [
                    'first'  => 'apple',
                    'second' => 'banana',
                    'third'  => 'cherry',
                ],
                'expected' => ['first', 'second', 'third'],
            ],
            'nested array' => [
                'input'    => [
                    'fruits'     => ['apple', 'banana'],
                    'vegetables' => ['carrot', 'lettuce'],
                ],
                'expected' => ['fruits', 'vegetables'],
            ],
            'mixed keys' => [
                'input'    => [
                    0       => 'apple',
                    'one'   => 'banana',
                    2       => 'cherry',
                    'three' => 'date',
                ],
                'expected' => [0, 'one', 2, 'three'],
            ],
        ];
    }

}
