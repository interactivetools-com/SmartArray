<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Array information: count()/Countable, isEmpty(), isNotEmpty(), contains().
 *
 * contains() uses the where-family matching rule (strings exact, numbers
 * numeric, null only null, bools as 1/0); the edge cases are pinned here so
 * a refactor can't change them silently.
 */
class InfoTest extends SmartArrayTestCase
{
    //region count()

    #[DataProvider('modeProvider')]
    public function testCountIsShallow(string $class): void
    {
        $this->assertSame(0, $class::new([])->count());
        $this->assertSame(3, $class::new(['a', 'b', 'c'])->count());
        $this->assertSame(1, $class::new([[]])->count(), 'an empty nested array is still one element');
        $this->assertSame(2, $class::new([['a', 'b', 'c'], ['d']])->count(), 'rows count as one regardless of size');
    }

    #[DataProvider('modeProvider')]
    public function testCountableInterfaceMatchesCountMethod(string $class): void
    {
        $sa = $class::new(['a', null, ['nested']]);

        $this->assertSame($sa->count(), count($sa));
    }

    //endregion
    //region isEmpty() / isNotEmpty()

    #[DataProvider('modeProvider')]
    public function testIsEmptyAndIsNotEmpty(string $class): void
    {
        $empty    = $class::new([]);
        $nonEmpty = $class::new([null]);   // a stored null still counts as an element

        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($empty->isNotEmpty());
        $this->assertFalse($nonEmpty->isEmpty());
        $this->assertTrue($nonEmpty->isNotEmpty());
    }

    //endregion
    //region contains()

    #[DataProvider('modeProvider')]
    public function testContainsFindsValuesNotKeys(string $class): void
    {
        $sa = $class::new(['a' => 'apple', 'b' => 'banana']);

        $this->assertTrue($sa->contains('apple'));
        $this->assertFalse($sa->contains('a'), 'keys are not searched');
        $this->assertFalse($class::new([])->contains('anything'));
    }

    #[DataProvider('modeProvider')]
    public function testContainsMatchingRules(string $class): void
    {
        // Numbers match numerically in either direction
        $this->assertTrue($class::new([1])->contains('1'), "'1' matches int 1");
        $this->assertTrue($class::new(['1.00'])->contains(1), "int 1 matches DECIMAL-style '1.00'");
        $this->assertFalse($class::new([0])->contains('abc'), "PHP 8: 0 == 'abc' is false");
        $this->assertFalse($class::new([''])->contains(0), "PHP 8: 0 == '' is false");

        // Strings match as exact text
        $this->assertFalse($class::new(['0e99'])->contains('0e12'), 'hash-like numeric strings are exact text');
        $this->assertFalse($class::new(['1000'])->contains('1e3'), 'no scientific-notation crossover for string pairs');
        $this->assertTrue($class::new(['0e99'])->contains('0e99'), 'identical strings still match');

        // null matches only null (SQL IS NULL semantics)
        $this->assertTrue($class::new([null])->contains(null));
        $this->assertFalse($class::new([''])->contains(null), "null does not match ''");
        $this->assertFalse($class::new([false])->contains(null), 'null does not match false');
        $this->assertFalse($class::new([null])->contains(''), "'' does not match a stored null");

        // Bools compare as 1/0
        $this->assertTrue($class::new([1])->contains(true));
        $this->assertTrue($class::new(['1'])->contains(true));
        $this->assertTrue($class::new(['0'])->contains(false));
        $this->assertFalse($class::new(['abc'])->contains(true), "true means 1, not 'any truthy value'");
        $this->assertFalse($class::new([''])->contains(false), "false means 0, and 0 == '' is false in PHP 8");
    }

    #[DataProvider('modeProvider')]
    public function testContainsUnwrapsSmartStringNeedles(string $class): void
    {
        $sa = $class::new(['banana']);

        $this->assertTrue($sa->contains(new SmartString('banana')));
    }

    #[DataProvider('modeProvider')]
    public function testContainsMatchesWholeRowsInNestedArrays(string $class): void
    {
        $sa = $class::new([['id' => 1, 'name' => 'Amy'], ['id' => 2, 'name' => 'Bob']]);

        $this->assertTrue($sa->contains(['id' => 1, 'name' => 'Amy']), 'array needles compare against whole rows');
        $this->assertFalse($sa->contains(['id' => 3, 'name' => 'Cid']));
        $this->assertFalse($sa->contains('Amy'), 'scalar needles do not search inside rows');
    }

    //endregion
}
