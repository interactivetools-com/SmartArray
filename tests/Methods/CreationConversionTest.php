<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use Itools\SmartString\SmartString;

/**
 * Tests for the primary SmartArray creation and conversion API.
 *
 * The Clean API:
 *   // Create
 *   new SmartArray($data)          // Raw (default)
 *   new SmartArrayHtml($data)      // HTML-safe
 *
 *   // For chaining (pre-PHP 8.5)
 *   SmartArray::new($data)->filter(...)
 *   SmartArrayHtml::new($data)->filter(...)
 *
 *   // Convert
 *   $arr->asHtml()                 // → SmartArrayHtml
 *   $arr->asRaw()                  // → SmartArray
 */
class CreationConversionTest extends SmartArrayTestCase
{

    //region new SmartArray() - Raw constructor (base class, raw behavior)

    public function testNewSmartArrayIsRawByDefault(): void
    {
        $arr = new SmartArray(['name' => 'John', 'age' => 30]);

        $this->assertInstanceOf(SmartArray::class, $arr);
        $this->assertFalse($arr->usingSmartStrings(), 'new SmartArray() should be raw by default');
    }

    public function testNewSmartArrayReturnsRawValues(): void
    {
        $arr = new SmartArray(['name' => '<script>alert("xss")</script>']);

        // Values should be raw strings, not SmartStrings
        $value = $arr['name'];
        $this->assertIsString($value);
        $this->assertNotInstanceOf(SmartString::class, $value);
        $this->assertSame('<script>alert("xss")</script>', $value);
    }

    public function testNewSmartArrayWithEmptyArray(): void
    {
        $arr = new SmartArray([]);

        $this->assertInstanceOf(SmartArray::class, $arr);
        $this->assertFalse($arr->usingSmartStrings());
        $this->assertCount(0, $arr);
    }

    public function testNewSmartArrayWithNestedArrays(): void
    {
        $arr = new SmartArray([
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
        ]);

        $this->assertInstanceOf(SmartArray::class, $arr);
        $this->assertInstanceOf(SmartArray::class, $arr[0]);
        $this->assertSame('First', $arr[0]['name']);
    }

    //endregion
    //region new SmartArrayHtml() - HTML-safe constructor

    public function testNewSmartArrayHtmlReturnsSmartArrayHtml(): void
    {
        $arr = new SmartArrayHtml(['name' => 'John', 'age' => 30]);

        $this->assertInstanceOf(SmartArrayHtml::class, $arr);
        $this->assertTrue($arr->usingSmartStrings());
    }

    public function testNewSmartArrayHtmlReturnsSmartStrings(): void
    {
        $arr = new SmartArrayHtml(['name' => '<script>alert("xss")</script>']);

        // Values should be SmartStrings
        $value = $arr['name'];
        $this->assertInstanceOf(SmartString::class, $value);

        // String context should be HTML-encoded
        $this->assertSame('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', (string) $value);

        // Raw value should be original
        $this->assertSame('<script>alert("xss")</script>', $value->value());
    }

    public function testNewSmartArrayHtmlWithEmptyArray(): void
    {
        $arr = new SmartArrayHtml([]);

        $this->assertInstanceOf(SmartArrayHtml::class, $arr);
        $this->assertCount(0, $arr);
    }

    public function testNewSmartArrayHtmlWithNestedArrays(): void
    {
        $arr = new SmartArrayHtml([
            ['id' => 1, 'name' => '<b>First</b>'],
            ['id' => 2, 'name' => '<i>Second</i>'],
        ]);

        $this->assertInstanceOf(SmartArrayHtml::class, $arr);
        $this->assertInstanceOf(SmartArrayHtml::class, $arr[0]);

        // Nested values should also be SmartStrings
        $this->assertInstanceOf(SmartString::class, $arr[0]['name']);
        $this->assertSame('&lt;b&gt;First&lt;/b&gt;', (string) $arr[0]['name']);
    }

    //endregion
    //region SmartArray::new() - Chaining helper

    public function testSmartArrayNewReturnsSmartArray(): void
    {
        $arr = SmartArray::new(['name' => 'John']);

        $this->assertInstanceOf(SmartArray::class, $arr);
        $this->assertFalse($arr->usingSmartStrings());
    }

    public function testSmartArrayNewAllowsChaining(): void
    {
        $result = SmartArray::new(['apple', 'banana', 'cherry'])
            ->filter(fn($v) => strlen($v) > 5)
            ->values()
            ->toArray();

        $this->assertSame(['banana', 'cherry'], $result);
    }

    public function testSmartArrayNewWithEmptyArray(): void
    {
        $arr = SmartArray::new([]);

        $this->assertInstanceOf(SmartArray::class, $arr);
        $this->assertCount(0, $arr);
    }

    //endregion
    //region SmartArrayHtml::new() - Chaining helper

