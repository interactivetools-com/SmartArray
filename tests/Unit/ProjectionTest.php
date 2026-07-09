<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use InvalidArgumentException;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Column and key projections: pluck(), pluckNth(), column(), indexBy(),
 * groupBy(), keys(), values().
 *
 * Pins the null/missing-field rules decided in the review: pluck silently
 * skips rows missing the column (warning only when the first row is missing
 * it), indexBy and groupBy put null/missing under '', no rows dropped by
 * groupBy, duplicates last-wins for indexBy and accumulate for groupBy.
 */
class ProjectionTest extends SmartArrayTestCase
{
    //region pluck()

    #[DataProvider('modeProvider')]
    public function testPluckExtractsColumnReindexed(string $class): void
    {
        $sa = $class::new([
            5 => ['id' => 1, 'name' => 'Amy'],
            9 => ['id' => 2, 'name' => 'Bob'],
        ]);

        $result = $sa->pluck('name');

        $this->assertInstanceOf($class, $result);
        $this->assertSame(['Amy', 'Bob'], $result->toArray(), 'pluck reindexes from 0');
        $this->assertMetadataPreserved($sa, $result);
    }

    #[DataProvider('modeProvider')]
    public function testPluckTwoArgBuildsKeyedMap(string $class): void
    {
        $sa = $class::new([
            ['id' => 10, 'name' => 'Amy'],
            ['id' => 20, 'name' => 'Bob'],
            ['id' => 10, 'name' => 'Amy2'],  // duplicate key: last wins
        ]);

        $this->assertSame([10 => 'Amy2', 20 => 'Bob'], $sa->pluck('name', 'id')->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testPluckSkipsRowsMissingColumnSilently(string $class): void
    {
        // First row has the column, so no typo warning fires (Q3); rows without
        // the column are skipped (array_column semantics), result reindexes
        $sa = $class::new([['n' => 'a'], ['id' => 2], ['n' => 'c']]);

        [$result, $output] = $this->captureOutput(fn() => $sa->pluck('n'));

        $this->assertSame(['a', 'c'], $result->toArray());
        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testPluckMissingColumnInFirstRowWarns(string $class): void
    {
        $sa = $class::new([['id' => 1], ['id' => 2]]);

        [$result, $output] = $this->captureOutput(fn() => $sa->pluck('name'));

        $this->assertSame([], $result->toArray());
        $this->assertStringContainsString("pluck(): 'name' doesn't exist", $output);
    }

    #[DataProvider('modeProvider')]
    public function testPluckOnFlatThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pluck(): Expected a nested array, but got a flat array');

        $class::new(['a', 'b'])->pluck('name');
    }

    //endregion
    //region pluckNth()

    #[DataProvider('modeProvider')]
    public function testPluckNthExtractsByPosition(string $class): void
    {
        // The SHOW TABLES case: column names unpredictable, position known
        $sa = $class::new([
            ['Tables_in_db (cms_%)' => 'cms_accounts'],
            ['Tables_in_db (cms_%)' => 'cms_pages'],
        ]);

        $this->assertSame(['cms_accounts', 'cms_pages'], $sa->pluckNth(0)->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testPluckNthNegativeAndRaggedRows(string $class): void
    {
        $sa = $class::new([
            ['a', 'b', 'c'],
            ['d', 'e'],       // no position 2; skipped for nth(2) and nth(-3)
        ]);

        $this->assertSame(['c'], $sa->pluckNth(2)->toArray());
        $this->assertSame(['c', 'e'], $sa->pluckNth(-1)->toArray(), 'negative counts from each row\'s own end');
        $this->assertSame(['a'], $sa->pluckNth(-3)->toArray());
        $this->assertSame([], $sa->pluckNth(5)->toArray(), 'out of bounds for every row: empty');
    }

    #[DataProvider('modeProvider')]
    public function testPluckNthOnFlatThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pluckNth(): Expected a nested array, but got a flat array');

        $class::new(['a', 'b'])->pluckNth(0);
    }

    //endregion
    //region column()

    #[DataProvider('modeProvider')]
    public function testColumnDispatchesToPluckAndIndexBy(string $class): void
    {
        $rows = [['id' => 1, 'name' => 'Amy'], ['id' => 2, 'name' => 'Bob']];
        $sa   = $class::new($rows);

        $this->assertSame($sa->pluck('name')->toArray(), $sa->column('name')->toArray());
        $this->assertSame($sa->pluck('name', 'id')->toArray(), $sa->column('name', 'id')->toArray());
        $this->assertSame($sa->indexBy('id')->toArray(), $sa->column(null, 'id')->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testColumnWithBothArgumentsNullThrows(string $class): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('column() unexpected arguments');

        $class::new([['id' => 1]])->column(null, null);
    }

    //endregion
    //region indexBy()

    #[DataProvider('modeProvider')]
    public function testIndexByKeysRowsByFieldValue(string $class): void
    {
        $sa = $class::new([
            ['code' => 'AMY', 'city' => 'Boston'],
            ['code' => 'BOB', 'city' => 'Vancouver'],
        ]);

        $result = $sa->indexBy('code');

        $this->assertSame([
            'AMY' => ['code' => 'AMY', 'city' => 'Boston'],
            'BOB' => ['code' => 'BOB', 'city' => 'Vancouver'],
        ], $result->toArray());
        $this->assertValidStructure($result);
        $this->assertMetadataPreserved($sa, $result);
    }

    #[DataProvider('modeProvider')]
    public function testIndexByDuplicatesLastWins(string $class): void
    {
        $sa = $class::new([
            ['city' => 'NYC', 'name' => 'Amy'],
            ['city' => 'NYC', 'name' => 'Bob'],
        ]);

        $this->assertSame(['NYC' => ['city' => 'NYC', 'name' => 'Bob']], $sa->indexBy('city')->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testIndexByNullAndMissingValuesIndexUnderEmptyString(string $class): void
    {
        // Q4: one rule for both absence forms - '' key, last wins. Previously a
        // missing field produced a leftover numeric key that looked like real data.
        $sa = $class::new([
            ['id' => 1, 'n' => 'a'],
            ['id' => null, 'n' => 'b'],
            ['n' => 'c'],                 // no id at all
        ]);

        [$result, $output] = $this->captureOutput(fn() => $sa->indexBy('id'));

        $this->assertSame([
            1  => ['id' => 1, 'n' => 'a'],
            '' => ['n' => 'c'],           // last '' row wins over the null one
        ], $result->toArray());
        $this->assertSame('', $output, 'null values are legitimate data, not a typo: no warning');
    }

    #[DataProvider('modeProvider')]
    public function testIndexByOnFlatThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('indexBy(): Expected a nested array, but got a flat array');

        $class::new(['a', 'b'])->indexBy('id');
    }

    //endregion
    //region groupBy()

    #[DataProvider('modeProvider')]
    public function testGroupByCollectsRowsInEncounterOrder(string $class): void
    {
        $sa = $class::new([
            ['city' => 'NYC', 'name' => 'Amy'],
            ['city' => 'Vancouver', 'name' => 'Bob'],
            ['city' => 'NYC', 'name' => 'Cid'],
        ]);

        $result = $sa->groupBy('city');

        $this->assertSame([
            'NYC' => [
                ['city' => 'NYC', 'name' => 'Amy'],
                ['city' => 'NYC', 'name' => 'Cid'],
            ],
            'Vancouver' => [
                ['city' => 'Vancouver', 'name' => 'Bob'],
            ],
        ], $result->toArray(), 'groups appear in first-seen order; rows keep encounter order');
        $this->assertInstanceOf($class, $result->get('NYC'), 'each group is a same-mode SmartArray');
        $this->assertValidStructure($result);
    }

    #[DataProvider('modeProvider')]
    public function testGroupByNullAndMissingValuesGroupUnderEmptyString(string $class): void
    {
        // Q4: like SQL GROUP BY keeps a NULL group - no rows are dropped
        $sa = $class::new([
            ['g' => 'a', 'v' => 1],
            ['g' => null, 'v' => 2],
            ['v' => 3],
        ]);

        [$result, $output] = $this->captureOutput(fn() => $sa->groupBy('g'));

        $this->assertSame([
            'a' => [['g' => 'a', 'v' => 1]],
            ''  => [['g' => null, 'v' => 2], ['v' => 3]],
        ], $result->toArray());
        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testGroupByIntegerValuesKeepIntegerKeys(string $class): void
    {
        $sa = $class::new([['year' => 2024, 'n' => 'a'], ['year' => 2025, 'n' => 'b']]);

        $this->assertSame([2024, 2025], $sa->groupBy('year')->keys()->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testGroupByOnFlatThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('groupBy(): Expected a nested array, but got a flat array');

        $class::new(['a', 'b'])->groupBy('g');
    }

    //endregion
    //region keys() / values()

    #[DataProvider('modeProvider')]
    public function testKeysReturnsKeyListInSameMode(string $class): void
    {
        $sa = $class::new(['a' => 1, 7 => 2, 'c' => 3]);

        $result = $sa->keys();

        $this->assertInstanceOf($class, $result);
        $this->assertSame(['a', 7, 'c'], $result->toArray(), 'key order and types preserved');

        // In HTML mode, keys read back as SmartStrings like any other value
        $first = $result->first();
        $this->assertModeValue('a', $first, $class);
    }

    #[DataProvider('modeProvider')]
    public function testValuesReindexesFromZero(string $class): void
    {
        $sa = $class::new([5 => 'a', 'k' => 'b', 9 => ['nested' => 1]]);

        $result = $sa->values();

        $this->assertSame([0 => 'a', 1 => 'b', 2 => ['nested' => 1]], $result->toArray());
        $this->assertInstanceOf($class, $result->nth(2), 'nested rows stay same-mode children');
    }

    #[DataProvider('modeProvider')]
    public function testKeysAndValuesOnEmpty(string $class): void
    {
        $this->assertSame([], $class::new([])->keys()->toArray());
        $this->assertSame([], $class::new([])->values()->toArray());
    }

    //endregion
}
