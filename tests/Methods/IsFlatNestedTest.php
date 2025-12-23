<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::isFlat() and SmartArray::isNested() methods.
 *
 * isFlat() returns true if the array contains no nested arrays.
 * isNested() returns true if the array contains at least one nested array.
 */
class IsFlatNestedTest extends SmartArrayTestCase
{

    /**
     * @dataProvider isFlatProvider
     */
    public function testIsFlat(array $input, bool $expected): void
    {
        foreach ([new SmartArray($input), new SmartArrayHtml($input)] as $smartArray) {
            $varExport  = var_export($smartArray->toArray(), true);
            $this->assertSame(
                $expected,
                $smartArray->isFlat(),
                "Expected isFlat() = " . var_export($expected, true) . " with structure:\n$varExport"
            );
        }
    }

    /**
     * @dataProvider isFlatProvider
     */
    public function testIsNested(array $input, bool $expected): void
    {
        $expected = !$expected; // isNested is opposite of isFlat

        foreach ([new SmartArray($input), new SmartArrayHtml($input)] as $smartArray) {
            $varExport  = var_export($smartArray->toArray(), true);
            $this->assertSame(
                $expected,
                $smartArray->isNested(),
                "Expected isNested() = " . var_export($expected, true) . " with structure:\n$varExport"
            );
        }
    }

    public static function isFlatProvider(): array
    {
        return [
            'empty array'              => [[], true],
            'flat numeric array'       => [[1, 2, 3], true],
            'flat string array'        => [['a', 'b', 'c'], true],
            'flat associative array'   => [['a' => 1, 'b' => 2, 'c' => 3], true],
            'flat mixed types'         => [['a', 2, null, true, 1.5], true],
            'nested numeric array'     => [[1, [2, 3], 4], false],
            'nested associative array' => [['a' => ['b' => 2], 'c' => 3], false],
            'multiple nested arrays'   => [['a' => [1, 2], 'b' => [3, 4]], false],
            'deeply nested array'      => [[1, [2, [3, 4]], 5], false],
            'empty nested array'       => [[1, [], 3], false],
            'array at start'           => [[[1], 2, 3], false],
            'array at end'             => [[1, 2, [3]], false],
            'only nested array'        => [[[1, 2, 3]], false],
        ];
    }

}
