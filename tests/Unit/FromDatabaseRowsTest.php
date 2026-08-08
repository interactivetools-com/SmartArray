<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * fromDatabaseRows(): trusted construction from database-shaped rows (a list of
 * flat arrays with scalar/null values) and the kept-source fast path in toArray().
 *
 * The contract under test: a fromDatabaseRows() collection is observably identical
 * to one built by the constructor from the same rows, except toArray() returns the
 * original rows directly until any write to the collection or one of its rows.
 */
class FromDatabaseRowsTest extends SmartArrayTestCase
{
    //region Fixtures

    /** @return array<int, array<string, string|int|float|null>> */
    private static function newsRows(): array
    {
        return [
            ['id' => 1, 'title' => "Mayor Says 'No'", 'views' => 1200, 'rating' => 4.5, 'notes' => null],
            ['id' => 2, 'title' => 'Steady "Growth"', 'views' => 0, 'rating' => 3.0, 'notes' => 'a&b'],
            ['id' => 3, 'title' => 'Final <Report>', 'views' => 87, 'rating' => 1.5, 'notes' => ''],
        ];
    }

    //endregion
    //region Equivalence with the constructor

    #[DataProvider('modeProvider')]
    public function testMatchesConstructorBuiltCollection(string $class): void
    {
        $rows        = self::newsRows();
        $constructed = new $class($rows);
        $trusted     = $class::fromDatabaseRows($rows);

        $this->assertSame($constructed->toArray(), $trusted->toArray());
        $this->assertSame(3, $trusted->count());
        $this->assertSame([0, 1, 2], $trusted->keys()->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testChildRowsGetClassPositionAndRoot(string $class): void
    {
        $trusted = $class::fromDatabaseRows(self::newsRows());

        $positions = [];
        foreach ($trusted as $row) {
            $this->assertInstanceOf($class, $row);
            $this->assertSame($trusted, $row->root());
            $positions[] = [$row->position(), $row->isFirst(), $row->isLast()];
        }
        $this->assertSame([[1, true, false], [2, false, false], [3, false, true]], $positions);
    }

    #[DataProvider('modeProvider')]
    public function testFieldAccessWrapsPerMode(string $class): void
    {
        $trusted = $class::fromDatabaseRows(self::newsRows());

        $this->assertModeValue("Mayor Says 'No'", $trusted->first()->title, $class);
        $this->assertModeValue(1200, $trusted->first()->views, $class);
        $this->assertModeValue(null, $trusted->first()->notes, $class);
    }

    public function testHtmlModeEncodesOnOutput(): void
    {
        $trusted = SmartArrayHtml::fromDatabaseRows(self::newsRows());

        $this->assertSame("Mayor Says &apos;No&apos;", (string)$trusted->first()->title);
        $this->assertSame('Final &lt;Report&gt;', (string)$trusted->last()->title);
    }

    #[DataProvider('modeProvider')]
    public function testEmptyRows(string $class): void
    {
        $trusted = $class::fromDatabaseRows([]);

        $this->assertSame(0, $trusted->count());
        $this->assertSame([], $trusted->toArray());
        $this->assertSmartNull($trusted->first());
    }

    //endregion
    //region toArray() kept-source fast path

    #[DataProvider('modeProvider')]
    public function testToArrayReturnsOriginalRows(string $class): void
    {
        $rows    = self::newsRows();
        $trusted = $class::fromDatabaseRows($rows);

        $this->assertSame($rows, $trusted->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testWriteToRowRefreshesToArray(string $class): void
    {
        $trusted = $class::fromDatabaseRows(self::newsRows());

        $trusted->first()->title = 'Rewritten';

        $this->assertSame('Rewritten', $trusted->toArray()[0]['title']);
        $this->assertSame('Steady "Growth"', $trusted->toArray()[1]['title']);
    }

    #[DataProvider('modeProvider')]
    public function testWriteToCollectionRefreshesToArray(string $class): void
    {
        $trusted = $class::fromDatabaseRows(self::newsRows());

        $trusted->{3} = ['id' => 4, 'title' => 'Added', 'views' => 1, 'rating' => 0.0, 'notes' => null];

        $this->assertSame(4, $trusted->count());
        $this->assertSame('Added', $trusted->toArray()[3]['title']);
    }

    #[DataProvider('modeProvider')]
    public function testUnsetOnRowRefreshesToArray(string $class): void
    {
        $trusted = $class::fromDatabaseRows(self::newsRows());

        $firstRow = $trusted->first();
        unset($firstRow->notes);

        $this->assertSame(['id', 'title', 'views', 'rating'], array_keys($trusted->toArray()[0]));
    }

    #[DataProvider('modeProvider')]
    public function testUnsetOnCollectionRefreshesToArray(string $class): void
    {
        $trusted = $class::fromDatabaseRows(self::newsRows());

        unset($trusted->{0});

        $this->assertSame([1, 2], array_keys($trusted->toArray()));
    }

    //endregion
    //region Prototype cache

    #[DataProvider('modeProvider')]
    public function testRepeatCallsStartFresh(string $class): void
    {
        // Mutating one collection must never affect the next fromDatabaseRows() call
        $first = $class::fromDatabaseRows(self::newsRows(), ['mysqli' => ['insert_id' => 42]]);
        $first->first()->title = 'Mutated';
        $first->{3}            = ['id' => 4, 'title' => 'Added', 'views' => 1, 'rating' => 0.0, 'notes' => null];

        $second = $class::fromDatabaseRows([['id' => 7, 'title' => 'Fresh', 'views' => 0, 'rating' => 0.0, 'notes' => null]]);

        $this->assertSame(1, $second->count());
        $this->assertSame('Fresh', $second->toArray()[0]['title']);
        $this->assertSame([], $second->mysqli());
        $this->assertSame($second, $second->root());
        $this->assertSame(0, $second->position());
    }

    public function testInterleavedClassesKeepTheirClass(): void
    {
        $raw   = SmartArray::fromDatabaseRows(self::newsRows());
        $html  = SmartArrayHtml::fromDatabaseRows(self::newsRows());
        $raw2  = SmartArray::fromDatabaseRows(self::newsRows());

        $this->assertInstanceOf(SmartArray::class, $raw->first());
        $this->assertInstanceOf(SmartArrayHtml::class, $html->first());
        $this->assertModeValue("Mayor Says 'No'", $raw2->first()->title, SmartArray::class);
        $this->assertModeValue("Mayor Says 'No'", $html->first()->title, SmartArrayHtml::class);
    }

    #[DataProvider('modeProvider')]
    public function testPropertiesLandOnResultSetAndRows(string $class): void
    {
        $handler = fn(SmartArrayBase $row, string $field) => [['related' => $field], ['query' => 'SELECT related']];
        $trusted = $class::fromDatabaseRows(self::newsRows(), [
            'loadHandler' => $handler,
            'mysqli'      => ['query' => 'SELECT * FROM news', 'insert_id' => 5],
        ]);

        $this->assertSame('SELECT * FROM news', $trusted->mysqli('query'));
        $this->assertSame(5, $trusted->mysqli('insert_id'));
        $this->assertSame('SELECT * FROM news', $trusted->first()->mysqli('query'));

        // load() on a row proves loadHandler carried into the children
        $this->assertModeValue('orders', $trusted->first()->load('orders')->related, $class);
    }

    #[DataProvider('modeProvider')]
    public function testExplicitUseSmartStringsKeepsConstructorBehavior(string $class): void
    {
        // An explicit key matching the class default behaves like the constructor
        $matching = $class === SmartArrayHtml::class;
        $forced   = $class::fromDatabaseRows(self::newsRows(), ['useSmartStrings' => $matching]);

        $this->assertSame((new $class(self::newsRows()))->toArray(), $forced->toArray());
        $this->assertModeValue("Mayor Says 'No'", $forced->first()->title, $class);
    }

    #[DataProvider('modeProvider')]
    public function testExplicitUseSmartStringsMismatchThrows(string $class): void
    {
        // A key contradicting the class still gets the constructor's validation
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('useSmartStrings');

        $class::fromDatabaseRows(self::newsRows(), ['useSmartStrings' => $class !== SmartArrayHtml::class]);
    }

    //endregion
    //region fromDatabaseRow() - first row directly

    #[DataProvider('modeProvider')]
    public function testRowMatchesFromDatabaseRowsFirst(string $class): void
    {
        // Same graph as fromDatabaseRows()->first(): the row, its siblings, and root
        $rows = self::newsRows();
        $row  = $class::fromDatabaseRow($rows, ['mysqli' => ['query' => 'SELECT 1']]);

        $this->assertEquals($class::fromDatabaseRows($rows, ['mysqli' => ['query' => 'SELECT 1']])->first(), $row);
        $this->assertInstanceOf($class, $row);
        $this->assertModeValue("Mayor Says 'No'", $row->title, $class);
        $this->assertSame([1, true, false], [$row->position(), $row->isFirst(), $row->isLast()]);
    }

    #[DataProvider('modeProvider')]
    public function testRowRootIsTheFullResultSet(string $class): void
    {
        $row = $class::fromDatabaseRow(self::newsRows(), ['mysqli' => ['query' => 'SELECT 1']]);

        $this->assertSame(3, $row->root()->count());
        $this->assertSame(self::newsRows(), $row->root()->toArray());
        $this->assertSame('SELECT 1', $row->root()->mysqli('query'));
        $this->assertSame($row, $row->root()->first());
    }

    #[DataProvider('modeProvider')]
    public function testSingleRowIsFirstAndLast(string $class): void
    {
        $row = $class::fromDatabaseRow([self::newsRows()[0]]);

        $this->assertSame([1, true, true], [$row->position(), $row->isFirst(), $row->isLast()]);
        $this->assertSame(1, $row->root()->count());
    }

    #[DataProvider('modeProvider')]
    public function testNoRowsReturnsEmptyCollection(string $class): void
    {
        $row = $class::fromDatabaseRow([], ['mysqli' => ['query' => 'SELECT 1']]);

        $this->assertInstanceOf($class, $row);
        $this->assertSame(0, $row->count());
        $this->assertSmartNull($row->missing_field);
        $this->assertSame('SELECT 1', $row->root()->mysqli('query'));
    }

    //endregion
}
