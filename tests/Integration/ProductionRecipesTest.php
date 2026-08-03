<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Integration;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\SmartNull;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The production recipe catalog (__pattern-sweep-findings.md) as end-to-end chains.
 *
 * Every test is a literal chain from the sweep, run against mysqli-shaped rows
 * (string values, query metadata set) with the final output pinned exactly. If
 * these stay green through a refactor, real sites still render.
 *
 * Some recipes call methods that are now deprecated aliases (pluck, pluckNth,
 * nth, sprintf). They are at the Silent stage: no runtime notice, and production
 * code still calls them, so the recipes call them exactly as production does.
 */
class ProductionRecipesTest extends SmartArrayTestCase
{
    //region Fixtures

    /** SHOW COLUMNS output, as mysqli returns it: every value a string or null. */
    private static function showColumns(): array
    {
        return [
            ['Field' => 'id',    'Type' => 'int(10) unsigned', 'Null' => 'NO',  'Key' => 'PRI', 'Default' => null, 'Extra' => 'auto_increment'],
            ['Field' => 'name',  'Type' => 'varchar(255)',     'Null' => 'NO',  'Key' => '',    'Default' => '',   'Extra' => ''],
            ['Field' => 'email', 'Type' => 'varchar(255)',     'Null' => 'YES', 'Key' => 'UNI', 'Default' => null, 'Extra' => ''],
        ];
    }

    private static function showColumnsMeta(): array
    {
        return ['mysqli' => ['query' => 'SHOW COLUMNS FROM `users`', 'baseTable' => 'users']];
    }

    /** A CMS page list: hidden flag as '0'/'1', checkbox group as a tab-delimited list. */
    private static function pages(): array
    {
        return [
            ['num' => '1', 'title' => 'Home',   'hidden' => '0', 'showIn' => "\tmenu\tfooter\t"],
            ['num' => '2', 'title' => 'About',  'hidden' => '0', 'showIn' => "\tmenu\t"],
            ['num' => '3', 'title' => 'Secret', 'hidden' => '1', 'showIn' => "\tmenu\t"],
            ['num' => '4', 'title' => 'Terms',  'hidden' => '0', 'showIn' => "\tfooter\t"],
        ];
    }

    private static function pagesMeta(): array
    {
        return ['mysqli' => ['query' => 'SELECT * FROM `pages` ORDER BY `num`', 'baseTable' => 'pages']];
    }

    //endregion
    //region A recipes: shaping query results

    /**
     * A1: `->pluck('col')->toArray()` for a flat value list.
     * Production source: column-name lists from SHOW COLUMNS, the most common idiom in the sweep.
     */
    #[DataProvider('modeProvider')]
    public function testA1PluckToArrayReturnsFlatValueList(string $class): void
    {
        $result = $class::new(self::showColumns(), self::showColumnsMeta());

        [$fields, $output] = $this->captureOutput(fn() => $result->pluck('Field')->toArray());

        $this->assertSame(['id', 'name', 'email'], $fields);
        $this->assertSame('', $output);
    }

    /**
     * A2: two-arg `->pluck($valueCol, $keyCol)->toArray()` for a lookup map.
     * Production source: table-size listing keyed by table name.
     */
    #[DataProvider('modeProvider')]
    public function testA2TwoArgPluckReturnsKeyedMap(string $class): void
    {
        $result = $class::new([
            ['TABLE_NAME' => 'accounts', 'bytes' => '16384'],
            ['TABLE_NAME' => 'orders',   'bytes' => '49152'],
            ['TABLE_NAME' => 'users',    'bytes' => '32768'],
        ], ['mysqli' => ['query' => 'SELECT TABLE_NAME, DATA_LENGTH AS bytes FROM information_schema.TABLES', 'baseTable' => 'TABLES']]);

        [$sizes, $output] = $this->captureOutput(fn() => $result->pluck('bytes', 'TABLE_NAME')->toArray());

        $this->assertSame([
            'accounts' => '16384',
            'orders'   => '49152',
            'users'    => '32768',
        ], $sizes);
        $this->assertSame('', $output);
    }