    public function testSmartArrayHtmlNewReturnsSmartArrayHtml(): void
    {
        $arr = SmartArrayHtml::new(['name' => 'John']);

        $this->assertInstanceOf(SmartArrayHtml::class, $arr);
        $this->assertTrue($arr->usingSmartStrings());
    }

    public function testSmartArrayHtmlNewAllowsChaining(): void
    {
        $result = SmartArrayHtml::new([
            ['name' => 'John', 'active' => true],
            ['name' => 'Jane', 'active' => false],
            ['name' => 'Bob', 'active' => true],
        ])
            ->where('active', true)
            ->pluck('name')
            ->toArray();

        $this->assertSame(['John', 'Bob'], $result);
    }

    public function testSmartArrayHtmlNewWithEmptyArray(): void
    {
        $arr = SmartArrayHtml::new([]);

        $this->assertInstanceOf(SmartArrayHtml::class, $arr);
        $this->assertCount(0, $arr);
    }

    //endregion
    //region ->asHtml() - Convert to HTML-safe

    public function testAsHtmlConvertsToSmartArrayHtml(): void
    {
        $raw  = new SmartArray(['name' => 'John']);
        $html = $raw->asHtml();

        $this->assertInstanceOf(SmartArrayHtml::class, $html);
        $this->assertTrue($html->usingSmartStrings());
    }

    public function testAsHtmlReturnsNewInstance(): void
    {
        $raw  = new SmartArray(['name' => 'John']);
        $html = $raw->asHtml();

        // Original should be unchanged (still raw behavior)
        $this->assertFalse($raw->usingSmartStrings());

        // New instance should be HTML
        $this->assertNotSame($raw, $html);
        $this->assertInstanceOf(SmartArrayHtml::class, $html);
    }

    public function testAsHtmlReturnsSameInstanceIfAlreadyHtml(): void
    {
        $html1 = new SmartArrayHtml(['name' => 'John']);
        $html2 = $html1->asHtml();

        // Should return same instance (lazy conversion)
        $this->assertSame($html1, $html2);
    }

    public function testAsHtmlPreservesData(): void
    {
        $raw  = new SmartArray(['name' => '<b>John</b>', 'age' => 30]);
        $html = $raw->asHtml();

        $this->assertSame(['name' => '<b>John</b>', 'age' => 30], $html->toArray());
    }

    public function testAsHtmlWorksInChain(): void
    {
        $result = SmartArray::new([
            ['name' => 'John', 'score' => 85],
            ['name' => 'Jane', 'score' => 92],
        ])
            ->sortBy('score')
            ->asHtml()
            ->first();

        $this->assertInstanceOf(SmartArrayHtml::class, $result);
        $this->assertInstanceOf(SmartString::class, $result['name']);
    }

    //endregion
    //region ->asRaw() - Convert to raw values

    public function testAsRawConvertsToSmartArray(): void
    {
        $html = new SmartArrayHtml(['name' => 'John']);
        $raw  = $html->asRaw();

        $this->assertInstanceOf(SmartArray::class, $raw);
        $this->assertFalse($raw->usingSmartStrings());
    }

    public function testAsRawReturnsNewInstance(): void
    {
        $html = new SmartArrayHtml(['name' => 'John']);
        $raw  = $html->asRaw();

        // Original should be unchanged
        $this->assertInstanceOf(SmartArrayHtml::class, $html);
        $this->assertTrue($html->usingSmartStrings());

        // New instance should be raw
        $this->assertNotSame($html, $raw);
    }

    public function testAsRawReturnsSameInstanceIfAlreadyRaw(): void
    {
        $raw1 = SmartArray::new(['name' => 'John']); // SmartArray
        $raw2 = $raw1->asRaw();

        // Should return same instance (lazy conversion)
        $this->assertSame($raw1, $raw2);
    }

    public function testAsRawPreservesData(): void
    {
        $html = new SmartArrayHtml(['name' => '<b>John</b>', 'age' => 30]);
        $raw  = $html->asRaw();

        $this->assertSame(['name' => '<b>John</b>', 'age' => 30], $raw->toArray());
    }

    public function testAsRawWorksInChain(): void
    {
        $result = SmartArrayHtml::new([
            ['name' => 'John', 'score' => 85],
            ['name' => 'Jane', 'score' => 92],
        ])
            ->sortBy('score')
            ->asRaw()
            ->pluck('name')
            ->toArray();

        $this->assertSame(['John', 'Jane'], $result);
    }

    //endregion
    //region Round-trip conversions

    public function testRoundTripRawToHtmlToRaw(): void
    {
        $original = SmartArray::new(['name' => '<b>Test</b>', 'count' => 42]);
        $html     = $original->asHtml();
        $backRaw  = $html->asRaw();

        $this->assertSame($original->toArray(), $backRaw->toArray());
        $this->assertInstanceOf(SmartArray::class, $backRaw);
    }

    public function testRoundTripHtmlToRawToHtml(): void
    {
        $original = new SmartArrayHtml(['name' => '<b>Test</b>', 'count' => 42]);
        $raw      = $original->asRaw();
        $backHtml = $raw->asHtml();

        $this->assertSame($original->toArray(), $backHtml->toArray());
        $this->assertInstanceOf(SmartArrayHtml::class, $backHtml);
    }

