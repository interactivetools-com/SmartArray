<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayRaw;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::sprintf() method.
 *
 * sprintf($format) formats each element using sprintf.
 * When SmartStrings are enabled, values are HTML-encoded before formatting.
 */
class SprintfTest extends SmartArrayTestCase
{

    /**
     * @dataProvider sprintfProvider
     */
    public function testSprintf(array $input, string $format, array $expected, bool $useSmartStrings = false): void
    {
        $smartArray = new SmartArray($input, ['useSmartStrings' => $useSmartStrings]);
        $result     = $smartArray->sprintf($format);

        $this->assertInstanceOf(SmartArrayRaw::class, $result);
        $this->assertSame($expected, $result->toArray());
    }

    public static function sprintfProvider(): array
    {
        return [
            'raw strings' => [
                'input'           => ['apple', 'banana'],
                'format'          => '<li>%s</li>',
                'expected'        => ['<li>apple</li>', '<li>banana</li>'],
                'useSmartStrings' => false,
            ],
            'html encoding' => [
                'input'           => ['<script>', '<b>bold</b>'],
                'format'          => '<td>%s</td>',
                'expected'        => ['<td>&lt;script&gt;</td>', '<td>&lt;b&gt;bold&lt;/b&gt;</td>'],
                'useSmartStrings' => true,
            ],
            'keys preserved' => [
                'input'           => ['a' => 'apple', 'b' => 'banana'],
                'format'          => '%s!',
                'expected'        => ['a' => 'apple!', 'b' => 'banana!'],
                'useSmartStrings' => false,
            ],
        ];
    }

}
