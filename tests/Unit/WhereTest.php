<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use InvalidArgumentException;
use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The where family: where(), whereNot(), whereInList().
 *
 * Pins the matching contract (strings exact, numbers numeric, null only null,
 * bools as 1/0) and the null/missing matrix decided in the review (Q8):
 *
 *     row state        where()          whereNot()       whereInList()
 *     field missing    excluded         KEPT             excluded
 *     field is null    matches null     matches null     always excluded
 */
class WhereTest extends SmartArrayTestCase
{
    //region where()

    #[DataProvider('modeProvider')]
    public function testWhereKeepsMatchingRowsAndKeys(string $class): void
    {
        $sa = $class::new([
            3 => ['status' => 'active', 'name' => 'Amy'],
            7 => ['status' => 'draft', 'name' => 'Bob'],
            9 => ['status' => 'active', 'name' => 'Cid'],
        ]);

        $result = $sa->where('status', 'active');

        $this->assertInstanceOf($class, $result);
        $this->assertSame([
            3 => ['status' => 'active', 'name' => 'Amy'],
            9 => ['status' => 'active', 'name' => 'Cid'],
        ], $result->toArray(), 'original keys preserved');
        $this->assertSame(3, $sa->count(), 'source unchanged');
        $this->assertMetadataPreserved($sa, $result);
        $this->assertValidStructure($result);
    }

    #[DataProvider('modeProvider')]
    public function testWhereMatchesNumbersAcrossTypes(string $class): void
    {
        // Database/form data often stores numbers as strings; a number on either
        // side compares numerically
        $sa = $class::new([
            ['id' => 1],
            ['id' => '1'],
            ['id' => 2],
        ]);

        $this->assertCount(2, $sa->where('id', 1), "int 1 matches '1'");
        $this->assertCount(2, $sa->where('id', '1'), "'1' matches int 1");

        $prices = $class::new([
            ['price' => '1.00'],   // DECIMAL columns come back as strings
            ['price' => '19.99'],
        ]);

        $this->assertCount(1, $prices->where('price', 1), "int 1 matches DECIMAL '1.00'");
        $this->assertCount(1, $prices->where('price', 19.99), "float 19.99 matches '19.99'");
    }

    #[DataProvider('modeProvider')]
    public function testWhereMatchesStringsAsExactText(string $class): void
    {
        // Two strings never compare numerically: PHP's numeric-string == would
        // match distinct hash-like values ('0e12' == '0e99' is true in raw PHP)
        $sa = $class::new([
            ['code' => '0e99'],
            ['code' => '1000'],
            ['code' => '01000'],
            ['code' => 'Apple'],
        ]);

        $this->assertCount(0, $sa->where('code', '0e12'), 'hash-like strings are exact text');
        $this->assertCount(1, $sa->where('code', '0e99'), 'identical strings match');
        $this->assertCount(0, $sa->where('code', '1e3'), 'no scientific notation crossover');
        $this->assertCount(1, $sa->where('code', '1000'), "leading-zero '01000' stays distinct from '1000'");
        $this->assertCount(0, $sa->where('code', 'apple'), 'case-sensitive');
    }

    #[DataProvider('modeProvider')]
    public function testWhereNullMatchesOnlyNull(string $class): void
    {
        // SQL IS NULL semantics: null is "no value", not the empty-ish family
        $sa = $class::new([
            ['f' => null],
            ['f' => ''],
            ['f' => 0],
            ['f' => false],
            ['f' => '0'],
            ['f' => 'x'],
        ]);

        $this->assertSame([0], $sa->where('f', null)->keys()->toArray(), 'null matches only null');
        $this->assertSame([1], $sa->where('f', '')->keys()->toArray(), "'' does not match a stored null");
        $this->assertSame([2, 3, 4], $sa->where('f', 0)->keys()->toArray(), "0 matches 0, false, and '0' but not null or ''");
    }

