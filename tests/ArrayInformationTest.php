<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use PHPUnit\Framework\TestCase;
use Itools\SmartArray\SmartArray;
use Itools\SmartString\SmartString;

class ArrayInformationTest extends TestCase
{
//region Array Information

    /**
     * @dataProvider countProvider
     */
    public function testCountMethod($input, int $expected): void
    {
        foreach (['new', 'newSS'] as $method) {
            $object = SmartArray::$method($input);
            $this->assertSame($expected, $object->count());
        }
    }

    /**
     * @dataProvider countProvider
     * @noinspection PhpUnitTestsInspection
     */
    public function testCountFunction($input, int $expected): void
    {
        foreach (['new', 'newSS'] as $method) {
            $object = SmartArray::$method($input);
            $this->assertSame($expected, count($object));
        }
    }

    public function countProvider(): array
    {
        return [
            // Test empty arrays
            "empty array"            => [[], 0],
            "empty nested array"     => [[[]], 1], // The outer array has one element (an empty array)

            // Test non-empty arrays
            "single element array"   => [[1], 1],
            "nested array"           => [[[1, 2]], 1],
            "flat array"             => [[1, 2, 3], 3],

            // Test arrays with various types
            "mixed value array"      => [["Hello", 123, null], 3],

            // Test with test records
            "test nested array data" => [TestHelpers::getTestRecords(), 3],
            "test flat array data"   => [TestHelpers::getTestRecord(), 7],
            "test empty array data"  => [[], 0],
        ];
    }

    /**
     * @dataProvider isEmptyProvider
     */
    public function testIsEmpty($input, bool $expected): void
    {
        foreach (['new', 'newSS'] as $method) {
            $object = SmartArray::$method($input);
            match ($expected) {
                true  => $this->assertTrue($object->isEmpty()),
                false => $this->assertFalse($object->isEmpty()),
            };
        }
    }

    /**
     * @dataProvider isEmptyProvider
     */
    public function testIsNotEmpty($input, bool $expected): void
    {
        foreach (['new', 'newSS'] as $method) {
            $object = SmartArray::$method($input);
            match (!$expected) {
                true  => $this->assertTrue($object->isNotEmpty()),
                false => $this->assertFalse($object->isNotEmpty()),
            };
        }
    }

    public function isEmptyProvider(): array
    {
        return [
            // Test empty arrays
            "empty array"            => [[], true],
            "empty nested array"     => [[[]], false], // Nested empty array is not considered empty

            // Test non-empty arrays
            "single element array"   => [[1], false],
            "nested array"           => [[[1, 2]], false],
            "flat array"             => [[1, 2, 3], false],

            // Test arrays with various types
            "mixed value array"      => [["Hello", 123, null], false],

            // Test with test records
            "test nested array data" => [TestHelpers::getTestRecords(), false],
            "test flat array data"   => [TestHelpers::getTestRecord(), false],
            "test empty array data"  => [[], true],
        ];
    }
    
    /**
     * @dataProvider containsProvider
     * 
     * Tests the contains() method, which checks if an array contains a specific value.
     * 
     * Note: The current implementation of contains() uses loose comparison (in_array with 
     * strict=false), which means it will return true for things like contains('0') when 
     * the array contains 0. This behavior matches PHP's default in_array() behavior.
     */
    public function testContains($input, $value, $expected): void
    {
        foreach (['new', 'newSS'] as $method) {
            $smartArray = SmartArray::$method($input);
            $result = $smartArray->contains($value);
            $this->assertSame($expected, $result, "Failed asserting contains() for {$method}");
        }
    }
    
