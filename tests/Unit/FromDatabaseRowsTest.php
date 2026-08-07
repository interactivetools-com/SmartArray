<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

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
}
