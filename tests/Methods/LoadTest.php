<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Error;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartNull;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use InvalidArgumentException;
use Itools\SmartArray\CallerException;

/**
 * Tests for SmartArray::load().
 *
 * load($field) lazily loads related data using a handler function,
 * passed in as the 'loadHandler' constructor property (how ZenDB wires it).
 */
class LoadTest extends SmartArrayTestCase
{

    //region loadHandler constructor property

    public function testLoadHandlerSetViaConstructorProperty(): void
    {
        $handler = function ($smartArray, $field) {
            return [['child' => 'data'], []];
        };

        $smartArray = new SmartArray(['id' => 1, 'name' => 'Test'], ['loadHandler' => $handler]);

        // Verify handler is set by calling load
        $result = $smartArray->load('related');

        $this->assertInstanceOf(SmartArray::class, $result);
        $this->assertSame(['child' => 'data'], $result->toArray());
    }

    //endregion
    //region load() - Success cases

    public function testLoadReturnsSmartArrayWithHandlerResult(): void
    {
        $handler = function ($row, $field) {
            $this->assertInstanceOf(SmartArray::class, $row);
            $this->assertSame('products', $field);
            return [
                ['product1', 'product2', 'product3'],
                ['query' => 'SELECT * FROM products']
            ];
        };

        $smartArray = new SmartArray(['id' => 1, 'name' => 'Test'], ['loadHandler' => $handler]);

        $result = $smartArray->load('products');

        $this->assertInstanceOf(SmartArray::class, $result);
        $this->assertSame(['product1', 'product2', 'product3'], $result->toArray());
    }

    public function testLoadPassesRowDataToHandler(): void
    {
        $receivedRow = null;
        $handler     = function ($row, $field) use (&$receivedRow) {
            $receivedRow = $row->toArray();
            return [[], []];
        };

        $smartArray = new SmartArray(['user_id' => 42, 'name' => 'John'], ['loadHandler' => $handler]);

        $smartArray->load('orders');

        $this->assertSame(['user_id' => 42, 'name' => 'John'], $receivedRow);
    }

    public function testLoadPassesFieldNameToHandler(): void
    {
        $receivedField = null;
        $handler       = function ($row, $field) use (&$receivedField) {
            $receivedField = $field;
            return [[], []];
        };

        $smartArray = new SmartArray(['id' => 1], ['loadHandler' => $handler]);

        $smartArray->load('invoices');

        $this->assertSame('invoices', $receivedField);
    }

    public function testLoadPreservesLoadHandlerInResult(): void
    {
        $handlerCalled = 0;

        $handler = function ($row, $field) use (&$handlerCalled) {
            $handlerCalled++;
            return [['nested' => 'data'], []];
        };

        $smartArray = new SmartArray(['id' => 1], ['loadHandler' => $handler]);

        // First load
        $result = $smartArray->load('level1');
        $this->assertSame(1, $handlerCalled);

        // Result should also have the handler, so it can load nested data
        $nestedResult = $result->load('level2');
        $this->assertSame(2, $handlerCalled);
    }

    public function testLoadStoresMysqliMetadata(): void
    {
        $handler = function ($row, $field) {
            return [
                ['data' => 'value'],
                ['query' => 'SELECT * FROM related', 'affected_rows' => 1]
            ];
        };

        $smartArray = new SmartArray(['id' => 1], ['loadHandler' => $handler]);

        $result = $smartArray->load('related');

        $this->assertSame('SELECT * FROM related', $result->mysqli('query'));
        $this->assertSame(1, $result->mysqli('affected_rows'));
    }

    //endregion
    //region load() - Empty array handling

    public function testLoadReturnsSmartNullWhenArrayIsEmpty(): void
    {
        $handler = function ($row, $field) {
            $this->fail('Handler should not be called for empty array');
            return [[], []];
        };

        $smartArray = new SmartArray([], ['loadHandler' => $handler]);

        $result = $smartArray->load('anything');

        $this->assertInstanceOf(SmartNull::class, $result);
    }

    //endregion
    //region load() - Error cases

    public function testLoadThrowsWithoutHandler(): void
    {
        $smartArray = new SmartArray(['id' => 1]);

        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('load(): no load handler is set');

        $smartArray->load('products');
    }

    public function testLoadThrowsWithEmptyFieldName(): void
    {
        $smartArray = new SmartArray(['id' => 1], ['loadHandler' => fn($row, $col) => [[], []]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name is required');

        $smartArray->load('');
    }

    public function testLoadThrowsOnNestedArray(): void
    {
        $smartArray = new SmartArray([
            ['id' => 1, 'name' => 'Row 1'],
            ['id' => 2, 'name' => 'Row 2'],
        ], ['loadHandler' => fn($row, $col) => [[], []]]);

        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('Cannot call load() on record set');

        $smartArray->load('products');
    }

    public function testLoadThrowsWhenHandlerReturnsFalse(): void
    {
        $smartArray = new SmartArray(['id' => 1], ['loadHandler' => fn($row, $col) => false]);

        $this->expectException(Error::class);
        $this->expectExceptionMessage("Load handler doesn't support field 'products'");

        $smartArray->load('products');
    }

    public function testLoadThrowsWhenHandlerReturnsNonArray(): void
    {
        $smartArray = new SmartArray(['id' => 1], ['loadHandler' => fn($row, $col) => ['invalid', 'not an array with two arrays']]);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Load handler must return an array');

        $smartArray->load('products');
    }

    //endregion
    //region load() - Valid field names

    /**
     * @dataProvider validFieldNamesProvider
     */
    public function testLoadAcceptsValidFieldNames(string $fieldName): void
    {
        $smartArray = new SmartArray(['id' => 1], ['loadHandler' => fn($row, $col) => [[], []]]);

        $result = $smartArray->load($fieldName);

        $this->assertInstanceOf(SmartArray::class, $result);
    }

    public static function validFieldNamesProvider(): array
    {
        return [
            'simple name'      => ['products'],
            'with underscore'  => ['user_products'],
            'with numbers'     => ['items2'],
            'with hyphen'      => ['related-items'],
            'uppercase'        => ['PRODUCTS'],
            'mixed case'       => ['RelatedProducts'],
        ];
    }

    /**
     * @dataProvider invalidFieldNamesProvider
     */
    public function testLoadRejectsInvalidFieldNames(string $fieldName): void
    {
        $smartArray = new SmartArray(['id' => 1], ['loadHandler' => fn($row, $col) => [[], []]]);

        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('Field name contains invalid characters');

        $smartArray->load($fieldName);
    }

    public static function invalidFieldNamesProvider(): array
    {
        return [
            'with dot'       => ['table.column'],
            'with space'     => ['user products'],
            'with special'   => ['products!'],
            'with semicolon' => ['products;drop'],
            'with quotes'    => ["products'"],
        ];
    }

    //endregion

}