    /**
     * A3: `->indexBy('col')->toArray()` for whole rows keyed by a field.
     * Production source: schema-listing pattern, memoized per table with `??=`.
     */
    #[DataProvider('modeProvider')]
    public function testA3IndexByReturnsRowsKeyedByField(string $class): void
    {
        $result = $class::new(self::showColumns(), self::showColumnsMeta());

        [$byField, $output] = $this->captureOutput(fn() => $result->indexBy('Field')->toArray());

        $this->assertSame([
            'id'    => ['Field' => 'id',    'Type' => 'int(10) unsigned', 'Null' => 'NO',  'Key' => 'PRI', 'Default' => null, 'Extra' => 'auto_increment'],
            'name'  => ['Field' => 'name',  'Type' => 'varchar(255)',     'Null' => 'NO',  'Key' => '',    'Default' => '',   'Extra' => ''],
            'email' => ['Field' => 'email', 'Type' => 'varchar(255)',     'Null' => 'YES', 'Key' => 'UNI', 'Default' => null, 'Extra' => ''],
        ], $byField);
        $this->assertSame('', $output);
        $this->assertValidStructure($result->indexBy('Field'));
    }

    /**
     * A4: `->pluckNth(0)->sort()->toArray()` for a positional column.
     * Production source: SHOW TABLES, whose single column name varies with the database name.
     */
    #[DataProvider('modeProvider')]
    public function testA4PluckNthZeroThenSortReturnsSortedColumn(string $class): void
    {
        $result = $class::new([
            ['Tables_in_shop' => 'orders'],
            ['Tables_in_shop' => 'accounts'],
            ['Tables_in_shop' => 'users'],
        ], ['mysqli' => ['query' => 'SHOW TABLES', 'baseTable' => null]]);

        [$tables, $output] = $this->captureOutput(fn() => $result->pluckNth(0)->sort()->toArray());

        $this->assertSame(['accounts', 'orders', 'users'], $tables);
        $this->assertSame('', $output);
    }

    /**
     * A5: `->first()->{'Column Name'}->value()` and `->first()->at(1)->value()` for a single cell.
     * Production source: SHOW CREATE TABLE, where the wanted cell is the second column.
     */
    public function testA5FirstCellByNameAndByPositionMatch(): void
    {
        $createSql = "CREATE TABLE `users` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB";
        $result    = SmartArrayHtml::new(
            [['Table' => 'users', 'Create Table' => $createSql]],
            ['mysqli' => ['query' => 'SHOW CREATE TABLE `users`', 'baseTable' => 'users']],
        );

        [$byName, $nameOutput]         = $this->captureOutput(fn() => $result->first()->{'Create Table'}->value());
        [$byPosition, $positionOutput] = $this->captureOutput(fn() => $result->first()->at(1)->value());

        $this->assertSame($createSql, $byName);
        $this->assertSame($createSql, $byPosition);
        $this->assertSame('', $nameOutput);
        $this->assertSame('', $positionOutput);
    }

    /**
     * A6: `->pluck('col')->contains($value)` as a membership test.
     * Production source: "does this index already exist" checks against SHOW INDEX.
     */
    #[DataProvider('modeProvider')]
    public function testA6PluckContainsChecksColumnMembership(string $class): void
    {
        $result = $class::new([
            ['Table' => 'people', 'Key_name' => 'PRIMARY', 'Column_name' => 'PersonID', 'Seq_in_index' => '1'],
            ['Table' => 'people', 'Key_name' => 'email',   'Column_name' => 'Email',    'Seq_in_index' => '1'],
        ], ['mysqli' => ['query' => 'SHOW INDEX FROM `people`', 'baseTable' => 'people']]);

        [$indexed, $output] = $this->captureOutput(fn() => [
            $result->pluck('Column_name')->contains('PersonID'),
            $result->pluck('Column_name')->contains('CompanyID'),
        ]);

        $this->assertSame([true, false], $indexed);
        $this->assertSame('', $output);
    }