    #[DataProvider('modeProvider')]
    public function testWhereBoolsCompareAsOneAndZero(string $class): void
    {
        // MySQL stores checkbox/bool fields as tinyint 1/0, so true means 1, false means 0
        $sa = $class::new([
            1 => ['active' => 1],
            2 => ['active' => '1'],
            3 => ['active' => 0],
            4 => ['active' => '0'],
            5 => ['active' => 'abc'],
            6 => ['active' => null],
            7 => ['active' => ''],
            8 => ['active' => true],
            9 => ['active' => false],
        ]);

        $this->assertSame([1, 2, 8], $sa->where('active', true)->keys()->toArray(), "true means 1, not 'any truthy value'; stored true counts as 1");
        $this->assertSame([3, 4, 9], $sa->where('active', false)->keys()->toArray(), 'false means 0; null and "" are not 0; stored false counts as 0');
    }

    #[DataProvider('modeProvider')]
    public function testWhereAndWhereNotPartitionForEveryValueType(string $class): void
    {
        // Falsification: when the field exists in every row, where() and whereNot()
        // must split the set exactly in two for every value type the rule handles
        $sa = $class::new([
            ['f' => null], ['f' => ''], ['f' => 0], ['f' => '0'], ['f' => false],
            ['f' => 1], ['f' => '1'], ['f' => '0e99'], ['f' => '1.00'], ['f' => 'Apple'],
        ]);
        $total = $sa->count();

        foreach ([null, true, false, 0, 1, '', '0', '0e12', '0e99', 1.0, 'Apple', 'apple'] as $value) {
            $kept    = $sa->where('f', $value)->count();
            $dropped = $sa->whereNot('f', $value)->count();
            $this->assertSame($total, $kept + $dropped, 'where + whereNot must cover all rows for value: ' . var_export($value, true));
        }
    }

