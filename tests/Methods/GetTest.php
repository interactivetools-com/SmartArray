<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartArray\Tests\TestHelpers;
use Itools\SmartString\SmartString;

/**
 * Tests for SmartArray::get() method.
 *
 * get($key, $default) retrieves a value by key, with optional default if key doesn't exist.
 */
class GetTest extends SmartArrayTestCase
{

    public function testGetDefaults(): void
    {
        $expected = "Bob";
        $default  = "Unknown Name";

        // Test defaults are ignored when key exists
        $smartArrays = [
            new SmartArray(['name' => $expected, 'city' => 'Springfield']),
            new SmartArrayHtml(['name' => $expected, 'city' => 'Springfield']),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value;
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertSame($expected, $actual);
        }

        // Test defaults are ignored when key exists but is null
        $smartArrays = [
            new SmartArray(['name' => null, 'city' => 'Springfield']),
            new SmartArrayHtml(['name' => null, 'city' => 'Springfield']),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value;
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertNull($actual);
        }

        // Test defaults are used when key doesn't exist
        $smartArrays = [
            new SmartArray(['name2' => $expected, 'city' => 'Springfield']),
            new SmartArrayHtml(['name2' => $expected, 'city' => 'Springfield']),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value;
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertSame($default, $actual);
        }

        // Test defaults are used when array is empty
        $smartArrays = [
            new SmartArray(),
            new SmartArrayHtml(),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value;
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertSame($default, $actual);
        }
    }

    /**
     * @dataProvider getProvider
     */
    public function testGet(array $initialData, string|int $key, mixed $expected): void
    {
        $array   = new SmartArray($initialData);
        $arraySS = new SmartArrayHtml($initialData);

        // Capture any warnings output by SmartArray::get()
        ob_start();
        $result   = $array->get($key);
        $resultSS = $arraySS->get($key);
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

    public static function getProvider(): array
    {
        return [
            'get existing key' => [
                'initialData' => ['name' => 'Alice', 'age' => 30],
                'key'         => 'name',
                'expected'    => 'Alice',
            ],
            'get non-existing key' => [
                'initialData' => ['name' => 'Alice'],
                'key'         => 'email',
                'expected'    => null,
            ],
            'get existing key with value null' => [
                'initialData' => ['name' => 'Alice', 'nickname' => null],
                'key'         => 'nickname',
                'expected'    => null,
            ],
            'get nested array value' => [
                'initialData' => ['user' => ['id' => 1, 'name' => 'Alice']],
                'key'         => 'user',
                'expected'    => ['id' => 1, 'name' => 'Alice'],
            ],
            'get non-existing key from empty array' => [
                'initialData' => [],
                'key'         => 'key',
                'expected'    => null,
            ],
            'get existing key with boolean false' => [
                'initialData' => ['isActive' => false],
                'key'         => 'isActive',
                'expected'    => false,
            ],
            'get existing key with value zero' => [
                'initialData' => ['count' => 0],
                'key'         => 'count',
                'expected'    => 0,
            ],
            'get existing key with empty string' => [
                'initialData' => ['title' => ''],
                'key'         => 'title',
                'expected'    => '',
            ],
            'get existing key with integer key' => [
                'initialData' => [0 => 'zero', 1 => 'one'],
                'key'         => 1,
                'expected'    => 'one',
            ],
            'get non-existing key with integer key' => [
                'initialData' => [0 => 'zero', 1 => 'one'],
                'key'         => 2,
                'expected'    => null,
            ],
            'get existing key with numeric string key' => [
                'initialData' => ['123' => 'numeric key'],
                'key'         => '123',
                'expected'    => 'numeric key',
            ],
            'get existing key with special characters in key' => [
                'initialData' => ['key-with-dash' => 'special key'],
                'key'         => 'key-with-dash',
                'expected'    => 'special key',
            ],
            'get existing key with empty string key' => [
                'initialData' => ['' => 'empty key'],
                'key'         => '',
                'expected'    => 'empty key',
            ],
            'get existing key where value is array' => [
                'initialData' => ['data' => ['item1', 'item2']],
                'key'         => 'data',
                'expected'    => ['item1', 'item2'],
            ],
        ];
    }

}
