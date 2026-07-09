<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Array information: count()/Countable, isEmpty(), isNotEmpty(), contains().
 *
 * contains() is documented loose == comparison; the footgun cases from the
 * review are pinned here so a refactor can't change them silently.
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
    public function testContainsUsesLooseComparison(string $class): void
    {
        // Documented == footguns, pinned deliberately (see contains() docblock)
        $this->assertTrue($class::new([1])->contains('1'), "'1' == 1");
        $this->assertTrue($class::new([true])->contains('1'), "'1' == true");
        $this->assertTrue($class::new([''])->contains(null), "null == ''");
        $this->assertTrue($class::new([false])->contains(null), 'null == false');
        $this->assertFalse($class::new([0])->contains('abc'), "PHP 8: 0 == 'abc' is false");
        $this->assertFalse($class::new([''])->contains(0), "PHP 8: 0 == '' is false");
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
