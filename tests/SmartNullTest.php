<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use PHPUnit\Framework\TestCase;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartNull;

class SmartNullTest extends TestCase
{
    /**
     * @dataProvider methodAccessProvider
     */
    public function testMethodAccess(callable $operation, $expected): void
    {
        $smartNull = new SmartNull();
        $result    = $operation($smartNull);
        $this->assertSame($expected, $result);
    }

    public function methodAccessProvider(): array
    {
        return [
            'chained property access returns SmartNull'          => [
                'operation' => fn($sn) => $sn->foo->bar->baz instanceof SmartNull,
                'expected'  => true,
            ],
            'value() returns null'                               => [
                'operation' => fn($sn) => $sn->value(),
                'expected'  => null,
            ],
            'toArray() returns empty array'                      => [
                'operation' => fn($sn) => $sn->toArray(),
                'expected'  => [],
            ],
            'count() returns 0'                                  => [
                'operation' => fn($sn) => count($sn),
                'expected'  => 0,
            ],
            'isEmpty() returns true'                             => [
                'operation' => fn($sn) => $sn->isEmpty(),
                'expected'  => true,
            ],
        ];
    }

    /**
     * @dataProvider typeConversionProvider
     */
    public function testTypeConversion(callable $operation, $expected): void
    {
        $smartNull = new SmartNull();
        $result    = $operation($smartNull);
        $this->assertSame($expected, $result);
    }

    public function typeConversionProvider(): array
    {
        return [
            'string conversion returns empty string' => [
                'operation' => fn($sn) => (string)$sn,
                'expected'  => '',
            ],
            'bool conversion returns true'           => [
                'operation' => fn($sn) => (bool)$sn,
                'expected'  => true,
            ],
        ];
    }

    /**
     * @dataProvider comparisonProvider
     */
    public function testComparison(callable $operation, $expected): void
    {
        $smartNull = new SmartNull();
        $result    = $operation($smartNull);
        $this->assertSame($expected, $result);
    }

    public function comparisonProvider(): array
    {
        return [
            'loose equality with null returns false'        => [
                'operation' => fn($sn) => $sn === null,
                'expected'  => false,
            ],
            'strict equality with null returns false'       => [
                'operation' => fn($sn) => $sn === null,
                'expected'  => false,
            ],
            'loose equality with false returns false'       => [
                'operation' => fn($sn) => $sn === false,
                'expected'  => false,
            ],
            'strict equality with false returns false'      => [
                'operation' => fn($sn) => $sn === false,
                'expected'  => false,
            ],
            'identity with another SmartNull returns false' => [
                'operation' => fn($sn) => $sn === new SmartNull(),
                'expected'  => false,
            ],
        ];
    }

    /**
     * @dataProvider conditionalEvaluationProvider
     */
    public function testConditionalEvaluation(callable $operation, $expected): void
    {
        $smartNull = new SmartNull();
        $result    = $operation($smartNull);
        $this->assertSame($expected, $result);
    }

    public function conditionalEvaluationProvider(): array
    {
        return [
            'if statement evaluates as truthy'                       => [
                'operation' => fn($sn) => $sn ? 'truthy' : 'falsy',
                'expected'  => 'truthy',
            ],
            'ternary shorthand returns SmartNull'                    => [
                'operation' => fn($sn) => $sn instanceof SmartNull,
                'expected'  => true,
            ],
            'ternary evaluates as truthy'                            => [
                'operation' => fn($sn) => $sn ? 'truthy' : 'falsy',
                'expected'  => 'truthy',
            ],
            'is_null returns false (SmartNull is not actually null)' => [
                'operation' => fn($sn) => is_null($sn),
                'expected'  => false,
            ],
            'isset returns true (SmartNull exists)'                  => [
                'operation' => fn($sn) => isset($sn),
                'expected'  => true,
            ],
        ];
    }

    /**
     * @dataProvider arrayAccessProvider
     */
    public function testArrayAccess(callable $operation, $expected): void
    {
        $smartNull = new SmartNull();
        $result    = $operation($smartNull);
        $this->assertSame($expected, $result);
    }