    public function containsProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'value'    => 'anything',
                'expected' => false,
            ],
            'flat array with existing value' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'value'    => 'banana',
                'expected' => true,
            ],
            'flat array with non-existing value' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'value'    => 'orange',
                'expected' => false,
            ],
            'numeric array with existing value' => [
                'input'    => [1, 2, 3, 4, 5],
                'value'    => 3,
                'expected' => true,
            ],
            'numeric array with non-existing value' => [
                'input'    => [1, 2, 3, 4, 5],
                'value'    => 6,
                'expected' => false,
            ],
            'mixed array with existing value' => [
                'input'    => [1, 'two', true, null, 5.5],
                'value'    => true,
                'expected' => true,
            ],
            'array with completely non-existing value' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'value'    => 'orange',
                'expected' => false,
            ],
            'mixed array with type-juggled match (loose comparison)' => [
                'input'    => [1, 'two', true, null, 5.5],
                'value'    => '1', // String '1' matches integer 1 in loose comparison
                'expected' => true,
            ],
            'nested array with SmartString input' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'value'    => new SmartString('banana'),
                'expected' => true,
            ],
            'associative array with existing value' => [
                'input'    => ['a' => 'apple', 'b' => 'banana', 'c' => 'cherry'],
                'value'    => 'cherry',
                'expected' => true,
            ],
            'associative array with existing key as value' => [
                'input'    => ['a' => 'apple', 'b' => 'banana', 'c' => 'cherry'],
                'value'    => 'a',
                'expected' => false, // keys are not searched, only values
            ],
            'array with null value' => [
                'input'    => ['apple', null, 'cherry'],
                'value'    => null,
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider isListProvider
     */
    public function testIsList(array $input, bool $expected): void
    {
        // skip if method doesn't exist
        if (!method_exists(SmartArray::class, 'isList')) {
            // leave test here in case we re-add method in future
            $this->assertTrue(true);
            return;
        }

        // original test
        foreach (['new', 'newSS'] as $newMethod) {
            $smartArray = SmartArray::$newMethod($input);
            $keysCSV    = implode(',', array_keys($smartArray->getArrayCopy()));
            $varExport  = var_export($smartArray->toArray(), true);
            $this->assertSame($expected, $smartArray->isList(), "Expected " . var_export($expected, true) . " with keys: $keysCSV\n$varExport");
        }
    }

    public function isListProvider(): array
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

    /**
     * @dataProvider isFlatProvider
     */
    public function testIsFlat(array $input, bool $expected): void
    {
        foreach (['new', 'newSS'] as $newMethod) {
            $smartArray = SmartArray::$newMethod($input);
            $varExport  = var_export($smartArray->toArray(), true);
            $this->assertSame($expected, $smartArray->isFlat(), "Expected " . var_export($expected, true) . " with structure:\n$varExport");
        }
    }

    public function isFlatProvider(): array
    {
        return [
            'empty array'              => [
                [],
                true,
            ],
            'flat numeric array'       => [
                [1, 2, 3],
                true,
            ],
            'flat string array'        => [
                ['a', 'b', 'c'],
                true,
            ],
            'flat associative array'   => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                true,
            ],
            'flat mixed types'         => [
                ['a', 2, null, true, 1.5],
                true,
            ],
            'nested numeric array'     => [
                [1, [2, 3], 4],
                false,
            ],
            'nested associative array' => [
                ['a' => ['b' => 2], 'c' => 3],
                false,
            ],
            'multiple nested arrays'   => [
                ['a' => [1, 2], 'b' => [3, 4]],
                false,
            ],
            'deeply nested array'      => [
                [1, [2, [3, 4]], 5],
                false,
            ],
            'empty nested array'       => [
                [1, [], 3],
                false,
            ],
            'array at start'           => [
                [[1], 2, 3],
                false,
            ],
            'array at end'             => [
                [1, 2, [3]],
                false,
            ],
            'only nested array'        => [
                [[1, 2, 3]],
                false,
            ],
        ];
    }

    /**
     * @dataProvider isFlatProvider
     */
    public function testIsNested(array $input, bool $expected): void
    {
        $expected = !$expected; // The expected value is the opposite of our isFlatProvider

        foreach (['new', 'newSS'] as $newMethod) {
            $smartArray = SmartArray::$newMethod($input);
            $varExport  = var_export($smartArray->toArray(), true);
            $this->assertSame($expected, $smartArray->isNested(), "Expected " . var_export($expected, true) . " with structure:\n$varExport");
        }
    }

    /**
     * @dataProvider mysqliProvider
     */
    public function testMysqli($input, $mysqliInfo, $operation, $expected): void
    {
        // Create initial SmartArray with metadata
        $properties = ['mysqli' => $mysqliInfo];
        $smartArray = new SmartArray($input, $properties);

        // Perform operation if specified
        if ($operation) {
            $smartArray = $operation($smartArray);
        }

        // Verify metadata
        $actualMysqli = (array)$smartArray->mysqli();
        $this->assertEquals($expected, $actualMysqli);

        // Test nested arrays also have same mysqli info
        foreach ($smartArray as $value) {
            if ($value instanceof SmartArray) {
                $this->assertEquals($expected, (array)$value->mysqli());
            }
        }
    }

    public function mysqliProvider(): array
    {
        // Note: This was converted from the old metadata test, but should work fine for testing mysqli info
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
                'operation' => fn($arr)
                    => $arr
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

    /**
     * @dataProvider rootProvider
     */
    public function testRoot($input, $operation): void
    {
        $rootArray = new SmartArray($input);
        $testArray = $operation ? $operation($rootArray) : $rootArray;

        // test root array references self
        $this->assertSame($rootArray, $rootArray->root(), "Root array ->root() should reference self");

        // test transformed array references root
        ob_start();
        $rootArray->debug();
        $rootData = ob_get_clean();

        ob_start();
        $testArray->debug();
        $testData = ob_get_clean();

        $this->assertSame($rootArray, $testArray->root(), "Nested array should reference original root\nORIGINAL: $rootData\nMODIFIED: $testData");
    }

    public function rootProvider(): array
    {
        $nestedInput = [
            ['id' => 1, 'name' => 'John', 'city' => 'NYC', 'score' => 85],
            ['id' => 2, 'name' => 'Jane', 'city' => 'LA', 'score' => 92],
            ['id' => 3, 'name' => 'Bob', 'city' => 'NYC', 'score' => 78],
            ['id' => 4, 'name' => 'Alice', 'city' => 'LA', 'score' => 95],
        ];

        return [
            'root array has no root' => [
                'input'     => ['a' => 1, 'b' => 2],
                'operation' => null,
            ],

            'first() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->first(),
            ],

            'last() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->last(),
            ],

            'nth() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->nth(2),
            ],

            'map() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->map(fn($x) => ['id' => $x['id'], 'grade' => $x['score'] >= 90 ? 'A' : 'B']),
            ],

            'filter() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->filter(fn($x) => $x['score'] >= 90),
            ],

            'where() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->where(['city' => 'NYC']),
            ],

            'sort() maintains root' => [
                'input'     => [5, 2, 8, 1, 9],
                'operation' => fn($arr) => $arr->sort(),
            ],

            'sortBy() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->sortBy('score'),
            ],

            'unique() maintains root' => [
                'input'     => [1, 2, 2, 3, 3, 4],
                'operation' => fn($arr) => $arr->unique(),
            ],

            'keys() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->keys(),
            ],

            'values() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->values(),
            ],

            'pluck() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->pluck('name'),
            ],

            'indexBy() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->indexBy('id'),
            ],

            'groupBy() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->groupBy('city')->NYC->first(),
            ],

            'chunk() maintains root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr) => $arr->chunk(2),
            ],

            'chained transformations maintain root' => [
                'input'     => $nestedInput,
                'operation' => fn($arr)
                    => $arr
                    ->filter(fn($x) => $x['score'] >= 80)
                    ->map(fn($x) => ['name' => $x['name'], 'grade' => $x['score'] >= 90 ? 'A' : 'B'])
                    ->sortBy('name')
                    ->groupBy('grade')
                    ->first(),
            ],

            'empty array has no root' => [
                'input'     => [],
                'operation' => null,
            ],
        ];
    }

//endregion
}
