<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::chunk() method.
 *
 * chunk($size) splits the array into chunks of the specified size.
 * Returns a SmartArray of SmartArrays.
 */
class ChunkTest extends SmartArrayTestCase
{

    /**
     * @dataProvider chunkProvider
     */
    public function testChunk(
        array $input,
        int $size,
        array $expected,
        bool $shouldThrowException = false,
        string $expectedExceptionMessage = ''
    ): void {
        $smartArray = new SmartArray($input);

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
            $smartArray->chunk($size);
            return;
        }

        $actual = $smartArray->chunk($size)->toArray();
        $this->assertEquals($expected, $actual, "Chunked SmartArray does not match expected output.");
    }

    public static function chunkProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'size'     => 3,
                'expected' => [],
            ],
            'size greater than array size' => [
                'input'    => [1, 2, 3, 4, 5],
                'size'     => 10,
                'expected' => [[1, 2, 3, 4, 5]],
            ],
            'size less than array size' => [
                'input'    => [1, 2, 3, 4, 5],
                'size'     => 2,
                'expected' => [[1, 2], [3, 4], [5]],
            ],
            'size equal to array size' => [
                'input'    => [1, 2, 3, 4, 5],
                'size'     => 5,
                'expected' => [[1, 2, 3, 4, 5]],
            ],
            'size is one' => [
                'input'    => [1, 2, 3, 4, 5],
                'size'     => 1,
                'expected' => [[1], [2], [3], [4], [5]],
            ],
            'negative size throws exception' => [
                'input'                    => [1, 2, 3],
                'size'                     => -2,
                'expected'                 => [],
                'shouldThrowException'     => true,
                'expectedExceptionMessage' => "Chunk size must be greater than 0.",
            ],
            'zero size throws exception' => [
                'input'                    => [1, 2, 3],
                'size'                     => 0,
                'expected'                 => [],
                'shouldThrowException'     => true,
                'expectedExceptionMessage' => "Chunk size must be greater than 0.",
            ],
            'nested arrays' => [
                'input'    => [[1, 2], [3, 4], [5, 6]],
                'size'     => 2,
                'expected' => [
                    [[1, 2], [3, 4]],
                    [[5, 6]],
                ],
            ],
            'non-integer elements' => [
                'input'    => ['a', 'b', 'c', 'd', 'e'],
                'size'     => 2,
                'expected' => [['a', 'b'], ['c', 'd'], ['e']],
            ],
            'large array' => [
                'input'    => range(1, 100),
                'size'     => 15,
                'expected' => array_chunk(range(1, 100), 15),
            ],
            'user array chunks' => [
                'input'    => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                    ['id' => 4, 'name' => 'David'],
                    ['id' => 5, 'name' => 'Eve'],
                ],
                'size'     => 2,
                'expected' => [
                    [
                        ['id' => 1, 'name' => 'Alice'],
                        ['id' => 2, 'name' => 'Bob'],
                    ],
                    [
                        ['id' => 3, 'name' => 'Charlie'],
                        ['id' => 4, 'name' => 'David'],
                    ],
                    [
                        ['id' => 5, 'name' => 'Eve'],
                    ],
                ],
            ],
        ];
    }

}
