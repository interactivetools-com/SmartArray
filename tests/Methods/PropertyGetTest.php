<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for property access syntax: $arr->key
 *
 * Note: SmartArray extends ArrayObject with ARRAY_AS_PROPS flag, so both
 * ['key'] and ->key currently route through offsetGet(). These tests are
 * kept separate from OffsetGetTest for future refactoring flexibility.
 *
 * @see OffsetGetTest for array index syntax ($arr['key'])
 */
class PropertyGetTest extends SmartArrayTestCase
{

    /**
     * @dataProvider propertyGetProvider
     */
    public function testPropertyGet(array $initialData, callable $get, mixed $expected): void
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

    public static function propertyGetProvider(): array
    {
        return [
            'scalar value' => [
                'initialData' => ['age' => 30],
                'get'         => fn($array) => $array->age,
                'expected'    => 30,
            ],
            'string value' => [
                'initialData' => ['name' => 'Alice'],
                'get'         => fn($array) => $array->name,
                'expected'    => 'Alice',
            ],
            'array value' => [
                'initialData' => ['contact' => ['email' => 'alice@example.com', 'phone' => '123-456-7890']],
                'get'         => fn($array) => $array->contact,
                'expected'    => ['email' => 'alice@example.com', 'phone' => '123-456-7890'],
            ],
            'nested SmartArray' => [
                'initialData' => ['nested' => ['a' => 1, 'b' => 2]],
                'get'         => fn($array) => $array->nested,
                'expected'    => ['a' => 1, 'b' => 2],
            ],
            'chained property access' => [
                'initialData' => ['nested' => ['key' => 'value']],
                'get'         => fn($array) => $array->nested->key,
                'expected'    => 'value',
            ],
            'deeply chained property access' => [
                'initialData' => ['level1' => ['level2' => ['level3' => 'deep']]],
                'get'         => fn($array) => $array->level1->level2->level3,
                'expected'    => 'deep',
            ],
            'non-existent key returns SmartNull' => [
                'initialData' => ['name' => 'Alice'],
                'get'         => fn($array) => $array->age,
                'expected'    => null,
            ],
            'null value' => [
                'initialData' => ['nothing' => null],
                'get'         => fn($array) => $array->nothing,
                'expected'    => null,
            ],
            'boolean value' => [
                'initialData' => ['isActive' => true],
                'get'         => fn($array) => $array->isActive,
                'expected'    => true,
            ],
            'float value' => [
                'initialData' => ['pi' => 3.14],
                'get'         => fn($array) => $array->pi,
                'expected'    => 3.14,
            ],
            'underscore in key' => [
                'initialData' => ['user_name' => 'bob'],
                'get'         => fn($array) => $array->user_name,
                'expected'    => 'bob',
            ],
        ];
    }

}
