<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Closure;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Query metadata: mysqli(), root(), and the load handler.
 *
 * The database layer sets these three properties once, when it builds the result
 * set, and every derived array is still the same query's data, so all three have
 * to survive every transformation. The propagation loops run one literal method
 * list against both modes: a method that stops passing getInternalProperties()
 * fails with its own name in the data set label.
 *
 * The handler is a constructor property (setLoadHandler() was removed), and it
 * can't be read back, so it is checked by calling load() on the derived array.
 */
class MetadataTest extends SmartArrayTestCase
{
    //region Fixtures

    /** Shaped like the metadata the database layer stores after a query. */
    private const METADATA = [
        'query'         => 'SELECT * FROM users',
        'baseTable'     => 'users',
        'affected_rows' => 2,
        'insert_id'     => 0,
    ];

    private static function rows(): array
    {
        return [
            ['id' => 1, 'city' => 'NYC', 'tags' => "\tmenu\tfooter\t"],
            ['id' => 2, 'city' => 'LA', 'tags' => "\tmenu\t"],
        ];
    }

    /** Nested rows with metadata and a load handler, the shape the database layer returns. */
    private function sourceRows(string $class): SmartArrayBase
    {
        return new $class(self::rows(), [
            'mysqli'      => self::METADATA,
            'loadHandler' => self::loadHandler(),
        ]);
    }

    /** Flat values with the same properties, for the methods that reject nested arrays. */
    private function sourceValues(string $class): SmartArrayBase
    {
        return new $class(['b', 'a', 'b', 'c'], [
            'mysqli'      => self::METADATA,
            'loadHandler' => self::loadHandler(),
        ]);
    }

    /**
     * Stand-in for the database layer's handler: returns [rows, mysqliProperties].
     * It echoes $field back into both so tests can prove which handler ran.
     */
    private static function loadHandler(): Closure
    {
        return static fn(SmartArrayBase $array, string $field): array => [
            [['id' => 99, 'field' => $field]],
            ['baseTable' => $field],
        ];
    }

    /**
     * Build data sets that run one labelled case list against both modes.
     * Labels become the data set names, so a failure names the method.
     *
     * @param array<string, array<int, mixed>> $cases label => extra test arguments
     * @return array<string, array<int, mixed>>
     */
    private static function crossWithModes(array $cases): array
    {
        $rows = [];
        foreach (self::modeProvider() as $mode => [$class]) {
            foreach ($cases as $label => $args) {
                $rows["$mode: $label"] = [$class, ...$args];
            }
        }
        return $rows;
    }

    //endregion
    //region mysqli()

    #[DataProvider('modeProvider')]
    public function testMysqliWithNoArgumentReturnsTheWholeMetadataArray(string $class): void
    {
        $sa = $class::new(self::rows(), ['mysqli' => self::METADATA]);

        $this->assertSame([
            'query'         => 'SELECT * FROM users',
            'baseTable'     => 'users',
            'affected_rows' => 2,
            'insert_id'     => 0,
        ], $sa->mysqli(), 'keys and value types come back exactly as stored');
    }

    #[DataProvider('modeProvider')]
    public function testMysqliWithAKeyReturnsThatValueUnwrapped(string $class): void
    {
        // Metadata is diagnostics, not row data: values stay raw PHP types in both modes
        $sa = $class::new(self::rows(), ['mysqli' => self::METADATA]);

        $this->assertSame(2, $sa->mysqli('affected_rows'));
        $this->assertSame(0, $sa->mysqli('insert_id'));
        $this->assertSame('users', $sa->mysqli('baseTable'));
    }

    #[DataProvider('modeProvider')]
    public function testMysqliReturnsNullForAMissingKeyWithoutWarning(string $class): void
    {
        $sa = $class::new(self::rows(), ['mysqli' => self::METADATA]);

        [$result, $output] = $this->captureOutput(static fn() => $sa->mysqli('no_such_key'));

        $this->assertNull($result, 'the documented ->mysqli(\'affected_rows\') ?? 0 idiom relies on null');
        $this->assertSame('', $output, 'metadata keys vary by driver and query, so an unknown key is not a typo worth warning about');
    }

    #[DataProvider('modeProvider')]
    public function testMysqliReturnsEmptyArrayWhenNoMetadataWasSet(string $class): void
    {
        $sa = $class::new(self::rows());

        $this->assertSame([], $sa->mysqli(), 'arrays built in PHP have no query behind them');
    }

