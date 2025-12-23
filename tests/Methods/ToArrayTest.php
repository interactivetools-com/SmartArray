<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartArray\Tests\TestHelpers;
use Itools\SmartString\SmartString;

/**
 * Tests for SmartArray::toArray() method.
 *
 * toArray() converts the SmartArray back to a plain PHP array.
 * Recursively converts nested SmartArrays and SmartStrings to their raw values.
 */
class ToArrayTest extends SmartArrayTestCase
{

    /**
     * @dataProvider toArrayProvider
     */
    public function testToArray(array $input, array $expectedOutput): void
    {
        $smartArray   = new SmartArray($input);
        $actualOutput = $smartArray->toArray();
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public static function toArrayProvider(): array
    {
        $testRecords               = TestHelpers::getTestRecords();
        $expectedTestRecordsOutput = $testRecords;
        foreach ($expectedTestRecordsOutput as $index => $record) {
            foreach ($record as $key => $value) {
                if ($value instanceof SmartString) {
                    $expectedTestRecordsOutput[$index][$key] = $value->value();
                }
            }
        }

        return [
            'empty array' => [
                'input'          => [],
                'expectedOutput' => [],
            ],
            'flat array with primitives' => [
                'input'          => [1, 'string', true, null, 3.14],
                'expectedOutput' => [1, 'string', true, null, 3.14],
            ],
            'array with HTML characters' => [
                'input'          => [
                    'name' => 'John <b> Doe',
                    'age'  => 30,
                ],
                'expectedOutput' => [
                    'name' => 'John <b> Doe',
                    'age'  => 30,
                ],
            ],
            'nested SmartArray' => [
                'input'          => [
                    'user'   => [
                        'name'  => 'Jane " Doe',
                        'email' => 'jane@example.com',
                    ],
                    'active' => true,
                ],
                'expectedOutput' => [
                    'user'   => [
                        'name'  => 'Jane " Doe',
                        'email' => 'jane@example.com',
                    ],
                    'active' => true,
                ],
            ],
            'deeply nested SmartArray' => [
                'input'          => [
                    'level1' => [
                        'level2' => [
                            'level3' => [
                                'value' => '<deep> value',
                            ],
                        ],
                    ],
                ],
                'expectedOutput' => [
                    'level1' => [
                        'level2' => [
                            'level3' => [
                                'value' => '<deep> value',
                            ],
                        ],
                    ],
                ],
            ],
            'mixed types' => [
                'input'          => [
                    'number'      => 42,
                    'string'      => 'hello',
                    'smartString' => '"world"',
                    'array'       => [1, 2, 3],
                    'smartArray'  => [4, 5, 6],
                ],
                'expectedOutput' => [
                    'number'      => 42,
                    'string'      => 'hello',
                    'smartString' => '"world"',
                    'array'       => [1, 2, 3],
                    'smartArray'  => [4, 5, 6],
                ],
            ],
            'test records' => [
                'input'          => $testRecords,
                'expectedOutput' => $expectedTestRecordsOutput,
            ],
        ];
    }

}
