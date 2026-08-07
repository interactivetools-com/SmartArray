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
 * Pins the loose-comparison contract (documented ==) and the null/missing
 * matrix decided in the review (Q8):
 *
 *     row state        where()      whereNot()   whereInList()
 *     field missing    excluded     KEPT         excluded
 *     field is null    == applies   == applies   always excluded
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
    public function testWhereUsesLooseComparison(string $class): void
    {
        // Documented == semantics for database/form data where numbers are often strings
        $sa = $class::new([
            ['id' => 1],
            ['id' => '1'],
            ['id' => 2],
        ]);

        $this->assertCount(2, $sa->where('id', 1), "'1' == 1");
        $this->assertCount(2, $sa->where('id', '1'), "1 == '1'");
    }

    #[DataProvider('modeProvider')]
    public function testWhereNullValueMatchesLooseNullFamily(string $class): void
    {
        // A consequence of documented == semantics: null == '' == 0 == false in PHP 8
        $sa = $class::new([
            ['f' => null],
            ['f' => ''],
            ['f' => 0],
            ['f' => false],
            ['f' => '0'],     // '0' != null (PHP 8: null == '0' is false)
            ['f' => 'x'],
        ]);

        $this->assertSame([0, 1, 2, 3], $sa->where('f', null)->keys()->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testWhereExcludesRowsMissingFieldAndNonArrayRows(string $class): void
    {
        $sa = $class::new([
            'config' => 'scalar row',
            'a'      => ['f' => 5],
            'b'      => ['other' => 5],
        ]);

        [$result, ] = $this->captureOutput(fn() => $sa->where('f', 5));

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
    public function testWhereNotUsesLooseComparison(string $class): void
    {
        $sa = $class::new([['id' => 1], ['id' => '1'], ['id' => 2]]);

        $this->assertSame([2 => ['id' => 2]], $sa->whereNot('id', '1')->toArray());
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
    public function testWhereInListUnwrapsSmartStringValues(string $class): void
    {
        $sa = $class::new([1 => ['show_on' => "\tmenu\t"]]);

        $this->assertCount(1, $sa->whereInList('show_on', new SmartString('menu')));
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