    #[DataProvider('modeProvider')]
    public function testMysqliReturnsNullForAnyKeyWhenNoMetadataWasSet(string $class): void
    {
        $sa = $class::new(self::rows());

        [$result, $output] = $this->captureOutput(static fn() => $sa->mysqli('affected_rows'));

        $this->assertNull($result);
        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testMysqliCannotTellAStoredNullFromAMissingKey(string $class): void
    {
        // Both go through ?? , so the keyed form collapses the two cases;
        // the no-arg form is the only way to see the key exists
        $sa = $class::new([], ['mysqli' => ['insert_id' => null]]);

        $this->assertNull($sa->mysqli('insert_id'));
        $this->assertNull($sa->mysqli('no_such_key'));
        $this->assertSame(['insert_id' => null], $sa->mysqli());
    }

    #[DataProvider('modeProvider')]
    public function testMysqliMetadataReachesEveryDescendant(string $class): void
    {
        $sa = $class::new(['users' => [['name' => 'Amy', 'roles' => ['admin']]]], ['mysqli' => self::METADATA]);

        $this->assertSame(self::METADATA, $sa->users->mysqli());
        $this->assertSame(self::METADATA, $sa->users->first()->mysqli());
        $this->assertSame(self::METADATA, $sa->users->first()->roles->mysqli(), 'grandchildren too');
    }

    //endregion
    //region root()

    #[DataProvider('modeProvider')]
    public function testRootOnFreshConstructionIsSelf(string $class): void
    {
        $sa    = $class::new(self::rows());
        $empty = $class::new();

        $this->assertSame($sa, $sa->root());
        $this->assertSame($empty, $empty->root(), 'an empty array is its own root too');
    }

    #[DataProvider('modeProvider')]
    public function testRootOnChildRowsIsTheTopLevelArray(string $class): void
    {
        $sa = $class::new(self::rows(), ['mysqli' => self::METADATA]);

        $this->assertSame($sa, $sa->first()->root());
        $this->assertSame($sa, $sa->last()->root());
        $this->assertSame($sa, $sa->at(1)->root());
    }

    #[DataProvider('modeProvider')]
    public function testRootOnDeeplyNestedArraysIsStillTheTopLevelArray(string $class): void
    {
        $sa = $class::new(['users' => [['name' => 'Amy', 'roles' => ['admin']]]]);

        $this->assertSame($sa, $sa->users->root());
        $this->assertSame($sa, $sa->users->first()->root());
        $this->assertSame($sa, $sa->users->first()->roles->root());
    }

    #[DataProvider('modeProvider')]
    public function testRootOnADerivedArrayIsTheSource(string $class): void
    {
        $sa = $class::new(self::rows(), ['mysqli' => self::METADATA]);

        $filtered = $sa->where('city', 'NYC');

        $this->assertSame($sa, $filtered->root(), 'a derived array points back at the query it came from');
        $this->assertSame($sa, $filtered->first()->root(), 'and so do its rows');
    }

    #[DataProvider('modeProvider')]
    public function testRootStaysTheSourceThroughChainedTransformations(string $class): void
    {
        $sa = $class::new(self::rows(), ['mysqli' => self::METADATA]);

        $result = $sa->filter()->map(static fn(array $row) => $row)->sortBy('id')->groupBy('city');

        $this->assertSame($sa, $result->root(), 'every step passes the original root along, not the previous step');
        $this->assertSame($sa, $result->first()->first()->root());
    }

    #[DataProvider('modeProvider')]
    public function testChildrenAddedAfterConstructionInheritRootAndMetadata(string $class): void
    {
        $sa = $class::new(['a' => ['id' => 1]], ['mysqli' => self::METADATA]);

        $sa->set('b', ['id' => 2]);
        $sa->c = ['id' => 3];

        $this->assertSame($sa, $sa->b->root());
        $this->assertSame($sa, $sa->c->root());
        $this->assertSame(self::METADATA, $sa->b->mysqli());
        $this->assertSame(self::METADATA, $sa->c->mysqli());
    }

    public function testRootKeepsPointingAtTheOtherModeAfterAsRaw(): void
    {
        // Conversion keeps the source's root pointer, same as where()/filter()/map().
        // root() is @internal and its consumers only read metadata, which is
        // mode-independent. ConversionTest owns the rest of the conversion contract.
        $source = SmartArrayHtml::new(self::rows(), ['mysqli' => self::METADATA]);

        $raw = $source->asRaw();

        $this->assertInstanceOf(SmartArray::class, $raw);
        $this->assertSame($source, $raw->root());
        $this->assertInstanceOf(SmartArrayHtml::class, $raw->root());
        $this->assertSame(self::METADATA, $raw->mysqli(), 'metadata itself converts cleanly');
    }

    //endregion
    //region Propagation through transformations

    /**
     * Every method that returns a new array from nested rows.
     *
     * @return array<string, array{class-string<SmartArrayBase>, Closure}>
     */
    public static function nestedTransformationProvider(): array
    {
        return self::crossWithModes([
            'filter()'      => [static fn(SmartArrayBase $sa) => $sa->filter()],
            'filter(fn)'    => [static fn(SmartArrayBase $sa) => $sa->filter(static fn(array $row) => $row['id'] > 0)],
            'where()'       => [static fn(SmartArrayBase $sa) => $sa->where('city', 'NYC')],
            'whereNot()'    => [static fn(SmartArrayBase $sa) => $sa->whereNot('city', 'LA')],
            'whereInList()' => [static fn(SmartArrayBase $sa) => $sa->whereInList('tags', 'menu')],
            'sortBy()'      => [static fn(SmartArrayBase $sa) => $sa->sortBy('id')],
            'map()'         => [static fn(SmartArrayBase $sa) => $sa->map(static fn(array $row) => $row)],
            'merge()'       => [static fn(SmartArrayBase $sa) => $sa->merge([['id' => 3, 'city' => 'SF', 'tags' => '']])],
            'indexBy()'     => [static fn(SmartArrayBase $sa) => $sa->indexBy('id')],
            'groupBy()'     => [static fn(SmartArrayBase $sa) => $sa->groupBy('city')],
            'column()'      => [static fn(SmartArrayBase $sa) => $sa->column('id')],
            'column(null)'  => [static fn(SmartArrayBase $sa) => $sa->column(null, 'id')],
            'columnAt()'    => [static fn(SmartArrayBase $sa) => $sa->columnAt(0)],
            'keys()'        => [static fn(SmartArrayBase $sa) => $sa->keys()],
            'values()'      => [static fn(SmartArrayBase $sa) => $sa->values()],
        ]);
    }

    /**
     * The methods that need a flat array (sort/unique throw on nested), plus the
     * shape-agnostic ones again on flat input.
     *
     * @return array<string, array{class-string<SmartArrayBase>, Closure}>
     */
    public static function flatTransformationProvider(): array
    {
        return self::crossWithModes([
            'sort()'     => [static fn(SmartArrayBase $sa) => $sa->sort()],
            'sort(flag)' => [static fn(SmartArrayBase $sa) => $sa->sort(SORT_NATURAL)],
            'unique()'   => [static fn(SmartArrayBase $sa) => $sa->unique()],
            'filter()'   => [static fn(SmartArrayBase $sa) => $sa->filter()],
            'map()'      => [static fn(SmartArrayBase $sa) => $sa->map('strtoupper')],
            'merge()'    => [static fn(SmartArrayBase $sa) => $sa->merge(['d'])],
            'keys()'     => [static fn(SmartArrayBase $sa) => $sa->keys()],
            'values()'   => [static fn(SmartArrayBase $sa) => $sa->values()],
        ]);
    }

    /**
     * Deprecated aliases that still return a new array. They run the same
     * property-passing code, so a refactor breaks them the same way.
     *
     * @return array<string, array{class-string<SmartArrayBase>, Closure, string}>
     */
    public static function deprecatedTransformationProvider(): array
    {
        return self::crossWithModes([
            'pluck()'    => [static fn(SmartArrayBase $sa) => $sa->pluck('id'), ''],
            'pluckNth()' => [static fn(SmartArrayBase $sa) => $sa->pluckNth(0), ''],
            'each()'     => [static fn(SmartArrayBase $sa) => $sa->each(static fn($value, $key) => null), ''],
            'smartMap()' => [static fn(SmartArrayBase $sa) => $sa->smartMap(static fn($value, $key) => $value), '->smartMap() is deprecated, use ->map() instead'],
            'chunk()'    => [static fn(SmartArrayBase $sa) => $sa->chunk(1), '->chunk() is deprecated and will be removed in a future version'],
        ]);
    }

    #[DataProvider('nestedTransformationProvider')]
    public function testNestedTransformationsKeepMysqliRootAndMode(string $class, Closure $transform): void
    {
        $source = $this->sourceRows($class);

        [$result, $output] = $this->captureOutput(static fn() => $transform($source));

        $this->assertInstanceOf($class, $result);
        $this->assertMetadataPreserved($source, $result);
        $this->assertSame(self::METADATA, $result->mysqli());
        $this->assertSame($source, $result->root());
        $this->assertSame('', $output, 'every field named above exists in the rows, so nothing warns');
        $this->assertValidStructure($result);
    }

    #[DataProvider('nestedTransformationProvider')]
    public function testNestedTransformationsKeepTheLoadHandler(string $class, Closure $transform): void
    {
        $source = $this->sourceRows($class);
        $result = $transform($source);

        // The handler isn't readable, so run it. load() throws on record sets,
        // so descend to the first array that holds no child arrays.
        $row = $result;
        while ($row->first() instanceof SmartArrayBase) {
            $row = $row->first();
        }

        $this->assertSame([['id' => 99, 'field' => 'related']], $row->load('related')->toArray(), 'derived arrays can still load related data');
    }

    #[DataProvider('flatTransformationProvider')]
    public function testFlatTransformationsKeepMysqliRootAndMode(string $class, Closure $transform): void
    {
        $source = $this->sourceValues($class);

        [$result, $output] = $this->captureOutput(static fn() => $transform($source));

        $this->assertInstanceOf($class, $result);
        $this->assertMetadataPreserved($source, $result);
        $this->assertSame(self::METADATA, $result->mysqli());
        $this->assertSame($source, $result->root());
        $this->assertSame('', $output);
        $this->assertValidStructure($result);
    }

    #[DataProvider('deprecatedTransformationProvider')]
    public function testDeprecatedTransformationsKeepMysqliRootAndMode(string $class, Closure $transform, string $expectedNotice): void
    {
        $source = $this->sourceRows($class);

        [$result, $notices] = $this->captureDeprecations(static fn() => $transform($source));

        $this->assertMetadataPreserved($source, $result);
        $this->assertSame(self::METADATA, $result->mysqli());
        $this->assertSame($source, $result->root());

        if ($expectedNotice === '') {
            $this->assertSame([], $notices, 'renamed aliases are silent at runtime; the IDE shows the strikethrough');
            return;
        }
        $this->assertCount(1, $notices);
        $this->assertStringStartsWith($expectedNotice, $notices[0]);
    }

    public function testSprintfKeepsMetadataWhileSwitchingToRawMode(): void
    {
        // sprintf() is the one transformation whose result changes mode, so its
        // metadata handling can't ride on assertMetadataPreserved's class check
        $source = SmartArrayHtml::new(['a', 'b'], ['mysqli' => self::METADATA]);

        $result = $source->sprintf('<td>{value}</td>');

        $this->assertInstanceOf(SmartArray::class, $result, 'raw so the finished HTML is not re-encoded on output');
        $this->assertSame(self::METADATA, $result->mysqli());
        $this->assertSame($source, $result->root());
    }

    //endregion
    //region Load handler

    #[DataProvider('modeProvider')]
    public function testLoadUsesTheHandlerInheritedByADerivedArray(string $class): void
    {
        $row = $this->sourceRows($class)->where('city', 'LA')->first();

        $loaded = $row->load('orders');

        $this->assertInstanceOf($class, $loaded, 'loaded data comes back in the same mode');
        $this->assertSame([['id' => 99, 'field' => 'orders']], $loaded->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testLoadResultTakesTheHandlersMetadataAndStartsItsOwnRoot(string $class): void
    {
        $loaded = $this->sourceRows($class)->first()->load('orders');

        $this->assertSame(['baseTable' => 'orders'], $loaded->mysqli(), "the handler's metadata replaces the original query's");
        $this->assertSame($loaded, $loaded->root(), 'a load() result is a new query, so it is its own root');
    }

    #[DataProvider('modeProvider')]
    public function testLoadHandlerReachesRowsOfALoadedResult(string $class): void
    {
        $loaded = $this->sourceRows($class)->first()->load('orders');

        $this->assertSame([['id' => 99, 'field' => 'items']], $loaded->first()->load('items')->toArray(), 'the handler carries into loaded data so chains keep working');
    }

    //endregion
}
