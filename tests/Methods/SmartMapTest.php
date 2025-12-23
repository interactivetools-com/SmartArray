<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartString\SmartString;

/**
 * Tests for SmartArray::smartMap() method.
 *
 * smartMap($callback) transforms each element using a callback.
 * Unlike map(), callback receives SmartString/SmartArray objects (when SmartStrings enabled).
 * Returns a new SmartArray (immutable).
 */
class SmartMapTest extends SmartArrayTestCase
{

    /**
     * @dataProvider smartMapProvider
     */
    public function testSmartMap(array $input, callable $callback, array $expected, bool $useSmartStrings = true): void
    {
        $smartArray = new SmartArray($input);

        if ($useSmartStrings) {
            $smartArray = $smartArray->enableSmartStrings();
        }

        $originalArray = $smartArray->toArray();
        $mapped        = $smartArray->smartMap($callback);

        $this->assertEquals($expected, $mapped->toArray(), "SmartMap result doesn't match expected output");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified");
        $this->assertInstanceOf(SmartArrayBase::class, $mapped, "smartMap() should return a SmartArrayBase");
    }

    public static function smartMapProvider(): array
    {
        return [
            'empty array' => [
                'input'    => [],
                'callback' => fn($value, $key) => $value,
                'expected' => [],
            ],
            'flat array with SmartString operation' => [
                'input'    => ['hello', 'world'],
                'callback' => fn($value, $key) => strtoupper($value->value()),
                'expected' => ['HELLO', 'WORLD'],
            ],
            'flat array with keys in transform' => [
                'input'    => ['a' => 'hello', 'b' => 'world'],
                'callback' => fn($value, $key) => "$key: $value",
                'expected' => ['a' => 'a: hello', 'b' => 'b: world'],
            ],
            'mixed types with smart transformations' => [
                'input'    => ['text' => 'hello', 'num' => 42, 'bool' => true],
                'callback' => function ($value, $key) {
                    if ($value instanceof SmartString && is_string($value->value())) {
                        return strtoupper($value->value());
                    }
                    return "Value: $value";
                },
                'expected' => ['text' => 'HELLO', 'num' => 'Value: 42', 'bool' => 'Value: 1'],
            ],
            'nested array access' => [
                'input'    => [
                    ['name' => 'John', 'email' => 'john@example.com'],
                    ['name' => 'Jane', 'email' => 'jane@example.com'],
                ],
                'callback' => fn($row, $key) => strtoupper($row->name->value()) . ' - ' . $row->email,
                'expected' => ['JOHN - john@example.com', 'JANE - jane@example.com'],
            ],
            'HTML handling' => [
                'input'           => ['<p>Hello</p>', '<div>World</div>'],
                'callback'        => fn($value, $key) => $value->value(),
                'expected'        => ['<p>Hello</p>', '<div>World</div>'],
                'useSmartStrings' => true,
            ],
            'without SmartStrings enabled' => [
                'input'           => ['hello', 'world'],
                'callback'        => fn($value, $key) => strtoupper($value),
                'expected'        => ['HELLO', 'WORLD'],
                'useSmartStrings' => false,
            ],
        ];
    }

}