    /**
     * A7: `->where(...)->whereNot(...)->whereInList(...)` on one loaded collection.
     * Production source: site template that loads pages once and slices menus out of it in memory.
     */
    #[DataProvider('modeProvider')]
    public function testA7WhereChainFiltersOneLoadedCollection(string $class): void
    {
        $pages = $class::new(self::pages(), self::pagesMeta());

        [$menu, $output] = $this->captureOutput(
            fn() => $pages->where('hidden', '0')->whereNot('title', 'Terms')->whereInList('showIn', 'menu'),
        );

        $this->assertSame([
            0 => ['num' => '1', 'title' => 'Home',  'hidden' => '0', 'showIn' => "\tmenu\tfooter\t"],
            1 => ['num' => '2', 'title' => 'About', 'hidden' => '0', 'showIn' => "\tmenu\t"],
        ], $menu->toArray(), 'source keys survive the whole chain');
        $this->assertSame(4, $pages->count(), 'source collection unchanged');
        $this->assertSame('', $output);
        $this->assertValidStructure($menu);
    }

    /**
     * A8: `->where($col, $value)->first()->field` to take one field off one row.
     * Production source: reading a single engine's Support column out of SHOW ENGINES.
     */
    #[DataProvider('modeProvider')]
    public function testA8WhereThenFirstReturnsFieldInModeType(string $class): void
    {
        $engines = $class::new([
            ['Engine' => 'MEMORY', 'Support' => 'YES',     'Comment' => 'Hash based, stored in memory'],
            ['Engine' => 'InnoDB', 'Support' => 'DEFAULT', 'Comment' => 'Supports transactions'],
            ['Engine' => 'MyISAM', 'Support' => 'YES',     'Comment' => 'MyISAM storage engine'],
        ], ['mysqli' => ['query' => 'SHOW ENGINES', 'baseTable' => null]]);

        [$support, $output] = $this->captureOutput(fn() => $engines->where('Engine', 'InnoDB')->first()->Support);

        $this->assertModeValue('DEFAULT', $support, $class);
        $this->assertSame('', $output);
    }

    /**
     * A9: `->map(fn(array $row) => [...])->toArray()` to reshape rows.
     * Production source: field-editor screen turning SHOW COLUMNS rows into its own field descriptors.
     */
    #[DataProvider('modeProvider')]
    public function testA9MapReshapesRowsIntoNewKeys(string $class): void
    {
        $columns = $class::new(self::showColumns(), self::showColumnsMeta());

        [$fields, $output] = $this->captureOutput(fn() => $columns->map(fn(array $row) => [
            'name'     => $row['Field'],
            'type'     => $row['Type'],
            'required' => $row['Null'] === 'NO',
        ])->toArray());

        $this->assertSame([
            ['name' => 'id',    'type' => 'int(10) unsigned', 'required' => true],
            ['name' => 'name',  'type' => 'varchar(255)',     'required' => true],
            ['name' => 'email', 'type' => 'varchar(255)',     'required' => false],
        ], $fields, 'the callback receives raw arrays in both modes');
        $this->assertSame('', $output);
    }

    /**
     * A10: `->groupBy('col')` then foreach over buckets, plus dynamic `$grouped->{$key}` access.
     * Production source: error-log screen and statement screens that render one section per group.
     */
    #[DataProvider('modeProvider')]
    public function testA10GroupByBucketsSurviveForeachAndDynamicAccess(string $class): void
    {
        $log = $class::new([
            ['id' => '1', 'type' => 'error',  'message' => 'Disk full'],
            ['id' => '2', 'type' => 'notice', 'message' => 'Cache cleared'],
            ['id' => '3', 'type' => 'error',  'message' => 'Timeout'],
        ], ['mysqli' => ['query' => 'SELECT * FROM `log` ORDER BY `id`', 'baseTable' => 'log']]);

        [$grouped, $output] = $this->captureOutput(fn() => $log->groupBy('type'));

        $summary = [];
        foreach ($grouped as $type => $rows) {
            $summary[] = "$type:" . $rows->count();
        }

        $this->assertSame(['error', 'notice'], $grouped->keys()->toArray(), 'bucket order follows first appearance');
        $this->assertSame(['error:2', 'notice:1'], $summary);
        $this->assertSame(['Disk full', 'Timeout'], $grouped->error->pluck('message')->toArray(), 'dynamic bucket access by property');
        $this->assertSame(['Cache cleared'], $grouped->{'notice'}->pluck('message')->toArray(), 'dynamic bucket access by variable key');
        $this->assertSame('', $output);
        $this->assertValidStructure($grouped);
    }

