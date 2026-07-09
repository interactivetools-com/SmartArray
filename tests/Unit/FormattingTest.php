<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * implode() and sprintf() - the two methods with mode-dependent encoding
 * strategies, deliberately opposite (documented in the docblocks):
 *
 * - implode() defers: SmartArray returns a plain string of raw values;
 *   SmartArrayHtml returns a SmartString that encodes at output.
 * - sprintf() encodes eagerly (SmartArrayHtml only) and always returns a raw
 *   SmartArray so pre-formatted HTML is never re-encoded downstream.
 */
class FormattingTest extends SmartArrayTestCase
{
    //region implode()

    public function testImplodeRawModeReturnsPlainString(): void
    {
        $result = SmartArray::new(["O'Brien", '<b>', 'plain'])->implode(', ');

        $this->assertSame("O'Brien, <b>, plain", $result, 'raw mode: plain string, nothing encoded');
    }

    public function testImplodeHtmlModeReturnsSmartStringThatEncodesAtOutput(): void
    {
        // The headline behavior the old suite never tested
        $result = SmartArrayHtml::new(["O'Brien", '<b>'])->implode(', ');

        $this->assertInstanceOf(SmartString::class, $result);
        $this->assertSame("O'Brien, <b>", $result->value(), 'underlying value stays raw');
        $this->assertSame('O&apos;Brien, &lt;b&gt;', (string) $result, 'encoding happens at output');
    }

    #[DataProvider('modeProvider')]
    public function testImplodeStringifiesNullsAndBools(string $class): void
    {
        $result = $class::new(['a', null, true, false, 'b'])->implode('|');
        $value  = $result instanceof SmartString ? $result->value() : $result;

        $this->assertSame('a||1||b', $value, "null and false become '', true becomes '1' (PHP strval)");
    }

    #[DataProvider('modeProvider')]
    public function testImplodeSeparatorDefaultsToEmptyString(string $class): void
    {
        $result = $class::new(['a', 'b', 'c'])->implode();
        $value  = $result instanceof SmartString ? $result->value() : $result;

        $this->assertSame('abc', $value);
    }

    #[DataProvider('modeProvider')]
    public function testImplodeOnEmptyReturnsEmptyString(string $class): void
    {
        $result = $class::new([])->implode(', ');
        $value  = $result instanceof SmartString ? $result->value() : $result;

        $this->assertSame('', $value);
    }

    #[DataProvider('modeProvider')]
    public function testImplodeOnNestedThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('implode(): Expected a flat array, but got a nested array');

        $class::new([['a' => 1]])->implode(', ');
    }

    //endregion
    //region sprintf()

    public function testSprintfRawModeDoesNotEncode(): void
    {
        $result = SmartArray::new(["O'Brien", '<script>'])->sprintf('<td>{value}</td>');

        $this->assertSame(["<td>O'Brien</td>", '<td><script></td>'], $result->toArray());
    }

    public function testSprintfHtmlModeEncodesValuesEagerly(): void
    {
        $result = SmartArrayHtml::new(["O'Brien", '<script>'])->sprintf('<td>{value}</td>');

        $this->assertSame(['<td>O&apos;Brien</td>', '<td>&lt;script&gt;</td>'], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testSprintfAlwaysReturnsRawSmartArray(string $class): void
    {
        // Pre-formatted HTML must not re-encode in later operations
        $result = $class::new(['x'])->sprintf('<td>{value}</td>');

        $this->assertSame(SmartArray::class, get_class($result), 'raw SmartArray even from SmartArrayHtml');
    }

    public function testSprintfImplodeChainDoesNotDoubleEncode(): void
    {
        // The table recipe: encode once in sprintf, join without re-encoding
        $html = SmartArrayHtml::new(["O'Brien", '<b>'])->sprintf('<td>{value}</td>')->implode('');

        $this->assertSame("<td>O&apos;Brien</td><td>&lt;b&gt;</td>", $html);
    }

    #[DataProvider('modeProvider')]
    public function testSprintfKeyPlaceholderAndPositionalSyntax(string $class): void
    {
        $sa = $class::new(['name' => 'Bob', 'age' => 30]);

        $withAliases = $sa->sprintf('{key}={value}');
        $this->assertSame(['name' => 'name=Bob', 'age' => 'age=30'], $withAliases->toArray(), 'result keeps original keys');

        $positional = $sa->sprintf('%2$s: %1$s');
        $this->assertSame(['name' => 'name: Bob', 'age' => 'age: 30'], $positional->toArray(), '{value}/{key} are aliases for %1$s/%2$s');
    }

    public function testSprintfHtmlModeEncodesKeysInPlaceholders(): void
    {
        // Keys inserted via {key} encode in HTML mode; the result array's own
        // keys stay as stored
        $result = SmartArrayHtml::new(['<k>' => '<v>'])->sprintf('{key}={value}');

        $this->assertSame(['<k>' => '&lt;k&gt;=&lt;v&gt;'], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testSprintfSupportsNumberFormatting(string $class): void
    {
        $result = $class::new([7, 42])->sprintf('%05d');

        $this->assertSame(['00007', '00042'], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testSprintfOnNestedThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sprintf(): Expected a flat array, but got a nested array');

        $class::new([['a' => 1]])->sprintf('{value}');
    }

    //endregion
}
