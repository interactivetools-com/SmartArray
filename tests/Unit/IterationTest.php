<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

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
        // getIterator() returns a fresh generator per foreach, and iterating
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
    public function testEmptyArrayIteratesZeroTimes(string $class): void
    {
        $this->assertSame(0, iterator_count($class::new([])->getIterator()));
    }
}