    /**
     * A11: `->sortBy($field, SORT_NATURAL)` for human-order sorting.
     * Production source: fund-selection screen listing names with numeric suffixes.
     */
    #[DataProvider('modeProvider')]
    public function testA11SortByNaturalOrdersNumericSuffixes(string $class): void
    {
        $funds = $class::new([
            ['FundID' => '3', 'FundName' => 'Fund 10'],
            ['FundID' => '1', 'FundName' => 'Fund 2'],
            ['FundID' => '2', 'FundName' => 'Fund 1'],
        ], ['mysqli' => ['query' => 'SELECT * FROM `funds`', 'baseTable' => 'funds']]);

        [$sorted, $output] = $this->captureOutput(fn() => $funds->sortBy('FundName', SORT_NATURAL));

        $this->assertSame([
            ['FundID' => '2', 'FundName' => 'Fund 1'],
            ['FundID' => '1', 'FundName' => 'Fund 2'],
            ['FundID' => '3', 'FundName' => 'Fund 10'],
        ], $sorted->toArray(), 'natural order puts Fund 2 before Fund 10');
        $this->assertSame(['Fund 1', 'Fund 10', 'Fund 2'], $funds->sortBy('FundName')->pluck('FundName')->toArray(), 'default flags sort as strings');
        $this->assertSame('', $output);
        $this->assertValidStructure($sorted);
    }

    /**
     * A12: `->pluck($col)->filter(fn)->toArray()` for a filtered value list.
     * Production source: narrowing a table list to the ones carrying the install's prefix.
     */
    #[DataProvider('modeProvider')]
    public function testA12FilteredPluckKeepsOriginalKeys(string $class): void
    {
        $tables = $class::new([
            ['TABLE_NAME' => 'cms_accounts'],
            ['TABLE_NAME' => 'wp_posts'],
            ['TABLE_NAME' => 'cms_orders'],
        ], ['mysqli' => ['query' => 'SELECT TABLE_NAME FROM information_schema.TABLES', 'baseTable' => 'TABLES']]);

        [$ours, $output] = $this->captureOutput(
            fn() => $tables->pluck('TABLE_NAME')->filter(fn($name) => str_starts_with($name, 'cms_'))->toArray(),
        );

        // filter() keeps source keys like array_filter(), so this value list comes back
        // with a gap (0 and 2) and json_encode() would emit an object, not an array.
        // Chain ->values() to reindex when that matters (noted in the filter() phpdoc).
        $this->assertSame([0 => 'cms_accounts', 2 => 'cms_orders'], $ours);
        $this->assertSame('', $output);
    }

    /**
     * A13: `SmartArray::new($plainArray)->where([...])->keys()->toArray()` to query a config array.
     * Production source: pulling the upload-type field names out of a table's field schema.
     */
    #[DataProvider('modeProvider')]
    public function testA13WrapPlainArrayThenWhereKeys(string $class): void
    {
        $schema = [
            'photo'  => ['type' => 'upload',    'label' => 'Photo'],
            'title'  => ['type' => 'textfield', 'label' => 'Title'],
            'resume' => ['type' => 'upload',    'label' => 'Resume'],
        ];

        [$uploadFields, $deprecations] = $this->captureDeprecations(
            fn() => $class::new($schema)->where(['type' => 'upload'])->keys()->toArray(),
        );

        $this->assertSame(['photo', 'resume'], $uploadFields, 'field names, not the rows');
        $this->assertCount(1, $deprecations, 'the array form of where() is deprecated but still works');
        $this->assertStringContainsString("Replace ->where([...]) with ->where('type', 'upload')", $deprecations[0]);
    }

    /**
     * A14: `->pluck('id')->map('intval')->implode(',')->or('0')->string()` to build an IN list.
     * Production source: composing a raw SQL string where the id list may be empty.
     */
    public function testA14PluckMapImplodeBuildsCsvForRawSql(): void
    {
        $meta = ['mysqli' => ['query' => 'SELECT `id` FROM `trackers` WHERE `active` = 1', 'baseTable' => 'trackers']];
        $rows = SmartArrayHtml::new([['id' => '5'], ['id' => '12'], ['id' => '9']], $meta);
        $none = SmartArrayHtml::new([], $meta);

        [$csv, $csvOutput]     = $this->captureOutput(fn() => $rows->pluck('id')->map('intval')->implode(',')->or('0')->string());
        [$empty, $emptyOutput] = $this->captureOutput(fn() => $none->pluck('id')->map('intval')->implode(',')->or('0')->string());

        $this->assertSame('5,12,9', $csv);
        $this->assertSame('0', $empty, "or('0') keeps the SQL valid when nothing matched");
        $this->assertSame('', $csvOutput);
        $this->assertSame('', $emptyOutput);
    }

