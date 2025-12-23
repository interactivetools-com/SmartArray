<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::values() method.
 *
 * values() returns a SmartArray containing all the values, re-indexed with numeric keys.
 */
class ValuesTest extends SmartArrayTestCase
{

    /**
     * @dataProvider valuesProvider
     */
    public function testValues(array $input, array $expected): void
    {
        $expectedSmartArray = new SmartArray($expected);
        $actualSmartArray   = (new SmartArray($input))->values();
        $this->assertSame($expectedSmartArray->toArray(), $actualSmartArray->toArray());
    }

    public static function valuesProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'expected' => [],
            ],
            'flat array with primitives' => [
                'input'    => [1, 'string', true, null, 3.14],
                'expected' => [1, 'string', true, null, 3.14],
            ],
            'associative array' => [
                'input'    => [
                    'first'  => 'apple',
                    'second' => 'banana',
                    'third'  => 'cherry',
                ],
                'expected' => ['apple', 'banana', 'cherry'],
            ],
            'nested array' => [
                'input'    => [
                    'fruits'     => ['apple', 'banana'],
                    'vegetables' => ['carrot', 'lettuce'],
                ],
                'expected' => [
                    ['apple', 'banana'],
                    ['carrot', 'lettuce'],
                ],
            ],
            'mixed keys' => [
                'input'    => [
                    0       => 'apple',
                    'one'   => 'banana',
                    2       => 'cherry',
                    'three' => 'date',
                ],
                'expected' => ['apple', 'banana', 'cherry', 'date'],
            ],
            'mixed data types' => [
                'input'    => [
                    'name'    => 'John Doe',
                    'age'     => 30,
                    'emails'  => ['john@example.com', 'doe@example.com'],
                    'active'  => true,
                    'profile' => null,
                ],
                'expected' => [
                    'John Doe',
                    30,
                    ['john@example.com', 'doe@example.com'],
                    true,
                    null,
                ],
            ],
        ];
    }

}