    //endregion
    //region Type consistency across operations

    public function testSmartArrayMethodsReturnSmartArray(): void
    {
        $arr = SmartArray::new([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ]);

        // All transformation methods should return SmartArray
        $this->assertInstanceOf(SmartArray::class, $arr->filter(fn($r) => true));
        $this->assertInstanceOf(SmartArray::class, $arr->where('id', 1));
        $this->assertInstanceOf(SmartArray::class, $arr->sortBy('name'));
        $this->assertInstanceOf(SmartArray::class, $arr->indexBy('id'));
        $this->assertInstanceOf(SmartArray::class, $arr->pluck('name'));
        $this->assertInstanceOf(SmartArray::class, $arr->values());
        $this->assertInstanceOf(SmartArray::class, $arr->keys());
    }

    public function testSmartArrayHtmlMethodsReturnSmartArrayHtml(): void
    {
        $arr = new SmartArrayHtml([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ]);

        // All transformation methods should return SmartArrayHtml
        $this->assertInstanceOf(SmartArrayHtml::class, $arr->filter(fn($r) => true));
        $this->assertInstanceOf(SmartArrayHtml::class, $arr->where('id', 1));
        $this->assertInstanceOf(SmartArrayHtml::class, $arr->sortBy('name'));
        $this->assertInstanceOf(SmartArrayHtml::class, $arr->indexBy('id'));
        $this->assertInstanceOf(SmartArrayHtml::class, $arr->pluck('name'));
        $this->assertInstanceOf(SmartArrayHtml::class, $arr->values());
        $this->assertInstanceOf(SmartArrayHtml::class, $arr->keys());
    }

    //endregion
    //region where() shorthand syntax

    public function testWhereShorthandSyntaxWorksOnSmartArray(): void
    {
        $arr = SmartArray::new([
            ['status' => 'active', 'name' => 'John'],
            ['status' => 'inactive', 'name' => 'Jane'],
            ['status' => 'active', 'name' => 'Bob'],
        ]);

        $result = $arr->where('status', 'active')->pluck('name')->toArray();

        $this->assertSame(['John', 'Bob'], $result);
    }

    public function testWhereShorthandSyntaxWorksOnSmartArrayHtml(): void
    {
        $arr = new SmartArrayHtml([
            ['status' => 'active', 'name' => 'John'],
            ['status' => 'inactive', 'name' => 'Jane'],
            ['status' => 'active', 'name' => 'Bob'],
        ]);

        $result = $arr->where('status', 'active')->pluck('name')->toArray();

        $this->assertSame(['John', 'Bob'], $result);
    }

    //endregion
    //region Property preservation through conversions

    public function testAsHtmlPreservesLoadHandler(): void
    {
        $handlerCalled = false;
        $handler = function ($arr, $col) use (&$handlerCalled) {
            $handlerCalled = true;
            return [['loaded' => 'data'], []];
        };

        $raw = SmartArray::new(['id' => 1]);
        $raw->setLoadHandler($handler);

        $html = $raw->asHtml();
        $html->load('related');

        $this->assertTrue($handlerCalled, 'loadHandler should be preserved through asHtml()');
    }

    public function testAsRawPreservesLoadHandler(): void
    {
        $handlerCalled = false;
        $handler = function ($arr, $col) use (&$handlerCalled) {
            $handlerCalled = true;
            return [['loaded' => 'data'], []];
        };

        $html = SmartArrayHtml::new(['id' => 1]);
        $html->setLoadHandler($handler);

        $raw = $html->asRaw();
        $raw->load('related');

        $this->assertTrue($handlerCalled, 'loadHandler should be preserved through asRaw()');
    }

    public function testAsHtmlPreservesMysqliMetadata(): void
    {
        // Create array with mysqli metadata via constructor properties
        $raw = new SmartArray(['id' => 1], [
            'mysqli' => ['affected_rows' => 5, 'insert_id' => 42],
        ]);

        $html = $raw->asHtml();

        $this->assertSame(5, $html->mysqli('affected_rows'), 'mysqli metadata should be preserved through asHtml()');
        $this->assertSame(42, $html->mysqli('insert_id'), 'mysqli metadata should be preserved through asHtml()');
    }

    public function testAsRawPreservesMysqliMetadata(): void
    {
        // Create array with mysqli metadata via constructor properties
        $html = new SmartArrayHtml(['id' => 1], [
            'mysqli' => ['affected_rows' => 5, 'insert_id' => 42],
        ]);

        $raw = $html->asRaw();

        $this->assertSame(5, $raw->mysqli('affected_rows'), 'mysqli metadata should be preserved through asRaw()');
        $this->assertSame(42, $raw->mysqli('insert_id'), 'mysqli metadata should be preserved through asRaw()');
    }

    //endregion

}
