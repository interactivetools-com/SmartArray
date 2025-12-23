<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::sprintf() method.
 *
 * sprintf($format) formats each element using sprintf.
 * Supports both standard sprintf placeholders (%s, %1$s, %2$s) and
 * named aliases ({value}, {key}).
 */
class SprintfTest extends SmartArrayTestCase
{

    /**
     * @dataProvider sprintfProvider
     */
    public function testSprintf(array $input, string $format, array $expected, bool $useSmartStrings = false): void
    {
        $smartArray = $useSmartStrings
            ? new SmartArrayHtml($input)
            : new SmartArray($input);
        $result = $smartArray->sprintf($format);

        $this->assertInstanceOf(SmartArrayBase::class, $result);
        $this->assertSame($expected, $result->toArray());
    }

    public static function sprintfProvider(): array
    {
        return [
            // Basic %s usage (value only)
            'raw strings with %s' => [
                'input'           => ['apple', 'banana'],
                'format'          => '<li>%s</li>',
                'expected'        => ['<li>apple</li>', '<li>banana</li>'],
                'useSmartStrings' => false,
            ],

            // HTML encoding with SmartArrayHtml
            'html encoding' => [
                'input'           => ['<script>', '<b>bold</b>'],
                'format'          => '<td>%s</td>',
                'expected'        => ['<td>&lt;script&gt;</td>', '<td>&lt;b&gt;bold&lt;/b&gt;</td>'],
                'useSmartStrings' => true,
            ],

            // Keys are preserved in output
            'keys preserved' => [
                'input'           => ['a' => 'apple', 'b' => 'banana'],
                'format'          => '%s!',
                'expected'        => ['a' => 'apple!', 'b' => 'banana!'],
                'useSmartStrings' => false,
            ],

            // {value} alias
            '{value} alias' => [
                'input'           => ['apple', 'banana'],
                'format'          => '<li>{value}</li>',
                'expected'        => ['<li>apple</li>', '<li>banana</li>'],
                'useSmartStrings' => false,
            ],

            // {key} alias with string keys
            '{key} alias with string keys' => [
                'input'           => ['us' => 'United States', 'ca' => 'Canada'],
                'format'          => '<option value="{key}">{value}</option>',
                'expected'        => ['us' => '<option value="us">United States</option>', 'ca' => '<option value="ca">Canada</option>'],
                'useSmartStrings' => false,
            ],

            // {key} alias with numeric keys
            '{key} alias with numeric keys' => [
                'input'           => ['apple', 'banana'],
                'format'          => '<li data-index="{key}">{value}</li>',
                'expected'        => ['<li data-index="0">apple</li>', '<li data-index="1">banana</li>'],
                'useSmartStrings' => false,
            ],

            // sprintf positional syntax %1$s %2$s
            'sprintf positional %1$s %2$s' => [
                'input'           => ['us' => 'United States', 'ca' => 'Canada'],
                'format'          => '<option value="%2$s">%1$s</option>',
                'expected'        => ['us' => '<option value="us">United States</option>', 'ca' => '<option value="ca">Canada</option>'],
                'useSmartStrings' => false,
            ],

            // Repeated {key} in format
            'repeated {key}' => [
                'input'           => ['a' => 'Apple', 'b' => 'Banana'],
                'format'          => '<li id="item-{key}" data-key="{key}">{value}</li>',
                'expected'        => ['a' => '<li id="item-a" data-key="a">Apple</li>', 'b' => '<li id="item-b" data-key="b">Banana</li>'],
                'useSmartStrings' => false,
            ],

            // HTML encoding with {value} alias
            'html encoding with {value}' => [
                'input'           => ["O'Brien", '<script>'],
                'format'          => '<td>{value}</td>',
                'expected'        => ["<td>O&apos;Brien</td>", '<td>&lt;script&gt;</td>'],
                'useSmartStrings' => true,
            ],

            // Key with HTML encoding (key should not be encoded)
            'key not encoded' => [
                'input'           => ['<b>' => 'Bold'],
                'format'          => '<option value="{key}">{value}</option>',
                'expected'        => ['<b>' => '<option value="<b>">Bold</option>'],
                'useSmartStrings' => false,
            ],
        ];
    }

}