    /**
     * A15: `$row->root()->pluck($col)->merge([$x])->toArray()` from inside a nested row.
     * Production source: load handler batching every sibling row's foreign keys into one query.
     */
    #[DataProvider('modeProvider')]
    public function testA15RootFromNestedRowReachesSiblingValues(string $class): void
    {
        $articles = $class::new([
            ['num' => '1', 'author_id' => '7', 'title' => 'First'],
            ['num' => '2', 'author_id' => '9', 'title' => 'Second'],
            ['num' => '3', 'author_id' => '7', 'title' => 'Third'],
        ], ['mysqli' => ['query' => 'SELECT * FROM `articles`', 'baseTable' => 'articles']]);

        $row = $articles->at(1);

        [$idsToFetch, $output] = $this->captureOutput(fn() => $row->root()->pluck('author_id')->merge(['0'])->toArray());

        $this->assertSame($articles, $row->root(), 'a row reaches the result set it came from');
        $this->assertSame(['7', '9', '7', '0'], $idsToFetch, 'merge renumbers and appends the placeholder id');
        $this->assertSame('', $output);
    }

    //endregion
    //region B recipes: rendering

    /**
     * B11: the loop-free table: `->first()->keys()->sprintf('<th>{value}</th>')->implode("\n")`
     * plus a per-row `<td>` chain.
     * Production source: generic record-table screen that renders any query without knowing its columns.
     */
    public function testB11LoopFreeTableRendersEncodedHtml(): void
    {
        $rows = SmartArrayHtml::new([
            ['id' => '1', 'name' => "O'Brien & Sons", 'note' => '<b>bold</b>'],
            ['id' => '2', 'name' => 'Ünïcödé "quoted"', 'note' => ''],
        ], ['mysqli' => ['query' => 'SELECT * FROM `customers`', 'baseTable' => 'customers']]);

        [$header, $headerOutput] = $this->captureOutput(fn() => $rows->first()->keys()->sprintf('<th>{value}</th>')->implode("\n"));

        $cells = [];
        [, $cellOutput] = $this->captureOutput(function () use ($rows, &$cells) {
            foreach ($rows as $row) {
                $cells[] = $row->sprintf('<td>{value}</td>')->implode("\n");
            }
        });

        $this->assertSame("<th>id</th>\n<th>name</th>\n<th>note</th>", $header);
        $this->assertSame([
            "<td>1</td>\n<td>O&apos;Brien &amp; Sons</td>\n<td>&lt;b&gt;bold&lt;/b&gt;</td>",
            "<td>2</td>\n<td>Ünïcödé &quot;quoted&quot;</td>\n<td></td>",
        ], $cells, 'values are encoded once, the tags are not');
        $this->assertSame('', $headerOutput);
        $this->assertSame('', $cellOutput);
    }

    /**
     * B12: `position()` against a row limit and `isLast()` for separators, inside a foreach.
     * Production source: dashboard list that shows the first few rows and a "more" marker.
     */
    public function testB12PositionAndIsLastControlLoopOutput(): void
    {
        $leads = SmartArrayHtml::new([
            ['name' => 'Amy'],
            ['name' => 'Bob'],
            ['name' => 'Cid'],
            ['name' => 'Dan'],
            ['name' => 'Eve'],
        ], ['mysqli' => ['query' => 'SELECT `name` FROM `leads`', 'baseTable' => 'leads']]);

        $rowLimit = 3;
        $rendered = '';
        $flags    = [];

        [, $output] = $this->captureOutput(function () use ($leads, $rowLimit, &$rendered, &$flags) {
            foreach ($leads as $lead) {
                $flags[] = $lead->position() . ($lead->isFirst() ? 'F' : '-') . ($lead->isLast() ? 'L' : '-');
                if ($lead->position() > $rowLimit) {
                    continue;
                }
                $rendered .= $lead->name->string();
                if (!$lead->isLast()) {
                    $rendered .= ', ';
                }
            }
        });

        $this->assertSame('Amy, Bob, Cid, ', $rendered, 'the last shown row is not the last row, so it keeps its separator');
        $this->assertSame(['1F-', '2--', '3--', '4--', '5-L'], $flags);
        $this->assertSame('', $output);
    }

