<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartArray\Tests\TestHelpers;

/**
 * Tests for SmartArray::isFirst() method.
 *
 * isFirst() returns true when a nested SmartArray element is the first element
 * in its parent array, false otherwise.
 */
class IsFirstTest extends SmartArrayTestCase
{

    /**
     * @dataProvider isFirstProvider
     */
    public function testIsFirst(array $input): void
    {
        $smartArray     = new SmartArray($input);
        $smartArrayData = $smartArray->getArrayCopy();
        $firstKey       = array_key_first($smartArrayData);
        $assertionMade  = false;

        foreach ($smartArray as $key => $value) {
            // Only nested SmartArrays have isFirst()
            if (!$value instanceof SmartArray) {
                continue;
            }

            $isFirstExpected = ($key === $firstKey);
            $this->assertSame(
                $isFirstExpected,
                $value->isFirst(),
                "Element at key '$key': isFirst() should be " . ($isFirstExpected ? 'true' : 'false')
            );
            $assertionMade = true;
        }

        // Avoid "no assertions" warning for arrays without nested SmartArrays
        if (!$assertionMade) {
            $this->assertTrue(true, "No nested SmartArray elements to test isFirst()");
        }
    }

    public static function isFirstProvider(): array
    {
        return [
            'empty array' => [
                [],
            ],
            'single element array' => [
                [TestHelpers::getTestRecord()],
            ],
            'multiple elements array' => [
                TestHelpers::getTestRecords(),
            ],
            'non-sequential integer keys' => [
                [
                    20 => ['data' => 'second'],
                    10 => ['data' => 'first'],
                    30 => ['data' => 'third'],
                ],
            ],
            'associative array with string keys' => [
                [
                    'first'  => ['data' => 'first'],
                    'middle' => ['data' => 'middle'],
                    'last'   => ['data' => 'last'],
                ],
            ],
            'mixed elements with nested arrays at start and end' => [
                [
                    'a' => ['data' => 'first'],
                    'b' => 'string value',
                    'c' => 123,
                    'd' => null,
                    'e' => ['data' => 'last'],
                ],
            ],
            'mixed elements with nested array not at end' => [
                [
                    'a' => ['data' => 'first'],
                    'b' => 'string value',
                    'c' => 123,
                    'e' => ['data' => 'last'],
                    'd' => null,
                ],
            ],
            'no nested arrays (all scalars)' => [
                [
                    'x' => 'string value',
                    'y' => 123,
                    'z' => null,
                ],
            ],
            'deeply nested structure' => [
                [
                    'nested' => TestHelpers::getTestRecords(),
                    'single' => [TestHelpers::getTestRecord()],
                ],
            ],
        ];
    }

}
