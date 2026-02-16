<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::mysqli() method.
 *
 * mysqli() returns metadata about the database source (database, table, etc.).
 * This metadata is preserved through transformations and inherited by nested arrays.
 */
class MysqliTest extends SmartArrayTestCase
{

    /**
     * @dataProvider mysqliProvider
     */
    public function testMysqli(array $input, array $metadata, ?callable $operation, array $expected): void
    {
        // Create initial SmartArray with metadata
        $properties = ['mysqli' => $metadata];
        $smartArray = new SmartArray($input, $properties);

        // Perform operation if specified
        if ($operation) {
            $smartArray = $operation($smartArray);
        }

        // Verify metadata
        $actualMysqli = (array) $smartArray->mysqli();
        $this->assertEquals($expected, $actualMysqli);

        // Test nested arrays also have same mysqli info
        foreach ($smartArray as $value) {
            if ($value instanceof SmartArray) {
                $this->assertEquals($expected, (array) $value->mysqli());
            }
        }
    }

    public static function mysqliProvider(): array
    {
        $baseMetadata = ['database' => 'test_db', 'table' => 'users'];

        return [
            'basic metadata' => [
                'input'     => ['name' => 'John', 'age' => 30],
                'metadata'  => $baseMetadata,
                'operation' => null,
                'expected'  => $baseMetadata,
            ],
            'nested array inheritance' => [
                'input'     => [
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane'],
                ],
                'metadata'  => $baseMetadata,
                'operation' => null,
                'expected'  => $baseMetadata,
            ],
            'metadata preserved after map' => [
                'input'     => ['a' => 1, 'b' => 2],
                'metadata'  => $baseMetadata,
                'operation' => fn($arr) => $arr->map(fn($x) => $x * 2),
                'expected'  => $baseMetadata,
            ],
            'metadata preserved after filter' => [
                'input'     => ['a' => 1, 'b' => 2, 'c' => 3],
                'metadata'  => $baseMetadata,
                'operation' => fn($arr) => $arr->filter(fn($x) => $x > 1),
                'expected'  => $baseMetadata,
            ],
            'metadata with complex transformations' => [
                'input'     => [
                    ['id' => 1, 'score' => 10],
                    ['id' => 2, 'score' => 20],
                    ['id' => 3, 'score' => 30],
                ],
                'metadata'  => $baseMetadata,
                'operation' => fn($arr) => $arr
                    ->map(fn($x) => ['id' => $x['id'], 'doubled_score' => $x['score'] * 2])
                    ->filter(fn($x) => $x['doubled_score'] > 30)
                    ->groupBy('id'),
                'expected'  => $baseMetadata,
            ],
            'empty array with metadata' => [
                'input'     => [],
                'metadata'  => $baseMetadata,
                'operation' => null,
                'expected'  => $baseMetadata,
            ],
            'complex nested structure' => [
                'input'     => [
                    'users' => [
                        ['id' => 1, 'details' => ['age' => 25]],
                        ['id' => 2, 'details' => ['age' => 30]],
                    ],
                ],
                'metadata'  => $baseMetadata,
                'operation' => null,
                'expected'  => $baseMetadata,
            ],
        ];
    }

}
