<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::implode() method.
 *
 * implode($separator) joins all elements into a string.
 * Returns string (SmartArrayRaw) or SmartString (SmartArrayHtml).
 * Throws InvalidArgumentException for nested arrays.
 */
class ImplodeTest extends SmartArrayTestCase
{

    /**
     * @dataProvider implodeProvider
     */
    public function testImplode(array $input, string $separator, string $expected, bool $shouldThrowException = false): void
    {
        $smartArray = new SmartArray($input);

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a flat array, but got a nested array");
            /** @noinspection UnusedFunctionResultInspection */
            $smartArray->implode($separator);
            return;
        }

        $actual = $smartArray->implode($separator);
        $this->assertSame($expected, $actual);
    }

    public static function implodeProvider(): array
    {
        return [
            'empty array' => [
                'input'     => [],
                'separator' => ', ',
                'expected'  => '',
            ],
            'flat string array' => [
                'input'     => ['apple', 'banana', 'cherry'],
                'separator' => ', ',
                'expected'  => 'apple, banana, cherry',
            ],
            'with comma separator' => [
                'input'     => ['apple', 'banana', 'cherry'],
                'separator' => ', ',
                'expected'  => 'apple, banana, cherry',
            ],
            'with space separator' => [
                'input'     => ['apple', 'banana', 'cherry'],
                'separator' => ' ',
                'expected'  => 'apple banana cherry',
            ],
            'with hyphen separator' => [
                'input'     => ['apple', 'banana', 'cherry'],
                'separator' => ' - ',
                'expected'  => 'apple - banana - cherry',
            ],
            'with special characters' => [
                'input'     => ['He said "Hello"', "It's a test", 'Line1\nLine2', 'Comma, separated'],
                'separator' => '; ',
                'expected'  => 'He said "Hello"; It\'s a test; Line1\nLine2; Comma, separated',
            ],
            'single element' => [
                'input'     => ['onlyOne'],
                'separator' => ', ',
                'expected'  => 'onlyOne',
            ],
            'numeric elements' => [
                'input'     => [100, 200.5, 300],
                'separator' => '-',
                'expected'  => '100-200.5-300',
            ],
            'nested array throws exception' => [
                'input'                => [
                    ['apple', 'banana'],
                    ['cherry', 'date'],
                ],
                'separator'            => ', ',
                'expected'             => '',
                'shouldThrowException' => true,
            ],
        ];
    }

}
