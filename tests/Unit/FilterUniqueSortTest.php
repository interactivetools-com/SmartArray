<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * filter(), unique(), sort(), sortBy().
 *
 * Pins the raw-values callback contract, key handling per method (filter and
 * unique preserve, sort reindexes, sortBy is per-key-type), the loose
 * string-comparison rules of unique(), and sortBy()'s missing-field handling.
 */
class FilterUniqueSortTest extends SmartArrayTestCase
{
    //region filter()

    #[DataProvider('modeProvider')]
    public function testFilterCallbackReceivesRawValuesAndKeys(string $class): void
    {
        // The mode contract: callbacks get raw PHP values in BOTH modes, never SmartStrings
        $seen = [];
        $sa   = $class::new(['a' => '<b>', 'row' => ['x' => 1]]);

        $sa->filter(function ($value, $key) use (&$seen) {
            $seen[$key] = $value;
            return true;
        });

        $this->assertSame(['a' => '<b>', 'row' => ['x' => 1]], $seen, 'raw scalars and plain arrays, no Smart wrappers');
    }

    #[DataProvider('modeProvider')]
    public function testFilterKeepsMatchesAndPreservesKeys(string $class): void
    {
        $sa = $class::new(['a' => 1, 'b' => 5, 'c' => 10]);

        $result = $sa->filter(fn($v) => $v >= 5);

        $this->assertInstanceOf($class, $result);
        $this->assertSame(['b' => 5, 'c' => 10], $result->toArray());
        $this->assertSame(['a' => 1, 'b' => 5, 'c' => 10], $sa->toArray(), 'source unchanged');
        $this->assertMetadataPreserved($sa, $result);
    }

    #[DataProvider('modeProvider')]
    public function testFilterWithoutCallbackRemovesFalsyValues(string $class): void
    {
        $sa = $class::new([1, 0, true, false, '', null, 'hello', '0']);

        $result = $sa->filter();

        $this->assertSame([0 => 1, 2 => true, 6 => 'hello'], $result->toArray(), "0, false, '', null, and '0' are falsy; keys preserved");
    }

    #[DataProvider('modeProvider')]
    public function testFilterOnEmptyReturnsEmpty(string $class): void
    {
        $this->assertSame([], $class::new([])->filter(fn($v) => $v !== null)->toArray());
    }

    //endregion
    //region unique()

    #[DataProvider('modeProvider')]
    public function testUniqueKeepsFirstOccurrenceAndKeys(string $class): void
    {
        $sa = $class::new(['a', 'b', 'a', 'c', 'b']);

        $result = $sa->unique();

        $this->assertSame([0 => 'a', 1 => 'b', 3 => 'c'], $result->toArray());
        $this->assertSame(['a', 'b', 'a', 'c', 'b'], $sa->toArray(), 'source unchanged');
    }

