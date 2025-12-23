<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Error;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartNull;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use InvalidArgumentException;
use RuntimeException;

/**
 * Tests for SmartArray::load() and SmartArray::setLoadHandler() methods.
 *
 * load($column) lazily loads related data using a handler function.
 * setLoadHandler($handler) sets the handler for lazy loading.
 */
class LoadTest extends SmartArrayTestCase
{

    //region setLoadHandler()

    public function testSetLoadHandlerSetsCallable(): void
    {
        $smartArray = new SmartArray(['id' => 1, 'name' => 'Test']);

        $handler = function ($smartArray, $column) {
            return [['child' => 'data'], []];
        };

        $smartArray->setLoadHandler($handler);

        // Verify handler is set by calling load
        $result = $smartArray->load('related');

        $this->assertInstanceOf(SmartArray::class, $result);
        $this->assertSame(['child' => 'data'], $result->toArray());
    }

    //endregion
    //region load() - Success cases

    public function testLoadReturnsSmartArrayWithHandlerResult(): void
    {
        $smartArray = new SmartArray(['id' => 1, 'name' => 'Test']);

        $smartArray->setLoadHandler(function ($row, $column) {
            $this->assertInstanceOf(SmartArray::class, $row);
            $this->assertSame('products', $column);
            return [
                ['product1', 'product2', 'product3'],
                ['query' => 'SELECT * FROM products']
            ];
        });

        $result = $smartArray->load('products');

        $this->assertInstanceOf(SmartArray::class, $result);
        $this->assertSame(['product1', 'product2', 'product3'], $result->toArray());
    }

    public function testLoadPassesRowDataToHandler(): void
    {
        $smartArray = new SmartArray(['user_id' => 42, 'name' => 'John']);
        $receivedRow = null;

        $smartArray->setLoadHandler(function ($row, $column) use (&$receivedRow) {
            $receivedRow = $row->toArray();
            return [[], []];
        });

        $smartArray->load('orders');

        $this->assertSame(['user_id' => 42, 'name' => 'John'], $receivedRow);
    }

    public function testLoadPassesColumnNameToHandler(): void
    {
        $smartArray = new SmartArray(['id' => 1]);
        $receivedColumn = null;

        $smartArray->setLoadHandler(function ($row, $column) use (&$receivedColumn) {
            $receivedColumn = $column;
            return [[], []];
        });

        $smartArray->load('invoices');

        $this->assertSame('invoices', $receivedColumn);
    }

    public function testLoadPreservesLoadHandlerInResult(): void
    {
        $handlerCalled = 0;

        $smartArray = new SmartArray(['id' => 1]);
        $smartArray->setLoadHandler(function ($row, $column) use (&$handlerCalled) {
            $handlerCalled++;
            return [['nested' => 'data'], []];
        });

        // First load
        $result = $smartArray->load('level1');
        $this->assertSame(1, $handlerCalled);

        // Result should also have the handler, so it can load nested data
        $nestedResult = $result->load('level2');
        $this->assertSame(2, $handlerCalled);
    }

    public function testLoadStoresMysqliMetadata(): void
    {
        $smartArray = new SmartArray(['id' => 1]);
        $smartArray->setLoadHandler(function ($row, $column) {
            return [
                ['data' => 'value'],
                ['query' => 'SELECT * FROM related', 'affected_rows' => 1]
            ];
        });

        $result = $smartArray->load('related');

        $this->assertSame('SELECT * FROM related', $result->mysqli('query'));
        $this->assertSame(1, $result->mysqli('affected_rows'));
    }

    //endregion
    //region load() - Empty array handling

    public function testLoadReturnsSmartNullWhenArrayIsEmpty(): void
    {
        $smartArray = new SmartArray([]);

        $smartArray->setLoadHandler(function ($row, $column) {
            $this->fail('Handler should not be called for empty array');
            return [[], []];
        });

        $result = $smartArray->load('anything');

        $this->assertInstanceOf(SmartNull::class, $result);
    }

    //endregion
    //region load() - Error cases

    public function testLoadThrowsWithoutHandler(): void
    {
        $smartArray = new SmartArray(['id' => 1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No loadHandler property is defined');

        $smartArray->load('products');
    }

    public function testLoadThrowsWithEmptyColumnName(): void
    {
        $smartArray = new SmartArray(['id' => 1]);
        $smartArray->setLoadHandler(fn($row, $col) => [[], []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column name is required');

        $smartArray->load('');
    }

    public function testLoadThrowsOnNestedArray(): void
    {
        $smartArray = new SmartArray([
            ['id' => 1, 'name' => 'Row 1'],
            ['id' => 2, 'name' => 'Row 2'],
        ]);
        $smartArray->setLoadHandler(fn($row, $col) => [[], []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot call load() on record set');

        $smartArray->load('products');
    }

    public function testLoadThrowsWhenHandlerReturnsFalse(): void
    {
        $smartArray = new SmartArray(['id' => 1]);
        $smartArray->setLoadHandler(fn($row, $col) => false);

        $this->expectException(Error::class);
        $this->expectExceptionMessage("Load handler not available for 'products'");

        $smartArray->load('products');
    }

    public function testLoadThrowsWhenHandlerReturnsNonArray(): void
    {
        $smartArray = new SmartArray(['id' => 1]);
        $smartArray->setLoadHandler(fn($row, $col) => ['invalid', 'not an array with two arrays']);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Load handler must return an array');

        $smartArray->load('products');
    }

    //endregion
    //region load() - Valid column names

    /**
     * @dataProvider validColumnNamesProvider
     */
    public function testLoadAcceptsValidColumnNames(string $columnName): void
    {
        $smartArray = new SmartArray(['id' => 1]);
        $smartArray->setLoadHandler(fn($row, $col) => [[], []]);

        $result = $smartArray->load($columnName);

        $this->assertInstanceOf(SmartArray::class, $result);
    }

    public static function validColumnNamesProvider(): array
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
     * @dataProvider invalidColumnNamesProvider
     */
    public function testLoadRejectsInvalidColumnNames(string $columnName): void
    {
        $smartArray = new SmartArray(['id' => 1]);
        $smartArray->setLoadHandler(fn($row, $col) => [[], []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column name contains invalid characters');

        $smartArray->load($columnName);
    }

    public static function invalidColumnNamesProvider(): array
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
