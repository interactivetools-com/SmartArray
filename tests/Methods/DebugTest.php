<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\SmartArrayTestCase;

/**
 * Tests for SmartArray::debug() method.
 *
 * debug($debugLevel) outputs formatted debug information about the array.
 * Level 0: Basic data output
 * Level 1+: Includes object properties
 */
class DebugTest extends SmartArrayTestCase
{

    //region Basic output

    public function testDebugOutputsArrayData(): void
    {
        $smartArray = new SmartArray(['name' => 'John', 'age' => 30]);

        ob_start();
        $smartArray->debug();
        $output = ob_get_clean();

        $this->assertStringContainsString('name', $output);
        $this->assertStringContainsString('John', $output);
        $this->assertStringContainsString('age', $output);
        $this->assertStringContainsString('30', $output);
    }

    public function testDebugShowsSmartStringsDisabledMessage(): void
    {
        $smartArray = new SmartArray(['test' => 'data']);

        ob_start();
        $smartArray->debug();
        $output = ob_get_clean();

        $this->assertStringContainsString('Values are returned **as-is** on access', $output);
    }

    public function testDebugShowsSmartStringsEnabledMessage(): void
    {
        $smartArray = new SmartArrayHtml(['test' => 'data']);

        ob_start();
        $smartArray->debug();
        $output = ob_get_clean();

        $this->assertStringContainsString('Values are returned as **SmartStrings** on access', $output);
    }

    public function testDebugReturnsVoid(): void
    {
        $smartArray = new SmartArray(['a', 'b', 'c']);

        ob_start();
        $result = $smartArray->debug();
        ob_get_clean();

        $this->assertNull($result);
    }

    //endregion
    //region Debug levels

    public function testDebugLevel0DoesNotShowProperties(): void
    {
        $smartArray = new SmartArray(['test' => 'data']);

        ob_start();
        $smartArray->debug(0);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('Object Properties', $output);
    }

    public function testDebugLevel1ShowsProperties(): void
    {
        $smartArray = new SmartArray(['test' => 'data']);

        ob_start();
        $smartArray->debug(1);
        $output = ob_get_clean();

        $this->assertStringContainsString('Object Properties', $output);
    }

    public function testDebugLevel1ShowsRootProperty(): void
    {
        $smartArray = new SmartArray(['test' => 'data']);

        ob_start();
        $smartArray->debug(1);
        $output = ob_get_clean();

        $this->assertStringContainsString('root', $output);
        $this->assertStringContainsString('SmartArray', $output);
    }

    //endregion
    //region MySQL metadata

    public function testDebugShowsMysqliQueryWhenAvailable(): void
    {
        $smartArray = new SmartArray(['id' => 1], [
            'mysqli' => ['query' => 'SELECT * FROM users WHERE id = 1']
        ]);

        ob_start();
        $smartArray->debug();
        $output = ob_get_clean();

        $this->assertStringContainsString('MySQL Query', $output);
        $this->assertStringContainsString('SELECT * FROM users', $output);
    }

    public function testDebugShowsMysqliMetadataWhenAvailable(): void
    {
        $smartArray = new SmartArray(['id' => 1], [
            'mysqli' => [
                'query'         => 'SELECT * FROM users',
                'affected_rows' => 1,
                'num_rows'      => 1,
            ]
        ]);

        ob_start();
        $smartArray->debug();
        $output = ob_get_clean();

        $this->assertStringContainsString('MySQLi Metadata', $output);
        $this->assertStringContainsString('affected_rows', $output);
        $this->assertStringContainsString('num_rows', $output);
    }

    public function testDebugDoesNotShowMysqliWhenNotSet(): void
    {
        $smartArray = new SmartArray(['test' => 'data']);

        ob_start();
        $smartArray->debug();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('MySQL Query', $output);
        $this->assertStringNotContainsString('MySQLi Metadata', $output);
    }

    //endregion
    //region Nested arrays

    public function testDebugShowsNestedArrays(): void
    {
        $smartArray = new SmartArray([
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ]);

        ob_start();
        $smartArray->debug();
        $output = ob_get_clean();

        $this->assertStringContainsString('John', $output);
        $this->assertStringContainsString('Jane', $output);
    }

    //endregion
    //region Empty array

    public function testDebugHandlesEmptyArray(): void
    {
        $smartArray = new SmartArray([]);

        ob_start();
        $smartArray->debug();
        $output = ob_get_clean();

        // Should output something even for empty array
        $this->assertNotEmpty($output);
        $this->assertStringContainsString('SmartArray', $output);
    }

    //endregion
    //region Output wrapping

    public function testDebugOutputIsWrappedInXmpTags(): void
    {
        $smartArray = new SmartArray(['test' => 'data']);

        ob_start();
        $smartArray->debug();
        $output = ob_get_clean();

        $this->assertStringContainsString('<xmp>', $output);
        $this->assertStringContainsString('</xmp>', $output);
    }

    //endregion

}