    public function testUniqueComparesAsStrings(): void
    {
        // array_unique() default (SORT_STRING), documented in the docblock:
        // values that stringify identically count as duplicates
        $sa = SmartArray::new([1, '1', true, 1.0, '', false, null, '0', 0]);

        $result = $sa->unique();

        // "1": 1, '1', true, 1.0 collapse; "": '', false, null collapse; "0": '0', 0 collapse
        $this->assertSame([0 => 1, 4 => '', 7 => '0'], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testUniqueOnNestedThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unique(): Expected a flat array, but got a nested array');

        $class::new([['a' => 1]])->unique();
    }

    //endregion
    //region sort()

    #[DataProvider('modeProvider')]
    public function testSortOrdersValuesAndReindexesKeys(string $class): void
    {
        $sa = $class::new(['x' => 'banana', 'y' => 'apple', 'z' => 'cherry']);

        $result = $sa->sort();

        $this->assertSame([0 => 'apple', 1 => 'banana', 2 => 'cherry'], $result->toArray(), 'sort() always reindexes');
        $this->assertSame(['x' => 'banana', 'y' => 'apple', 'z' => 'cherry'], $sa->toArray(), 'source unchanged');
        $this->assertMetadataPreserved($sa, $result);
    }

    #[DataProvider('modeProvider')]
    public function testSortFlags(string $class): void
    {
        $numericStrings = $class::new(['10', '9', '100']);

        $this->assertSame(['9', '10', '100'], $numericStrings->sort(SORT_NUMERIC)->toArray());
        $this->assertSame(['10', '100', '9'], $numericStrings->sort(SORT_STRING)->toArray());

        $mixedCase = $class::new(['banana', 'Apple', 'cherry']);
        $this->assertSame(['Apple', 'banana', 'cherry'], $mixedCase->sort(SORT_STRING | SORT_FLAG_CASE)->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testSortOnNestedThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sort(): Expected a flat array, but got a nested array');

        $class::new([['a' => 1]])->sort();
    }

    //endregion
    //region sortBy()

    #[DataProvider('modeProvider')]
    public function testSortByOrdersRowsByField(string $class): void
    {
        $sa = $class::new([
            ['name' => 'Cid', 'age' => 25],
            ['name' => 'Amy', 'age' => 30],
            ['name' => 'Bob', 'age' => 20],
        ]);

        $byName = $sa->sortBy('name');
        $byAge  = $sa->sortBy('age', SORT_NUMERIC);

        $this->assertSame(['Amy', 'Bob', 'Cid'], array_column($byName->toArray(), 'name'));
        $this->assertSame([20, 25, 30], array_column($byAge->toArray(), 'age'));
        $this->assertModeValue('Cid', $sa->first()->get('name'), $class, 'source order unchanged');
        $this->assertValidStructure($byName);
        $this->assertMetadataPreserved($sa, $byName);
    }

    #[DataProvider('modeProvider')]
    public function testSortByMissingFieldSortsFirstAndKeepsRowsUnchanged(string $class): void
    {
        // Q1: previously threw ValueError "Array sizes are inconsistent".
        // Missing fields count as null for ordering only (like MySQL ORDER BY).
        $sa = $class::new([
            ['name' => 'Bob', 'age' => 30],
            ['name' => 'Amy'],                // no age
            ['name' => 'Cid', 'age' => 25],
        ]);

        [$result, ] = $this->captureOutput(fn() => $sa->sortBy('age'));

        $this->assertSame([
            ['name' => 'Amy'],                // null sorts first; row still has no age key
            ['name' => 'Cid', 'age' => 25],
            ['name' => 'Bob', 'age' => 30],
        ], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testSortByWarningChecksFirstRowOnly(string $class): void
    {
        // Q3: the missing-field warning is a typo check against the first row
        // (database rows are uniform). Later rows missing the field stay silent.
        $firstRowHasField = $class::new([['age' => 30], ['name' => 'Amy']]);
        [, $silent] = $this->captureOutput(fn() => $firstRowHasField->sortBy('age'));
        $this->assertSame('', $silent);

        $firstRowMissingField = $class::new([['name' => 'Amy'], ['age' => 30]]);
        [, $warned] = $this->captureOutput(fn() => $firstRowMissingField->sortBy('age'));
        $this->assertStringContainsString("sortBy(): 'age' doesn't exist", $warned);
    }

    #[DataProvider('modeProvider')]
    public function testSortByReindexesNumericKeysAndPreservesStringKeys(string $class): void
    {
        // Q2: array_multisort() default key handling, documented in the docblock
        $numericKeys = $class::new([['n' => 'b'], ['n' => 'a']]);
        $this->assertSame([0, 1], $numericKeys->sortBy('n')->keys()->toArray());

        $stringKeys = $class::new(['bob' => ['n' => 'b'], 'amy' => ['n' => 'a']]);
        $this->assertSame(['amy', 'bob'], $stringKeys->sortBy('n')->keys()->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testSortByOnFlatThrows(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sortBy(): Expected a nested array, but got a flat array');

        $class::new(['a', 'b'])->sortBy('name');
    }

    //endregion
}
