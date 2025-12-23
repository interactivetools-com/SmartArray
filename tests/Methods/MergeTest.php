<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartArray\Tests\TestHelpers;

/**
 * Tests for SmartArray::merge() method.
 *
 * merge(...$arrays) merges one or more arrays into the SmartArray.
 * Follows PHP's array_merge() behavior: string keys are overwritten, numeric keys are appended.
 */
class MergeTest extends SmartArrayTestCase
{

    /**
     * @dataProvider mergeProvider
     */
    public function testMerge(array $input, array $arrays, array $expected): void
    {
        // Test SmartArray without SmartStrings
        $smartArray = new SmartArray($input);
        $result     = $smartArray->merge(...$arrays);
        $this->assertEquals($expected, $result->toArray());

        // Test SmartArray with SmartStrings
        $smartArraySS = new SmartArrayHtml($input);
        $resultSS     = $smartArraySS->merge(...$arrays);
        $this->assertEquals($this->htmlEncode($expected), TestHelpers::toArrayResolveSS($resultSS));
    }

    public static function mergeProvider(): array
    {
        return [
            'merge with empty array' => [
                'input'    => ['a' => 1, 'b' => 2],
                'arrays'   => [[]],
                'expected' => ['a' => 1, 'b' => 2],
            ],
            'merge empty array with non-empty' => [
                'input'    => [],
                'arrays'   => [['a' => 1, 'b' => 2]],
                'expected' => ['a' => 1, 'b' => 2],
            ],
            'merge multiple arrays' => [
                'input'    => ['a' => 1],
                'arrays'   => [
                    ['b' => 2],
                    ['c' => 3],
                ],
                'expected' => ['a' => 1, 'b' => 2, 'c' => 3],
            ],
            'string keys overwrites' => [
                'input'    => ['name' => 'Alice', 'age' => 30],
                'arrays'   => [['name' => 'Bob', 'city' => 'NY']],
                'expected' => ['name' => 'Bob', 'age' => 30, 'city' => 'NY'],
            ],
            'numeric arrays combines and reindexes' => [
                'input'    => [1, 2, 3],
                'arrays'   => [[4, 5, 6], [7, 8, 9]],
                'expected' => [1, 2, 3, 4, 5, 6, 7, 8, 9],
            ],
            'mixed numeric and string keys' => [
                'input'    => ['a' => 1, 0 => 'first'],
                'arrays'   => [['b' => 2, 0 => 'second']],
                'expected' => ['a' => 1, 0 => 'first', 'b' => 2, 1 => 'second'],
            ],
            'nested arrays (shallow merge)' => [
                'input'    => ['user' => ['name' => 'Alice']],
                'arrays'   => [['user' => ['age' => 30]]],
                'expected' => ['user' => ['age' => 30]],
            ],
            'merge SmartArrays' => [
                'input'    => ['a' => 1],
                'arrays'   => [
                    new SmartArray(['b' => 2]),
                    new SmartArray(['c' => 3]),
                ],
                'expected' => ['a' => 1, 'b' => 2, 'c' => 3],
            ],
            'special characters' => [
                'input'    => ['name' => "O'Connor"],
                'arrays'   => [['company' => 'Smith & Sons', 'title' => '<CEO>']],
                'expected' => ['name' => "O'Connor", 'company' => 'Smith & Sons', 'title' => '<CEO>'],
            ],
            'null values' => [
                'input'    => ['a' => 1, 'b' => null],
                'arrays'   => [['b' => 2, 'c' => null]],
                'expected' => ['a' => 1, 'b' => 2, 'c' => null],
            ],
            'boolean values' => [
                'input'    => ['active' => true],
                'arrays'   => [['verified' => false]],
                'expected' => ['active' => true, 'verified' => false],
            ],
            'deeply nested arrays (shallow merge)' => [
                'input'    => [
                    'level1' => [
                        'level2' => ['a' => 1]
                    ]
                ],
                'arrays'   => [[
                    'level1' => [
                        'level2' => ['b' => 2]
                    ]
                ]],
                'expected' => [
                    'level1' => [
                        'level2' => ['b' => 2]
                    ]
                ],
            ],
        ];
    }

}
