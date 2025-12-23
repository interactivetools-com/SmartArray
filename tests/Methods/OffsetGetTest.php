<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for array index access: $arr['key']
 *
 * Note: SmartArray extends ArrayObject with ARRAY_AS_PROPS flag, so both
 * ['key'] and ->key currently route through offsetGet(). These tests are
 * kept separate from PropertyGetTest for future refactoring flexibility.
 *
 * @see PropertyGetTest for property syntax ($arr->key)
 */
class OffsetGetTest extends SmartArrayTestCase
{

    /**
     * @dataProvider offsetGetProvider
     */
    public function testOffsetGet(array $initialData, callable $get, mixed $expected): void
    {
        $array   = new SmartArray($initialData);
        $arraySS = new SmartArrayHtml($initialData);

        // Capture any warnings
        ob_start();
        $result   = $get($array);
        $resultSS = $get($arraySS);
        ob_end_clean();

        // Compare Raw
        $this->assertSame(
            expected: $expected,
            actual  : $this->normalizeRaw($result),
        );

        // Compare SS
        $this->assertSame(
            expected: $this->htmlEncode($expected),
            actual  : $this->normalizeSS($resultSS)
        );
    }

    public static function offsetGetProvider(): array
    {
        return [
            'scalar value' => [
                'initialData' => ['name' => 'Alice'],
                'get'         => fn($array) => $array['name'],
                'expected'    => 'Alice',
            ],
            'array value' => [
                'initialData' => ['address' => ['city' => 'New York', 'zip' => '10001']],
                'get'         => fn($array) => $array['address'],
                'expected'    => ['city' => 'New York', 'zip' => '10001'],
            ],
            'integer key' => [
                'initialData' => [42 => 'answer'],
                'get'         => fn($array) => $array[42],
                'expected'    => 'answer',
            ],
            'numeric string key' => [
                'initialData' => ['123' => 'numeric string key'],
                'get'         => fn($array) => $array['123'],
                'expected'    => 'numeric string key',
            ],
            'special characters in key' => [
                'initialData' => ['key-with-dash' => 'special key'],
                'get'         => fn($array) => $array['key-with-dash'],
                'expected'    => 'special key',
            ],
            'empty string key' => [
                'initialData' => ['' => 'empty key'],
                'get'         => fn($array) => $array[''],
                'expected'    => 'empty key',
            ],
            'nested array chained access' => [
                'initialData' => ['nested' => ['key' => 'value']],
                'get'         => fn($array) => $array['nested']['key'],
                'expected'    => 'value',
            ],
            'first element with numeric index' => [
                'initialData' => ['first', 'second', 'third'],
                'get'         => fn($array) => $array[0],
                'expected'    => 'first',
            ],
            'last element with calculated index' => [
                'initialData' => ['first', 'second', 'third'],
                'get'         => fn($array) => $array[count($array) - 1],
                'expected'    => 'third',
            ],
            'non-existent key returns SmartNull' => [
                'initialData' => ['name' => 'Alice'],
                'get'         => fn($array) => $array['age'],
                'expected'    => null,
            ],
            'null value' => [
                'initialData' => ['nothing' => null],
                'get'         => fn($array) => $array['nothing'],
                'expected'    => null,
            ],
            'boolean value' => [
                'initialData' => ['isActive' => true],
                'get'         => fn($array) => $array['isActive'],
                'expected'    => true,
            ],
            'float value' => [
                'initialData' => ['pi' => 3.14],
                'get'         => fn($array) => $array['pi'],
                'expected'    => 3.14,
            ],
        ];
    }

}
