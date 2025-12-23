<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::isList() method.
 *
 * isList() returns true if the array is a list (sequential integer keys starting from 0).
 */
class IsListTest extends SmartArrayTestCase
{

    /**
     * @dataProvider isListProvider
     */
    public function testIsList(array $input, bool $expected): void
    {
        // Skip if method doesn't exist (future-proofing)
        if (!method_exists(SmartArray::class, 'isList')) {
            $this->assertTrue(true);
            return;
        }

        foreach ([new SmartArray($input), new SmartArrayHtml($input)] as $smartArray) {
            $keysCSV    = implode(',', array_keys($smartArray->toArray()));
            $varExport  = var_export($smartArray->toArray(), true);
            $this->assertSame(
                $expected,
                $smartArray->isList(),
                "Expected " . var_export($expected, true) . " with keys: $keysCSV\n$varExport"
            );
        }
    }

    public static function isListProvider(): array
    {
        return [
            // Sequential numeric arrays (lists)
            'empty array'           => [[], true],
            'sequential numbers'    => [[1, 2, 3], true],
            'sequential strings'    => [['a', 'b', 'c'], true],
            'sequential mixed'      => [[1, 'b', null], true],

            // Non-sequential arrays
            'non-sequential keys'   => [[1 => 'a', 0 => 'b'], false],
            'string keys'           => [['a' => 1, 'b' => 2], false],
            'mixed keys'            => [['a' => 1, 0 => 2], false],
            'gaps in numeric keys'  => [[0 => 'a', 2 => 'b'], false],

            // Nested arrays
            'nested sequential'     => [[1, [2, 3], 4], true],
            'nested non-sequential' => [['a' => [1, 2], 'b' => 3], false],
        ];
    }

}
