<?php
/** @noinspection PhpUndefinedMethodInspection */
declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;

/**
 * Tests for deprecated/legacy method aliases in SmartArray.
 *
 * These methods are maintained for backwards compatibility but
 * will trigger deprecation warnings via trigger_error().
 *
 * Purpose: Ensure legacy code continues to work during migration.
 */
class LegacyMethodsTest extends SmartArrayTestCase
{

    /**
     * Suppress E_USER_DEPRECATED notices during legacy method tests.
     * These methods intentionally trigger deprecation warnings, but we're testing functionality, not warnings.
     */
    protected function setUp(): void
    {
        parent::setUp();
        set_error_handler(fn($errno) => $errno === E_USER_DEPRECATED, E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    //region SmartStrings toggle aliases

    public function testEnableSmartStringsReturnsSmartArrayHtml(): void
    {
        $smartArray = new SmartArray(['name' => 'John']);
        $result     = $smartArray->enableSmartStrings();

        $this->assertInstanceOf(SmartArrayHtml::class, $result);
        $this->assertTrue($result->usingSmartStrings());
    }

    public function testWithSmartStringsReturnsSmartArrayHtml(): void
    {
        $smartArray = new SmartArray(['name' => 'John']);
        $result     = $smartArray->withSmartStrings();

        $this->assertInstanceOf(SmartArrayHtml::class, $result);
        $this->assertTrue($result->usingSmartStrings());
    }

    public function testDisableSmartStringsReturnsSmartArray(): void
    {
        $smartArray = SmartArrayHtml::new(['name' => 'John']);
        $result     = $smartArray->disableSmartStrings();

        $this->assertInstanceOf(SmartArray::class, $result);
        $this->assertFalse($result->usingSmartStrings());
    }

    public function testNoSmartStringsReturnsSmartArray(): void
    {
        $smartArray = SmartArrayHtml::new(['name' => 'John']);
        $result     = $smartArray->noSmartStrings();

        $this->assertInstanceOf(SmartArray::class, $result);
        $this->assertFalse($result->usingSmartStrings());
    }

    //endregion
    //region Deprecation warnings

    public function testDeprecationWarningTriggered(): void
    {
        // Temporarily restore default handler so we can capture the deprecation
        restore_error_handler();

        $deprecationTriggered = false;
        set_error_handler(function ($errno) use (&$deprecationTriggered) {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationTriggered = true;
            }
            return true;
        });

        try {
            $smartArray = new SmartArray(['a', 'b', 'c']);
            $smartArray->enableSmartStrings();

            $this->assertTrue($deprecationTriggered, 'Deprecation warning should be triggered');
        } finally {
            restore_error_handler();
            // Re-install the suppressor for tearDown consistency
            set_error_handler(fn($errno) => $errno === E_USER_DEPRECATED, E_USER_DEPRECATED);
        }
    }

    //endregion
    //region Legacy methods still work correctly

    /**
     * @dataProvider legacyMethodsProvider
     */
    public function testLegacyMethodsReturnCorrectValues(string $method, array $args, string $expectedClass): void
    {
        $smartArray = new SmartArray(['a' => 1, 'b' => 2, 'c' => 3]);
        $result     = $smartArray->$method(...$args);

        $this->assertInstanceOf($expectedClass, $result);
    }

    public static function legacyMethodsProvider(): array
    {
        return [
            'toRaw'              => ['toRaw', [], SmartArray::class],
            'toHtml'             => ['toHtml', [], SmartArrayHtml::class],
            'enableSmartStrings' => ['enableSmartStrings', [], SmartArrayHtml::class],
            'withSmartStrings'   => ['withSmartStrings', [], SmartArrayHtml::class],
        ];
    }

    //endregion
    //region Legacy constructor syntax

    public function testSmartArrayWithBooleanTrueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create SmartArray with useSmartStrings=true');

        new SmartArray(['name' => 'John'], true);
    }

    public function testSmartArrayWithUseSmartStringsTrueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create SmartArray with useSmartStrings=true');

        new SmartArray(['name' => 'John'], ['useSmartStrings' => true]);
    }

    public function testSmartArrayHtmlWithBooleanFalseThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create SmartArrayHtml with useSmartStrings=false');

        new SmartArrayHtml(['name' => 'John'], false);
    }

    public function testSmartArrayHtmlWithUseSmartStringsFalseThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create SmartArrayHtml with useSmartStrings=false');

        new SmartArrayHtml(['name' => 'John'], ['useSmartStrings' => false]);
    }

    public function testSmartArrayWithBooleanFalseWorks(): void
    {
        // Passing false to SmartArray is valid (matches the class's purpose)
        $result = new SmartArray(['name' => 'John'], false);

        $this->assertInstanceOf(SmartArray::class, $result);
        $this->assertFalse($result->usingSmartStrings());
    }

    public function testSmartArrayHtmlWithBooleanTrueWorks(): void
    {
        // Passing true to SmartArrayHtml is valid (matches the class's purpose)
        $result = new SmartArrayHtml(['name' => 'John'], true);

        $this->assertInstanceOf(SmartArrayHtml::class, $result);
        $this->assertTrue($result->usingSmartStrings());
    }

    public function testLegacyConstructorTriggersDeprecationBeforeException(): void
    {
        // Temporarily restore default handler so we can capture the deprecation
        restore_error_handler();

        $deprecationTriggered = false;
        set_error_handler(function ($errno, $errstr) use (&$deprecationTriggered) {
            if ($errno === E_USER_DEPRECATED && str_contains($errstr, 'deprecated')) {
                $deprecationTriggered = true;
            }
            return true;
        });

        try {
            new SmartArray(['test'], true);
        }
        catch (InvalidArgumentException) {
            // Expected
        }
        finally {
            restore_error_handler();
            set_error_handler(fn($errno) => $errno === E_USER_DEPRECATED, E_USER_DEPRECATED);
        }

        $this->assertTrue($deprecationTriggered, 'Deprecation warning should be triggered before exception');
    }

    //endregion

}
