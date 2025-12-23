<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::root() method.
 *
 * root() returns a reference to the root SmartArray.
 * For the root array, it returns itself. For nested arrays, it returns the original parent.
 * This reference is preserved through all transformations.
 */
class RootTest extends SmartArrayTestCase
{

    /**
     * @dataProvider rootProvider
     */
    public function testRoot(array $input, ?callable $operation): void
    {
        $rootArray = new SmartArray($input);
        $testArray = $operation ? $operation($rootArray) : $rootArray;

        // Test root array references self
        $this->assertSame($rootArray, $rootArray->root(), "Root array ->root() should reference self");

        // Test transformed array references root
        ob_start();
        $rootArray->debug();
        $rootData = ob_get_clean();

        ob_start();
        $testArray->debug();
        $testData = ob_get_clean();

        $this->assertSame(
            $rootArray,
            $testArray->root(),
            "Nested array should reference original root\nORIGINAL: $rootData\nMODIFIED: $testData"
        );
    }

    public static function rootProvider(): array
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
                'operation' => fn($arr) => $arr
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

}
