<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartArray\Tests\TestHelpers;

/**
 * Tests for SmartArray::isLast() method.
 *
 * isLast() returns true when a nested SmartArray element is the last element
 * in its parent array, false otherwise.
 */
class IsLastTest extends SmartArrayTestCase
{

    /**
     * @dataProvider isLastProvider
     */
    public function testIsLast(array $input): void
    {
        $smartArray     = new SmartArray($input);
        $smartArrayData = $smartArray->getArrayCopy();
        $lastKey        = array_key_last($smartArrayData);
        $assertionMade  = false;

        foreach ($smartArray as $key => $value) {
            // Only nested SmartArrays have isLast()
            if (!$value instanceof SmartArray) {
                continue;
            }

            $isLastExpected = ($key === $lastKey);
            $this->assertSame(
                $isLastExpected,
                $value->isLast(),
                "Element at key '$key': isLast() should be " . ($isLastExpected ? 'true' : 'false')
            );
            $assertionMade = true;
        }

        // Avoid "no assertions" warning for arrays without nested SmartArrays
        if (!$assertionMade) {
            $this->assertTrue(true, "No nested SmartArray elements to test isLast()");
        }
    }

    public static function isLastProvider(): array
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
                    'e' => ['data' => 'middle'],
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