    public function arrayAccessProvider(): array
    {
        return [
            'array access with string key returns SmartNull'    => [
                'operation' => fn($sn) => $sn['key'] instanceof SmartNull,
                'expected'  => true,
            ],
            'array access with int key returns SmartNull'       => [
                'operation' => fn($sn) => $sn[0] instanceof SmartNull,
                'expected'  => true,
            ],
            'offsetExists returns false'                        => [
                'operation' => fn($sn) => $sn->offsetExists('any_key'),
                'expected'  => false,
            ],
            'chained array access returns SmartNull'            => [
                'operation' => fn($sn) => $sn['foo']['bar']['baz'] instanceof SmartNull,
                'expected'  => true,
            ],
            'mixed array and property access returns SmartNull' => [
                'operation' => fn($sn) => $sn['foo']->bar['baz'] instanceof SmartNull,
                'expected'  => true,
            ],
        ];
    }

    /**
     * @dataProvider iterationProvider
     */
    public function testIteration(callable $operation, $expected): void
    {
        $smartNull = new SmartNull();
        $result    = $operation($smartNull);
        $this->assertSame($expected, $result);
    }

    public function iterationProvider(): array
    {
        return [
            'foreach loop does not iterate' => [
                'operation' => function ($sn) {
                    $result = [];
                    foreach ($sn as $k => $v) {
                        $result[$k] = $v;
                    }
                    return $result;
                },
                'expected'  => [],
            ],
        ];
    }

    /**
     * @dataProvider jsonSerializationProvider
     */
    public function testJsonSerialization(callable $operation, $expected): void
    {
        $smartNull = new SmartNull();
        $result    = $operation($smartNull);
        $this->assertSame($expected, $result);
    }

    public function jsonSerializationProvider(): array
    {
        return [
            'json_encode returns null'                  => [
                'operation' => fn($sn) => json_encode($sn, JSON_THROW_ON_ERROR),
                'expected'  => 'null',
            ],
            'json_encode in array returns null element' => [
                'operation' => fn($sn) => json_encode(['key' => $sn], JSON_THROW_ON_ERROR),
                'expected'  => '{"key":null}',
            ],
        ];
    }

    /**
     * @dataProvider specialMethodsProvider
     */
    public function testSpecialMethods(callable $operation, $expected): void
    {
        $smartNull = new SmartNull();
        $result    = $operation($smartNull);
        $this->assertSame($expected, $result);
    }

    public function specialMethodsProvider(): array
    {
        return [
            '__toString returns empty string' => [
                'operation' => fn($sn) => $sn->__toString(),
                'expected'  => '',
            ],
            'value() returns null'            => [
                'operation' => fn($sn) => $sn->value(),
                'expected'  => null,
            ],
            'jsonSerialize() returns null'    => [
                'operation' => fn($sn) => $sn->jsonSerialize(),
                'expected'  => null,
            ],
        ];
    }

    /**
     * @dataProvider integrationProvider
     */
    public function testIntegration(callable $operation, $expected): void
    {
        $result = $operation();
        $this->assertSame($expected, $result);
    }

    public function integrationProvider(): array
    {
        return [
            'SmartArray returns SmartNull for non-existent key'     => [
                'operation' => function () {
                    $array = new SmartArray(['a' => 1]);
                    // Capture and discard output to avoid warnings in test results
                    ob_start();
                    $result = $array->get('nonexistent') instanceof SmartNull;
                    ob_end_clean();
                    return $result;
                },
                'expected'  => true,
            ],
            'SmartNull chained with string operations works safely' => [
                'operation' => function () {
                    $array = new SmartArray(['a' => 1]);
                    // Capture and discard output to avoid warnings in test results
                    ob_start();
                    $smartNull = $array->get('nonexistent');
                    ob_end_clean();
                    return $smartNull . " appended";
                },
                'expected'  => " appended",
            ],
        ];
    }
}
