<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\SmartArrayRaw;

/**
 * Tests for deprecated/legacy method aliases in SmartArray.
 *
 * These methods are maintained for backwards compatibility but
 * will trigger deprecation warnings when $logDeprecations is enabled.
 *
 * Purpose: Ensure legacy code continues to work during migration.
 */
class LegacyMethodsTest extends SmartArrayTestCase
{

    private bool $originalLogDeprecations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLogDeprecations = SmartArray::$logDeprecations;
    }

    protected function tearDown(): void
    {
        SmartArray::$logDeprecations = $this->originalLogDeprecations;
        parent::tearDown();
    }

    //region Static factory methods

    public function testNewSSReturnsSmartArrayHtml(): void
    {
        SmartArray::$logDeprecations = false;

        $result = SmartArray::newSS(['name' => 'John', 'age' => 30]);

        $this->assertInstanceOf(SmartArrayHtml::class, $result);
        $this->assertTrue($result->usingSmartStrings());
    }

    public function testNewSSWithEmptyArray(): void
    {
        SmartArray::$logDeprecations = false;

        $result = SmartArray::newSS([]);

        $this->assertInstanceOf(SmartArrayHtml::class, $result);
        $this->assertCount(0, $result);
    }

    public function testRawValueStaticAlias(): void
    {
        SmartArray::$logDeprecations = false;

        $result = SmartArray::rawValue('test string');

        $this->assertSame('test string', $result);
    }

    //endregion
    //region Instance method aliases via __call

    public function testExistsAliasForIsNotEmpty(): void
    {
        SmartArray::$logDeprecations = false;

        $nonEmpty = new SmartArray(['a', 'b', 'c']);
        $empty    = new SmartArray([]);

        $this->assertTrue($nonEmpty->exists());
        $this->assertFalse($empty->exists());
    }

    public function testFirstRowAliasForFirst(): void
    {
        SmartArray::$logDeprecations = false;

        $smartArray = new SmartArray(['first', 'second', 'third']);

        $this->assertSame('first', $smartArray->firstRow());
    }

    public function testGetFirstAliasForFirst(): void
    {
        SmartArray::$logDeprecations = false;

        $smartArray = new SmartArray(['first', 'second', 'third']);

        $this->assertSame('first', $smartArray->getFirst());
    }

    public function testGetValuesAliasForValues(): void
    {
        SmartArray::$logDeprecations = false;

        $smartArray = new SmartArray(['a' => 1, 'b' => 2, 'c' => 3]);
        $result     = $smartArray->getValues();

        $this->assertSame([1, 2, 3], $result->toArray());
    }

    public function testItemAliasForGet(): void
    {
        SmartArray::$logDeprecations = false;

        $smartArray = new SmartArray(['name' => 'John', 'age' => 30]);

        $this->assertSame('John', $smartArray->item('name'));
        $this->assertSame(30, $smartArray->item('age'));
    }

    public function testJoinAliasForImplode(): void
    {
        SmartArray::$logDeprecations = false;

        $smartArray = new SmartArray(['a', 'b', 'c']);

        $this->assertSame('a, b, c', $smartArray->join(', '));
    }

    public function testRawAliasForToArray(): void
    {
        SmartArray::$logDeprecations = false;

        $smartArray = new SmartArray(['name' => 'John', 'age' => 30]);

        $this->assertSame(['name' => 'John', 'age' => 30], $smartArray->raw());
    }

    //endregion
    //region SmartStrings toggle aliases

    public function testEnableSmartStringsReturnsSmartArrayHtml(): void
    {
        SmartArray::$logDeprecations = false;

        $smartArray = new SmartArray(['name' => 'John']);
        $result     = $smartArray->enableSmartStrings();

        $this->assertInstanceOf(SmartArrayHtml::class, $result);
        $this->assertTrue($result->usingSmartStrings());
    }

    public function testWithSmartStringsReturnsSmartArrayHtml(): void
    {
        SmartArray::$logDeprecations = false;

        $smartArray = new SmartArray(['name' => 'John']);
        $result     = $smartArray->withSmartStrings();

        $this->assertInstanceOf(SmartArrayHtml::class, $result);
        $this->assertTrue($result->usingSmartStrings());
    }

    public function testDisableSmartStringsReturnsSmartArrayRaw(): void
    {
        SmartArray::$logDeprecations = false;

        $smartArray = SmartArray::newSS(['name' => 'John']);
        $result     = $smartArray->disableSmartStrings();

        $this->assertInstanceOf(SmartArrayRaw::class, $result);
        $this->assertFalse($result->usingSmartStrings());
    }

    public function testNoSmartStringsReturnsSmartArrayRaw(): void
    {
        SmartArray::$logDeprecations = false;

        $smartArray = SmartArray::newSS(['name' => 'John']);
        $result     = $smartArray->noSmartStrings();

        $this->assertInstanceOf(SmartArrayRaw::class, $result);
        $this->assertFalse($result->usingSmartStrings());
    }

    //endregion
    //region Deprecation warnings

    public function testDeprecationWarningTriggeredWhenEnabled(): void
    {
        SmartArray::$logDeprecations = true;

        $deprecationTriggered = false;
        $previousHandler = set_error_handler(function ($errno, $errstr) use (&$deprecationTriggered) {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationTriggered = true;
            }
            return true;
        });

        try {
            $smartArray = new SmartArray(['a', 'b', 'c']);
            $smartArray->exists();

            $this->assertTrue($deprecationTriggered, 'Deprecation warning should be triggered when $logDeprecations is true');
        } finally {
            restore_error_handler();
        }
    }

    public function testNoDeprecationWarningWhenDisabled(): void
    {
        SmartArray::$logDeprecations = false;

        $deprecationTriggered = false;
        $previousHandler = set_error_handler(function ($errno, $errstr) use (&$deprecationTriggered) {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationTriggered = true;
            }
            return true;
        });

        try {
            $smartArray = new SmartArray(['a', 'b', 'c']);
            $smartArray->exists();

            $this->assertFalse($deprecationTriggered, 'No deprecation warning should be triggered when $logDeprecations is false');
        } finally {
            restore_error_handler();
        }
    }

    public function testNewSSTriggersDeprecationWhenEnabled(): void
    {
        SmartArray::$logDeprecations = true;

        $deprecationTriggered = false;
        set_error_handler(function ($errno, $errstr) use (&$deprecationTriggered) {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationTriggered = true;
            }
            return true;
        });

        try {
            SmartArray::newSS(['test']);

            $this->assertTrue($deprecationTriggered, 'newSS() should trigger deprecation warning');
        } finally {
            restore_error_handler();
        }
    }

    //endregion
    //region Legacy methods still work correctly

    /**
     * @dataProvider legacyMethodsProvider
     */
    public function testLegacyMethodsReturnCorrectValues(string $method, array $args, mixed $expected): void
    {
        SmartArray::$logDeprecations = false;

        $smartArray = new SmartArray(['a' => 1, 'b' => 2, 'c' => 3]);
        $result     = $smartArray->$method(...$args);

        // Normalize result for comparison
        if ($result instanceof SmartArray) {
            $result = $result->toArray();
        }

        $this->assertSame($expected, $result);
    }

    public static function legacyMethodsProvider(): array
    {
        return [
            'exists on non-empty' => ['exists', [], true],
            'getValues'           => ['getValues', [], [1, 2, 3]],
            'item with key'       => ['item', ['a'], 1],
            'raw'                 => ['raw', [], ['a' => 1, 'b' => 2, 'c' => 3]],
        ];
    }

    //endregion

}
