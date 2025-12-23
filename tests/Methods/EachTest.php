<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartString\SmartString;

/**
 * Tests for SmartArray::each() method.
 *
 * each($callback) executes a callback for each element (for side effects).
 * Unlike map(), returns the original SmartArray for chaining (not a new one).
 * Callback receives SmartString/SmartArray objects (when SmartStrings enabled).
 */
class EachTest extends SmartArrayTestCase
{

    /**
     * @dataProvider eachProvider
     */
    public function testEach(array $input, array $expectedResults): void
    {
        $smartArray  = new SmartArray($input);
        $smartArray  = $smartArray->enableSmartStrings();

        $results     = [];
        $returnValue = $smartArray->each(function ($value, $key) use (&$results) {
            $results[$key] = [
                'key'           => $key,
                'isSmartString' => $value instanceof SmartString,
                'isSmartArray'  => $value instanceof SmartArrayBase,
                'value'         => $value instanceof SmartString
                    ? $value->value()
                    : ($value instanceof SmartArrayBase ? 'SmartArray' : $value),
            ];
        });

        // each() returns original SmartArray for chaining
        $this->assertSame($smartArray, $returnValue, "each() should return the original SmartArray for chaining");

        // Results match expectations
        $this->assertEquals($expectedResults, $results, "each() callback should receive SmartString/SmartArray objects");
    }

    public static function eachProvider(): array
    {
        return [
            'empty array' => [
                'input'           => [],
                'expectedResults' => [],
            ],
            'flat array with strings' => [
                'input'           => ['hello', 'world'],
                'expectedResults' => [
                    0 => [
                        'key'           => 0,
                        'isSmartString' => true,
                        'isSmartArray'  => false,
                        'value'         => 'hello',
                    ],
                    1 => [
                        'key'           => 1,
                        'isSmartString' => true,
                        'isSmartArray'  => false,
                        'value'         => 'world',
                    ],
                ],
            ],
            'mixed types' => [
                'input'           => ['text' => 'hello', 'num' => 42, 'bool' => true],
                'expectedResults' => [
                    'text' => [
                        'key'           => 'text',
                        'isSmartString' => true,
                        'isSmartArray'  => false,
                        'value'         => 'hello',
                    ],
                    'num' => [
                        'key'           => 'num',
                        'isSmartString' => true,
                        'isSmartArray'  => false,
                        'value'         => 42,
                    ],
                    'bool' => [
                        'key'           => 'bool',
                        'isSmartString' => true,
                        'isSmartArray'  => false,
                        'value'         => true,
                    ],
                ],
            ],
            'nested array' => [
                'input'           => [
                    'user' => ['name' => 'John', 'age' => 30],
                ],
                'expectedResults' => [
                    'user' => [
                        'key'           => 'user',
                        'isSmartString' => false,
                        'isSmartArray'  => true,
                        'value'         => 'SmartArray',
                    ],
                ],
            ],
        ];
    }

}
