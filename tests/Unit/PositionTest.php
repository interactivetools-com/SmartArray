<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * isFirst(), isLast(), position() - the template-loop metadata.
 *
 * Positions are 1-based, assigned at construction, and count every slot
 * (scalar elements advance the counter even though only child rows carry
 * metadata). Derived arrays get fresh positions because every transformation
 * builds a new SmartArray.
 */
class PositionTest extends SmartArrayTestCase
{
    #[DataProvider('modeProvider')]
    public function testRowsKnowTheirPosition(string $class): void
    {
        $rows = $class::new([['n' => 'a'], ['n' => 'b'], ['n' => 'c']]);

        $positions = [];
        foreach ($rows as $row) {
            $positions[] = [$row->position(), $row->isFirst(), $row->isLast()];
        }

        $this->assertSame([
            [1, true, false],
            [2, false, false],
            [3, false, true],
        ], $positions);
    }

    #[DataProvider('modeProvider')]
    public function testSingleRowIsBothFirstAndLast(string $class): void
    {
        $row = $class::new([['n' => 'only']])->first();

        $this->assertTrue($row->isFirst());
        $this->assertTrue($row->isLast());
        $this->assertSame(1, $row->position());
    }

    #[DataProvider('modeProvider')]
    public function testScalarSlotsAdvancePositions(string $class): void
    {
        // Mixed data: scalars can't carry metadata but still count as slots
        $sa = $class::new([['n' => 'a'], 'scalar', ['n' => 'b']]);

        $this->assertSame(1, $sa->nth(0)->position());
        $this->assertSame(3, $sa->nth(2)->position(), 'the scalar at slot 2 advanced the counter');
        $this->assertTrue($sa->nth(2)->isLast());
    }

    #[DataProvider('modeProvider')]
    public function testPositionsIgnoreKeyNamesAndOrder(string $class): void
    {
        $sa = $class::new([20 => ['n' => 'a'], 10 => ['n' => 'b'], 30 => ['n' => 'c']]);

        $this->assertTrue($sa->get(20)->isFirst(), 'first by position, not by lowest key');
        $this->assertTrue($sa->get(30)->isLast());
        $this->assertSame(2, $sa->get(10)->position());
    }

    #[DataProvider('modeProvider')]
    public function testStandaloneArrayHasPositionZero(string $class): void
    {
        // A root array has no parent, so position metadata stays at defaults
        $sa = $class::new([['n' => 'a']]);

        $this->assertSame(0, $sa->position());
        $this->assertFalse($sa->isFirst());
        $this->assertFalse($sa->isLast());
    }

    #[DataProvider('modeProvider')]
    public function testDerivedArraysGetFreshPositions(string $class): void
    {
        // Filtering rebuilds the array, so the surviving rows get new positions
        $rows = $class::new([
            ['type' => 'a', 'n' => 1],
            ['type' => 'b', 'n' => 2],
            ['type' => 'b', 'n' => 3],
        ]);

        $filtered = $rows->where('type', 'b');
        $first    = $filtered->first();
        $last     = $filtered->last();

        $this->assertTrue($first->isFirst(), 'row 2 of the source is row 1 of the result');
        $this->assertSame(1, $first->position());
        $this->assertTrue($last->isLast());
        $this->assertSame(2, $last->position());
        $this->assertValidStructure($filtered);
    }

    #[DataProvider('modeProvider')]
    public function testSortedArraysGetFreshPositions(string $class): void
    {
        $rows = $class::new([['n' => 'c'], ['n' => 'a'], ['n' => 'b']]);

        $sorted = $rows->sortBy('n');

        $this->assertTrue($sorted->first()->isFirst());
        $this->assertModeValue('a', $sorted->first()->get('n'), $class);
        $this->assertSame([1, 2, 3], array_map(fn($i) => $sorted->nth($i)->position(), [0, 1, 2]));
        $this->assertValidStructure($sorted);
    }
}
