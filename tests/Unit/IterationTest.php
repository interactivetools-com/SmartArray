<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use ArrayIterator;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * foreach / getIterator: what types iteration yields per mode, key
 * preservation, and that iterating is silent and repeatable.
 */
class IterationTest extends SmartArrayTestCase
{
    public function testRawModeYieldsRawValues(): void
    {
        $types = [];
        foreach (SmartArray::new(['s' => '<b>', 'i' => 5, 'n' => null, 'row' => ['x']]) as $key => $value) {
            $types[$key] = get_debug_type($value);
        }

        $this->assertSame([
            's'   => 'string',
            'i'   => 'int',
            'n'   => 'null',
            'row' => SmartArray::class,
        ], $types);
    }

    public function testHtmlModeYieldsSmartStringsAndHtmlChildren(): void
    {
        $types = [];
        foreach (SmartArrayHtml::new(['s' => '<b>', 'i' => 5, 'n' => null, 'row' => ['x']]) as $key => $value) {
            $types[$key] = get_debug_type($value);
        }

        $this->assertSame([
            's'   => SmartString::class,
            'i'   => SmartString::class,
            'n'   => SmartString::class,   // stored nulls wrap too, so output contexts stay safe
            'row' => SmartArrayHtml::class,
        ], $types);
    }

    #[DataProvider('modeProvider')]
    public function testIterationPreservesKeysAndOrder(string $class): void
    {
        $sa = $class::new([7 => 'a', 'x' => 'b', 0 => 'c']);

        $keys = [];
        foreach ($sa as $key => $value) {
            $keys[] = [$key, $value instanceof SmartString ? $value->value() : $value];
        }

        $this->assertSame([[7, 'a'], ['x', 'b'], [0, 'c']], $keys);
    }

    #[DataProvider('modeProvider')]
    public function testIterationIsSilentAndRepeatable(string $class): void
    {
        // getIterator() returns a fresh iterator per foreach, and iterating
        // is not offset access - no deprecation notices, no warnings
        $sa = $class::new(['a' => 1, 'b' => 2]);

        $iterateAll = function () use ($sa): array {
            $seen = [];
            foreach ($sa as $key => $value) {
                $seen[$key] = $value instanceof SmartString ? $value->value() : $value;
            }
            return $seen;
        };

        [$captured, $deprecations] = $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => [$iterateAll(), $iterateAll()])
        );
        [[$firstPass, $secondPass], $output] = $captured;

        $this->assertSame(['a' => 1, 'b' => 2], $firstPass);
        $this->assertSame($firstPass, $secondPass, 'second pass sees the same elements');
        $this->assertSame('', $output);
        $this->assertSame([], $deprecations);
    }

    #[DataProvider('modeProvider')]
    public function testHeldIteratorIsCountableAndRewindable(string $class): void
    {
        // Every mode returns an ArrayIterator, so generic code that counts or
        // re-traverses a held iterator behaves the same before and after asHtml()
        $it = $class::new(['a', 'b', 'c'])->getIterator();

        $this->assertInstanceOf(ArrayIterator::class, $it);
        $this->assertSame(3, $it->count());

        $unwrap = fn($value) => $value instanceof SmartString ? $value->value() : $value;
        $first  = array_map($unwrap, iterator_to_array($it));
        $second = array_map($unwrap, iterator_to_array($it));   // second traversal of the same object

        $this->assertSame(['a', 'b', 'c'], $first);
        $this->assertSame($first, $second, 'held iterator traverses again instead of throwing');
    }

    #[DataProvider('modeProvider')]
    public function testEmptyArrayIteratesZeroTimes(string $class): void
    {
        $this->assertSame(0, iterator_count($class::new([])->getIterator()));
    }

    #[DataProvider('modeProvider')]
    public function testRecordSetYieldsTheStoredRowObjects(string $class): void
    {
        // Rows come back by identity, not as copies - metadata like position()
        // answers the same whether a row came from foreach or first()
        $sa = $class::new([['id' => 1], ['id' => 2]]);

        $yielded = [];
        foreach ($sa as $row) {
            $yielded[] = $row;
        }

        $this->assertSame($sa->first(), $yielded[0]);
        $this->assertSame($sa->last(), $yielded[1]);
    }

    public function testHtmlModeWrapsScalarAddedAfterConstruction(): void
    {
        // A record set iterates unwrapped (all rows), but adding a scalar later
        // must bring back SmartString wrapping for it
        $sa = SmartArrayHtml::new([['id' => 1]]);
        $sa->note = '<b>';

        $types = [];
        foreach ($sa as $key => $value) {
            $types[$key] = get_debug_type($value);
        }

        $this->assertSame([0 => SmartArrayHtml::class, 'note' => SmartString::class], $types);
    }
}