    //endregion
    //region C and E recipes: empty results and metadata

    /**
     * C5: `->first()->asHtml()` so a possibly-empty result still auto-escapes downstream.
     * Production source: login screen normalizing a single-account lookup that may return nothing.
     */
    public function testC5FirstAsHtmlOnEmptyResultReturnsEmptyHtmlArray(): void
    {
        $meta   = ['mysqli' => ['query' => 'SELECT * FROM `accounts` WHERE `id` = 0', 'baseTable' => 'accounts']];
        $noRows = SmartArrayHtml::new([], $meta);

        [$account, $output] = $this->captureOutput(fn() => $noRows->first()->asHtml());

        $this->assertInstanceOf(SmartNull::class, $noRows->first(), 'first() on an empty result is a SmartNull');
        $this->assertInstanceOf(SmartArrayHtml::class, $account, 'asHtml() turns it into an empty typed collection');
        $this->assertSame([], $account->toArray());
        $this->assertSame(true, $account->isEmpty());
        $this->assertSame([
            'query'     => 'SELECT * FROM `accounts` WHERE `id` = 0',
            'baseTable' => 'accounts',
        ], $account->mysqli(), 'query metadata survives the normalize');
        $this->assertSame('', $output);

        // Missing fields on the normalized result read as empty, no warning
        [$name, $fieldOutput] = $this->captureOutput(fn() => $account->fullname);
        $this->assertInstanceOf(SmartNull::class, $name);
        $this->assertSame('', (string)$name);
        $this->assertSame('', $fieldOutput);

        // Same normalize starting from raw mode
        $this->assertInstanceOf(SmartArrayHtml::class, SmartArray::new([], $meta)->first()->asHtml());
    }

    /**
     * E1: `->mysqli('affected_rows') ?? 0` after a write.
     * Production source: shared helper reporting how many rows a write touched.
     */
    public function testE1MysqliAffectedRowsFallsBackToZeroWithoutMetadata(): void
    {
        $update = SmartArray::new([], ['mysqli' => [
            'query'          => 'UPDATE `orders` SET `paid` = 1 WHERE `id` = 3',
            'affected_rows'  => 2,
            'insert_id'      => 0,
            'baseTable'      => 'orders',
        ]]);
        $plain  = SmartArray::new([['id' => '1']]);

        $this->assertSame(2, $update->mysqli('affected_rows') ?? 0);
        $this->assertSame(0, $update->mysqli('insert_id') ?? 0);
        $this->assertSame('UPDATE `orders` SET `paid` = 1 WHERE `id` = 3', $update->mysqli('query'));
        $this->assertSame([
            'query'         => 'UPDATE `orders` SET `paid` = 1 WHERE `id` = 3',
            'affected_rows' => 2,
            'insert_id'     => 0,
            'baseTable'     => 'orders',
        ], $update->mysqli(), 'no-arg call returns every property');
        $this->assertSame(0, $plain->mysqli('affected_rows') ?? 0, 'arrays built by hand have no metadata');
        $this->assertSame([], $plain->mysqli());
    }

    /**
     * E2: `->mysqli('baseTable')` to learn which table a result came from.
     * Production source: load handler deciding which table to query for related rows.
     */
    public function testE2MysqliBaseTableReportsOriginTableOnRowsToo(): void
    {
        $orders = SmartArray::new(
            [['id' => '1', 'customer_id' => '4'], ['id' => '2', 'customer_id' => '9']],
            ['mysqli' => ['query' => 'SELECT * FROM `orders`', 'baseTable' => 'orders']],
        );

        $this->assertSame('orders', $orders->mysqli('baseTable'));
        $this->assertSame('orders', $orders->first()->mysqli('baseTable'), 'rows inherit the result set metadata');
        $this->assertSame('orders', $orders->where('customer_id', '4')->mysqli('baseTable'), 'derived arrays keep it too');
        $this->assertSame(null, SmartArray::new([['id' => '1']])->mysqli('baseTable'), 'missing property reads as null');
    }

    //endregion
}
