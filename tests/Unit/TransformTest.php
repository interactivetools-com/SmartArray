<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * map(), each(), merge().
 *
 * The callback contracts differ on purpose and are pinned here: map() hands
 * the callback raw PHP values in both modes; each() hands it the same Smart
 * values you'd get from property access.
 */
class TransformTest extends SmartArrayTestCase
{
    //region map()

    #[DataProvider('modeProvider')]
    public function testMapCallbackReceivesRawValues(string $class): void
    {
        // Both modes: raw values, so transformations don't need ->value() calls
        $seen = [];
        $class::new(['a' => '<b>', 'row' => ['x' => 1]])->map(function ($value) use (&$seen) {
            $seen[] = $value;
            return $value;
        });

        $this->assertSame(['<b>', ['x' => 1]], $seen);
    }

    #[DataProvider('modeProvider')]
    public function testMapTransformsValuesAndPreservesKeys(string $class): void
    {
        $sa = $class::new(['a' => 1, 'b' => 2]);

        $result = $sa->map(fn(int $n) => $n * 10);

        $this->assertInstanceOf($class, $result);
        $this->assertSame(['a' => 10, 'b' => 20], $result->toArray());
        $this->assertSame(['a' => 1, 'b' => 2], $sa->toArray(), 'source unchanged');
        $this->assertMetadataPreserved($sa, $result);
    }

    #[DataProvider('modeProvider')]
    public function testMapClosuresGetKeysNonClosuresDoNot(string $class): void
    {
        // Closures get ($value, $key); other callables get only $value so
        // e.g. map('intval') can't misread the key as the base argument
        $sa = $class::new(['a' => '1', 'b' => '10']);

        $withKeys = $sa->map(fn($value, $key) => "$key=$value");
        $this->assertSame(['a' => 'a=1', 'b' => 'b=10'], $withKeys->toArray());

        $intvals = $sa->map('intval');
        $this->assertSame(['a' => 1, 'b' => 10], $intvals->toArray(), "intval('1', 'a') would fail; only the value is passed");
    }

    #[DataProvider('modeProvider')]
    public function testMapReturnedArraysBecomeSameModeChildren(string $class): void
    {
        $sa = $class::new([['n' => 1], ['n' => 2]]);

        $result = $sa->map(fn(array $row) => ['n' => $row['n'], 'double' => $row['n'] * 2]);

        $this->assertInstanceOf($class, $result->first());
        $this->assertSame([['n' => 1, 'double' => 2], ['n' => 2, 'double' => 4]], $result->toArray());
        $this->assertValidStructure($result);
    }

    //endregion
    //region each()

    public function testEachCallbackReceivesSmartValuesPerMode(): void
    {
        // each() is for output-side effects, so it hands over the same types
        // property access would return: SmartStrings in HTML mode, raw otherwise
        $htmlTypes = [];
        SmartArrayHtml::new(['s' => 'x', 'row' => ['y']])->each(function ($value, $key) use (&$htmlTypes) {
            $htmlTypes[$key] = get_debug_type($value);
        });
        $this->assertSame(['s' => SmartString::class, 'row' => SmartArrayHtml::class], $htmlTypes);

        $rawTypes = [];
        SmartArray::new(['s' => 'x', 'row' => ['y']])->each(function ($value, $key) use (&$rawTypes) {
            $rawTypes[$key] = get_debug_type($value);
        });
        $this->assertSame(['s' => 'string', 'row' => SmartArray::class], $rawTypes);
    }

    #[DataProvider('modeProvider')]
    public function testEachReturnsSameInstanceForChaining(string $class): void
    {
        $sa = $class::new(['a', 'b']);

        $visited = 0;
        $result  = $sa->each(function () use (&$visited) {
            $visited++;
        });

        $this->assertSame($sa, $result);
        $this->assertSame(2, $visited);
    }

    //endregion
    //region merge()

    #[DataProvider('modeProvider')]
    public function testMergeCombinesWithArrayMergeSemantics(string $class): void
    {
        $sa = $class::new(['a' => 1, 'b' => 2, 5 => 'x']);

        $result = $sa->merge(['b' => 20, 'c' => 30], [9 => 'y']);

        $this->assertInstanceOf($class, $result);
        $this->assertSame(
            ['a' => 1, 'b' => 20, 0 => 'x', 'c' => 30, 1 => 'y'],
            $result->toArray(),
            'string keys overwrite in place, numeric keys renumber in encounter order'
        );
        $this->assertSame(['a' => 1, 'b' => 2, 5 => 'x'], $sa->toArray(), 'source unchanged');
        $this->assertMetadataPreserved($sa, $result);
    }

    #[DataProvider('modeProvider')]
    public function testMergeAcceptsSmartArraysOfEitherMode(string $class): void
    {
        $sa = $class::new(['a' => 1]);

        $result = $sa->merge(SmartArray::new(['b' => 2]), SmartArrayHtml::new(['c' => 3]));

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $result->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testMergeIsShallow(string $class): void
    {
        $sa = $class::new(['user' => ['name' => 'Amy', 'age' => 30]]);

        $result = $sa->merge(['user' => ['name' => 'Bob']]);

        $this->assertSame(['user' => ['name' => 'Bob']], $result->toArray(), 'colliding nested arrays replace, not deep-merge');
    }

    #[DataProvider('modeProvider')]
    public function testMergeWithNoArgumentsAndEmptyArrays(string $class): void
    {
        $sa = $class::new(['a' => 1]);

        $this->assertSame(['a' => 1], $sa->merge()->toArray());
        $this->assertSame(['a' => 1], $sa->merge([])->toArray());
        $this->assertSame(['a' => 1], $class::new([])->merge(['a' => 1])->toArray());
    }

    //endregion
}
