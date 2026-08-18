<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;

/**
 * The plain-array snapshot ($sourceRows) transforms carry forward so chained
 * toArray() calls skip the rebuild.
 *
 * The contract under test: a snapshot is only served while the root's snapshot
 * is still set, every write path clears the root's, and snapshots never come
 * back after a write. Correctness must never depend on the snapshot - each test
 * asserts what toArray() returns, not how fast it got it.
 */
class SourceRowsTest extends SmartArrayTestCase
{
    private static function rows(): array
    {
        return [
            ['id' => 1, 'name' => 'Amy', 'status' => 'active'],
            ['id' => 2, 'name' => 'Sam', 'status' => 'active'],
            ['id' => 3, 'name' => 'Kim', 'status' => 'draft'],
        ];
    }

    private static function snapshotOf(SmartArrayBase $set): ?array
    {
        return (new ReflectionProperty(SmartArrayBase::class, 'sourceRows'))->getValue($set);
    }

    #[DataProvider('modeProvider')]
    public function testTransformResultCarriesASnapshotMatchingToArray(string $class): void
    {
        $active = $class::fromDatabaseRows(self::rows())->where('status', 'active');

        $this->assertNotNull(self::snapshotOf($active), 'transforms keep the plain array they built from');
        $this->assertSame(self::snapshotOf($active), $active->toArray(), 'the snapshot IS the toArray() result');
    }

    #[DataProvider('modeProvider')]
    public function testWriteToADerivedRowShowsInDerivedToArray(string $class): void
    {
        $active = $class::fromDatabaseRows(self::rows())->where('status', 'active');

        $active->first()->name = 'Bob';

        $this->assertSame(['Bob', 'Sam'], array_column($active->toArray(), 'name'), 'the write, not the snapshot');
    }

    #[DataProvider('modeProvider')]
    public function testWriteToTheOriginalLeavesDerivedSnapshotSemantics(string $class): void
    {
        // derived sets copy row data at transform time; later writes to the
        // original never reach them - with or without the snapshot
        $source = $class::fromDatabaseRows(self::rows());
        $copy   = $source->values();

        $source->first()->name = 'Eve';

        $this->assertSame(['Amy', 'Sam', 'Kim'], array_column($copy->toArray(), 'name'), 'derived rows are copies');
        $this->assertSame('Eve', $source->toArray()[0]['name']);
    }

    #[DataProvider('modeProvider')]
    public function testWriteToOneSiblingLeavesTheOtherCorrect(string $class): void
    {
        $source = $class::fromDatabaseRows(self::rows());
        $active = $source->where('status', 'active');
        $draft  = $source->where('status', 'draft');

        $active->first()->name = 'Bob';

        $this->assertSame(['Kim'], array_column($draft->toArray(), 'name'), 'sibling rebuilds, same values');
    }

    #[DataProvider('modeProvider')]
    public function testUnsetOnADerivedSetShowsInToArray(string $class): void
    {
        $copy = $class::fromDatabaseRows(self::rows())->values();

        unset($copy->{0});

        $this->assertSame(['Sam', 'Kim'], array_column($copy->toArray(), 'name'));
    }

    #[DataProvider('modeProvider')]
    public function testSnapshotsNeverComeBackAfterAWrite(string $class): void
    {
        $source = $class::fromDatabaseRows(self::rows());
        $active = $source->where('status', 'active');

        $active->first()->name = 'Bob';

        $this->assertNull(self::snapshotOf($source), 'any write clears the root snapshot for good');
        $this->assertSame(['Bob', 'Sam'], array_column($active->toArray(), 'name'), 'first read after the write');
        $this->assertSame(['Bob', 'Sam'], array_column($active->toArray(), 'name'), 'and every read after that');
    }

    public function testPlainConstructedTreesStayCorrectWithoutSnapshots(): void
    {
        // new SmartArray() roots never get a snapshot, so derived sets can't serve
        // one either (the root check fails) - everything falls back to the rebuild
        $set    = SmartArray::new(self::rows());
        $active = $set->where('status', 'active');

        $this->assertSame(['Amy', 'Sam'], array_column($active->toArray(), 'name'));
    }
}