    #[DataProvider('modeProvider')]
    public function testWhereExcludesRowsMissingField(string $class): void
    {
        $sa = $class::new([
            'a' => ['f' => 5],
            'b' => ['other' => 5],
        ]);

        [$result, ] = $this->captureOutput(fn() => $sa->where('f', 5));

        $this->assertSame(['a' => ['f' => 5]], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testWhereThrowsOnScalarRows(string $class): void
    {
        $sa = $class::new(['config' => 'scalar', 'a' => ['f' => 5]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("where(): Expected a nested array of rows, but element 'config' is not a row (string)");

        $sa->where('f', 5);
    }

    #[DataProvider('modeProvider')]
    public function testWhereNotThrowsOnScalarRows(string $class): void
    {
        $sa = $class::new(['config' => 'scalar', 'a' => ['f' => 5]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("whereNot(): Expected a nested array of rows, but element 'config' is not a row (string)");

        $sa->whereNot('f', 5);
    }

    #[DataProvider('modeProvider')]
    public function testWhereInListThrowsOnScalarRows(string $class): void
    {
        $sa = $class::new(['config' => 'scalar', 'a' => ['tags' => "\tred\t"]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("whereInList(): Expected a nested array of rows, but element 'config' is not a row (string)");

        $sa->whereInList('tags', 'red');
    }

    #[DataProvider('modeProvider')]
    public function testWhereWorksAgainAfterScalarRowIsUnset(string $class): void
    {
        // Storing a scalar marks the array as not rows-only, and unset doesn't clear
        // the mark - the assert rescans, proves all remaining elements are rows, and passes
        $sa = $class::new(['config' => 'scalar', 'a' => ['f' => 5], 'b' => ['f' => 0]]);

        [$result, ] = $this->captureOutput(function () use ($sa) {
            unset($sa['config']); // bracket unset echoes a deprecation, not under test here
            return $sa->where('f', 5);
        });

        $this->assertSame(['a' => ['f' => 5]], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testWhereUnwrapsSmartStringValues(string $class): void
    {
        $sa = $class::new([['name' => 'Amy'], ['name' => 'Bob']]);

        $result = $sa->where('name', new SmartString('Amy'));

        $this->assertSame([['name' => 'Amy']], array_values($result->toArray()));
    }

    #[DataProvider('modeProvider')]
    public function testWhereArraySyntaxIsDeprecatedButChains(string $class): void
    {
        $sa = $class::new([
            ['status' => 'active', 'role' => 'admin'],
            ['status' => 'active', 'role' => 'user'],
            ['status' => 'draft', 'role' => 'admin'],
        ]);

        [$result, $deprecations] = $this->captureDeprecations(
            fn() => $sa->where(['status' => 'active', 'role' => 'admin'])
        );

        $this->assertSame([['status' => 'active', 'role' => 'admin']], array_values($result->toArray()), 'array conditions AND together');
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString("->where('status', 'active')->where('role', 'admin')", $deprecations[0], 'deprecation shows the chained replacement');
    }

    #[DataProvider('modeProvider')]
    public function testWhereArraySyntaxDeprecationFormatsEveryValueType(string $class): void
    {
        $sa = $class::new([['ids' => '1', 'active' => 1, 'deleted' => null, 'count' => 5]]);

        // the suggested replacement spells each value as PHP source: bools and null
        // by name, arrays as a [...] placeholder (no "Array to string conversion")
        [, $deprecations] = $this->captureDeprecations(
            fn() => $sa->where(['ids' => ['1', '2'], 'active' => true, 'deleted' => null, 'count' => 5])
        );

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString(
            "->where('ids', [...])->where('active', true)->where('deleted', null)->where('count', 5)",
            $deprecations[0],
        );
    }

    #[DataProvider('modeProvider')]
    public function testWhereArrayListFormThrowsWithHint(string $class): void
    {
        // where(['featured']) is a half-migration of where('featured', ...): a list
        // has no field names, so the error names the form the caller meant
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("where(): the array form takes ['field' => value] pairs, list given. Did you mean ->where('featured') to match rows where 'featured' is non-empty?");

        $class::new([['featured' => 1]])->where(['featured']);
    }

    #[DataProvider('modeProvider')]
    public function testWhereOnFlatThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('where(): Expected a nested array, but got a flat array');

        $class::new(['a', 'b'])->where('f', 1);
    }

    //endregion
    //region whereNot()

    #[DataProvider('modeProvider')]
    public function testWhereNotExcludesMatchesAndKeepsRowsMissingField(string $class): void
    {
        // The asymmetry with where(): "remove rows I KNOW match" keeps rows
        // that don't have the field at all
        $sa = $class::new([
            'a' => ['status' => 'draft'],
            'b' => ['status' => 'active'],
            'c' => ['name' => 'no status field'],
        ]);

        [$result, ] = $this->captureOutput(fn() => $sa->whereNot('status', 'draft'));

        $this->assertSame([
            'b' => ['status' => 'active'],
            'c' => ['name' => 'no status field'],
        ], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testWhereAndWhereNotPartitionOnlyWhenFieldPresent(string $class): void
    {
        // With the field everywhere: clean partition. A missing-field row
        // appears ONLY in whereNot()'s result - they are not strict complements.
        $sa = $class::new([
            'a' => ['f' => 1],
            'b' => ['f' => 2],
            'c' => ['g' => 3],
        ]);

        [$kept, ]    = $this->captureOutput(fn() => $sa->where('f', 1));
        [$dropped, ] = $this->captureOutput(fn() => $sa->whereNot('f', 1));

        $this->assertSame(['a'], $kept->keys()->toArray());
        $this->assertSame(['b', 'c'], $dropped->keys()->toArray(), "row 'c' is in neither match set, so whereNot keeps it");
    }

    #[DataProvider('modeProvider')]
    public function testWhereNotUsesSameMatchingRules(string $class): void
    {
        $sa = $class::new([['id' => 1], ['id' => '1'], ['id' => 2]]);

        $this->assertSame([2 => ['id' => 2]], $sa->whereNot('id', '1')->toArray(), "'1' excludes both int 1 and '1'");

        $codes = $class::new([['code' => '0e99'], ['code' => 'x']]);

        $this->assertCount(2, $codes->whereNot('code', '0e12'), 'hash-like strings are exact text, nothing excluded');
    }

    #[DataProvider('modeProvider')]
    public function testWhereNotOnFlatThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('whereNot(): Expected a nested array, but got a flat array');

        $class::new(['a', 'b'])->whereNot('f', 1);
    }

    //endregion
    //region single-argument forms

    #[DataProvider('modeProvider')]
    public function testWhereWithOnlyAFieldKeepsNonEmptyRows(string $class): void
    {
        // Single-argument where('field') uses PHP's empty() rule: NULL, false,
        // 0, "0", "", and missing fields are empty; everything else is kept
        $sa = $class::new([
            1 => ['featured' => 1],
            2 => ['featured' => 0],
            3 => ['featured' => '1'],
            4 => ['featured' => null],
            5 => ['featured' => ''],
            6 => ['featured' => '0'],
            7 => ['featured' => '0.0'],   // not empty to PHP
            8 => ['name' => 'no featured field'],
        ]);

        [$result, $output] = $this->captureOutput(fn() => $sa->where('featured'));

        $this->assertSame([1, 3, 7], $result->keys()->toArray());
        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testWhereNotWithOnlyAFieldKeepsEmptyRows(string $class): void
    {
        $sa = $class::new([
            1 => ['featured' => 1],
            2 => ['featured' => 0],
            4 => ['featured' => null],
            8 => ['name' => 'no featured field'],
        ]);

        [$result, $output] = $this->captureOutput(fn() => $sa->whereNot('featured'));

        $this->assertSame([2, 4, 8], $result->keys()->toArray());
        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testSingleArgumentFormsPartitionEveryRow(string $class): void
    {
        // Unlike the two-argument forms, where($f) and whereNot($f) are strict
        // complements: every row lands in exactly one result
        $sa = $class::new([
            1 => ['featured' => 1],
            2 => ['featured' => 0],
            3 => ['other' => 'x'],
        ]);

        [$kept, ]    = $this->captureOutput(fn() => $sa->where('featured'));
        [$dropped, ] = $this->captureOutput(fn() => $sa->whereNot('featured'));

        $this->assertSame([1], $kept->keys()->toArray());
        $this->assertSame([2, 3], $dropped->keys()->toArray());
    }

    //endregion
    //region whereInList()

    #[DataProvider('modeProvider')]
    public function testWhereInListMatchesDiscreteTabDelimitedValues(string $class): void
    {
        // CMS Builder checkbox-group format: "\tvalue\tvalue\t" or a plain single value
        $sa = $class::new([
            1 => ['show_on' => "\tmenu\tfooter\t"],   // delimited, menu first
            2 => ['show_on' => "\theader\tmenu\t"],   // delimited, menu last
            3 => ['show_on' => 'menu'],               // plain single value
            4 => ['show_on' => "\tmenuitem\t"],       // no substring match
            5 => ['show_on' => "\tfooter\t"],         // no match
        ]);

        $result = $sa->whereInList('show_on', 'menu');

        $this->assertSame([1, 2, 3], $result->keys()->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testWhereInListExcludesNullAndMissingFields(string $class): void
    {
        // Q8: null means "nothing selected" for list fields - always excluded
        $sa = $class::new([
            1 => ['show_on' => 'menu'],
            2 => ['show_on' => null],
            3 => ['other' => 'menu'],
        ]);

        [$result, ] = $this->captureOutput(fn() => $sa->whereInList('show_on', 'menu'));

        $this->assertSame([1], $result->keys()->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testWhereInListIsCaseSensitiveAndCastsNumbers(string $class): void
    {
        $sa = $class::new([
            1 => ['tags' => "\tMenu\t"],
            2 => ['tags' => "\tmenu\t"],
            3 => ['tags' => "\t1\t2\t"],
        ]);

        $this->assertSame([1], $sa->whereInList('tags', 'Menu')->keys()->toArray());
        $this->assertSame([3], $sa->whereInList('tags', 2)->keys()->toArray(), 'numeric search values cast to string');
    }

    #[DataProvider('modeProvider')]
    public function testWhereInListMatchesPlainValuesAsExactText(string $class): void
    {
        // The plain-single-value compare follows where(): string fields are exact
        // text, non-string fields still match a numeric search value
        $sa = $class::new([
            1 => ['code' => '0e99'],
            2 => ['code' => '1000'],
            3 => ['num'  => 2],
        ]);

        [$result, ] = $this->captureOutput(fn() => $sa->whereInList('code', '0e12'));
        $this->assertCount(0, $result, 'hash-like strings are exact text');

        [$result, ] = $this->captureOutput(fn() => $sa->whereInList('code', '1e3'));
        $this->assertCount(0, $result, 'no scientific notation crossover');

        [$result, ] = $this->captureOutput(fn() => $sa->whereInList('num', 2));
        $this->assertSame([3], $result->keys()->toArray(), 'int field matches numeric search value');
    }

    #[DataProvider('modeProvider')]
    public function testWhereInListMatchesBoolsAsOneAndZero(string $class): void
    {
        // Same 1/0 rule as where(): a stored bool can't loose-match arbitrary
        // strings (true == 'admin' is true in PHP, and used to match here)
        $sa = $class::new([
            1 => ['flag' => true],
            2 => ['flag' => false],
            3 => ['flag' => 'admin'],
        ]);

        $this->assertSame([1], $sa->whereInList('flag', true)->keys()->toArray());
        $this->assertSame([1], $sa->whereInList('flag', 1)->keys()->toArray());
        $this->assertSame([1], $sa->whereInList('flag', '1')->keys()->toArray());
        $this->assertSame([2], $sa->whereInList('flag', false)->keys()->toArray());
        $this->assertSame([2], $sa->whereInList('flag', 0)->keys()->toArray());
        $this->assertSame([3], $sa->whereInList('flag', 'admin')->keys()->toArray(), 'stored true must not match arbitrary strings');
    }

    #[DataProvider('modeProvider')]
    public function testWhereInListNullAndEmptyStringSearchValuesStayDistinct(string $class): void
    {
        // Used to stringify the search value, so null and false collapsed to ''
        $sa = $class::new([
            1 => ['note' => ''],
            2 => ['note' => null],
            3 => ['note' => 'x'],
        ]);

        $this->assertSame([1], $sa->whereInList('note', '')->keys()->toArray(), "'' matches only '' fields");
        $this->assertCount(0, $sa->whereInList('note', null), 'null matches nothing - null fields mean "nothing selected"');
        $this->assertCount(0, $sa->whereInList('note', false), "false matches 0, not ''");
    }

    #[DataProvider('modeProvider')]
    public function testWhereInListUnwrapsSmartStringValues(string $class): void
    {
        $sa = $class::new([1 => ['show_on' => "\tmenu\t"]]);

        $this->assertCount(1, $sa->whereInList('show_on', new SmartString('menu')));
    }

    #[DataProvider('modeProvider')]
    public function testWhereInListRejectsTabsInSearchValue(string $class): void
    {
        // A tab is the list separator, so a tab-bearing value can never be one
        // discrete value. Previously "menu\tfooter" built needle "\tmenu\tfooter\t"
        // and matched a field storing separate menu and footer tokens.
        $sa = $class::new([1 => ['show_on' => "\tmenu\tfooter\t"]]);

        foreach (["menu\tfooter", "\tmenu", "menu\t", "\t"] as $badValue) {
            try {
                $sa->whereInList('show_on', $badValue);
                $this->fail('Expected InvalidArgumentException for value ' . json_encode($badValue));
            } catch (InvalidArgumentException $e) {
                $this->assertSame('whereInList(): expected a single value to match, got a tab-separated list', $e->getMessage());
            }
        }
    }

    #[DataProvider('modeProvider')]
    public function testWhereInListRejectsTabsInSmartStringSearchValue(string $class): void
    {
        // getRawValue() unwraps first, so a wrapped tab-list is caught too
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('whereInList(): expected a single value to match, got a tab-separated list');

        $class::new([1 => ['show_on' => "\tmenu\t"]])->whereInList('show_on', new SmartString("menu\tfooter"));
    }

    #[DataProvider('modeProvider')]
    public function testWhereInListRejectsArrayValues(string $class): void
    {
        // Previously cast to the literal string "Array" with a PHP warning naming
        // the library file, then matched nothing
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('whereInList(): expected a single value to match, got array');

        $class::new([['show_on' => 'menu']])->whereInList('show_on', ['menu']);
    }

    #[DataProvider('modeProvider')]
    public function testWhereInListOnFlatThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('whereInList(): Expected a nested array, but got a flat array');

        $class::new(['a', 'b'])->whereInList('f', 'x');
    }

    //endregion
    //region Warning contract (shared by the family)

    #[DataProvider('modeProvider')]
    public function testWhereFamilyWarnsWhenFirstRowMissingField(string $class): void
    {
        // Q3: typo protection checks the first row only (database rows are uniform)
        $firstRowMissing = $class::new([['other' => 1], ['f' => 1]]);

        [, $whereOutput] = $this->captureOutput(fn() => $firstRowMissing->where('f', 1));
        $this->assertStringContainsString("where(): 'f' doesn't exist", $whereOutput);

        $firstRowHasField = $class::new([['f' => 1], ['other' => 1]]);
        [, $silent] = $this->captureOutput(fn() => $firstRowHasField->where('f', 1));
        $this->assertSame('', $silent, 'no warning when the first row has the field');
    }

    //endregion
}
