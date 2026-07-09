<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;
use Itools\SmartArray\Tests\Support\Fixtures;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * Construction: new SmartArray/SmartArrayHtml, ::new(), nested wrapping,
 * position metadata, property injection, and the legacy boolean argument.
 *
 * The boolean rule (both construction forms): a bool that contradicts the
 * class throws with a pointer to the right class; a redundant bool logs a
 * deprecation and proceeds.
 */
class CreationTest extends SmartArrayTestCase
{
    //region Basic construction

    #[DataProvider('modeProvider')]
    public function testConstructorWrapsNestedArraysRecursively(string $class): void
    {
        $sa = new $class(Fixtures::records());

        $this->assertSame($sa, $sa->root(), 'a freshly constructed array is its own root');
        $this->assertValidStructure($sa);
        $this->assertSame(Fixtures::records(), $sa->toArray(), 'toArray() round-trips the input exactly');
        $this->assertInstanceOf($class, $sa->first(), 'child rows take the parent class');
        $this->assertSame($class === SmartArrayHtml::class, $sa->usingSmartStrings());
    }

    #[DataProvider('modeProvider')]
    public function testNewMatchesConstructor(string $class): void
    {
        $fromNew         = $class::new(Fixtures::records());
        $fromConstructor = new $class(Fixtures::records());

        $this->assertSame($class, get_class($fromNew));
        $this->assertSame($fromConstructor->toArray(), $fromNew->toArray());
        $this->assertValidStructure($fromNew);
    }

    #[DataProvider('modeProvider')]
    public function testEmptyConstruction(string $class): void
    {
        $sa = $class::new([]);

        $this->assertSame(0, $sa->count());
        $this->assertTrue($sa->isEmpty());
        $this->assertSame([], $sa->toArray());
        $this->assertValidStructure($sa);
    }

    #[DataProvider('modeProvider')]
    public function testConstructorSetsPositionMetadataOnRows(string $class): void
    {
        $sa   = $class::new([['a' => 1], ['a' => 2], ['a' => 3]]);
        $rows = [$sa->nth(0), $sa->nth(1), $sa->nth(2)];

        $this->assertSame([1, 2, 3], array_map(fn($r) => $r->position(), $rows));
        $this->assertSame([true, false, false], array_map(fn($r) => $r->isFirst(), $rows));
        $this->assertSame([false, false, true], array_map(fn($r) => $r->isLast(), $rows));
    }

    #[DataProvider('modeProvider')]
    public function testConstructorAcceptsInternalProperties(string $class): void
    {
        $metadata = ['insert_id' => 42, 'affected_rows' => 3];
        $sa       = new $class([['id' => 1]], ['mysqli' => $metadata]);

        $this->assertSame($metadata, $sa->mysqli());
        $this->assertSame(42, $sa->mysqli('insert_id'));
        $this->assertSame($metadata, $sa->first()->mysqli(), 'metadata propagates to child rows');
        $this->assertValidStructure($sa);
    }

    #[DataProvider('modeProvider')]
    public function testConstructorUnwrapsSmartValuesInData(string $class): void
    {
        // Same rule as set(): Smart values in input data store as raw equivalents
        $sa = $class::new([
            'name' => new SmartString('Bob'),
            'row'  => SmartArrayHtml::new(['x' => 1]),
        ]);

        $this->assertSame(['name' => 'Bob', 'row' => ['x' => 1]], $sa->toArray());
        $this->assertInstanceOf($class, $sa->row, 'assigned rows take this array\'s mode');
    }

    #[DataProvider('modeProvider')]
    public function testConstructorRejectsUnsupportedValues(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("doesn't support stdClass values. Key bad");

        $class::new(['bad' => new stdClass()]);
    }

    //endregion
    //region Legacy boolean argument (Q5)

    /**
     * @return array<string, array{class-string<SmartArrayBase>, bool, string}>
     */
    public static function contradictoryBoolProvider(): array
    {
        return [
            'SmartArray with true'      => [SmartArray::class, true, 'Cannot create SmartArray with useSmartStrings=true. Use new SmartArrayHtml($data) instead.'],
            'SmartArrayHtml with false' => [SmartArrayHtml::class, false, 'Cannot create SmartArrayHtml with useSmartStrings=false. Use new SmartArray($data) instead.'],
        ];
    }

    #[DataProvider('contradictoryBoolProvider')]
    public function testContradictoryBoolThrowsFromNew(string $class, bool $bool, string $expectedMessage): void
    {
        // ::new() previously ignored the bool: code written for the old API got
        // raw values (unencoded output) with no signal. Now it matches the constructor.
        [$caught, $deprecations] = $this->captureDeprecations(function () use ($class, $bool) {
            try {
                $class::new(['name' => 'Bob'], $bool);
                return null;
            } catch (InvalidArgumentException $e) {
                return $e;
            }
        });

        $this->assertInstanceOf(InvalidArgumentException::class, $caught);
        $this->assertSame($expectedMessage, $caught->getMessage());
        $this->assertCount(1, $deprecations, 'deprecation logs before the throw so error logs show the migration hint');
    }

    #[DataProvider('contradictoryBoolProvider')]
    public function testContradictoryBoolThrowsFromConstructor(string $class, bool $bool, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        [$caught] = $this->captureDeprecations(fn() => new $class(['name' => 'Bob'], $bool));
        unset($caught);
    }

    /**
     * @return array<string, array{class-string<SmartArrayBase>, bool}>
     */
    public static function redundantBoolProvider(): array
    {
        return [
            'SmartArray with false'    => [SmartArray::class, false],
            'SmartArrayHtml with true' => [SmartArrayHtml::class, true],
        ];
    }

    #[DataProvider('redundantBoolProvider')]
    public function testRedundantBoolLogsDeprecationAndProceeds(string $class, bool $bool): void
    {
        [$results, $deprecations] = $this->captureDeprecations(fn() => [
            $class::new(['name' => 'Bob'], $bool),
            new $class(['name' => 'Bob'], $bool),
        ]);

        foreach ($results as $sa) {
            $this->assertInstanceOf($class, $sa);
            $this->assertSame(['name' => 'Bob'], $sa->toArray());
        }
        $this->assertCount(2, $deprecations);
        $this->assertStringContainsString('deprecated', $deprecations[0]);
    }

    #[DataProvider('modeProvider')]
    public function testRedundantUseSmartStringsArrayFormIsSilent(string $class): void
    {
        // The array form matching the class's own mode stays silent: internal
        // code passes explicit useSmartStrings (e.g. sprintf building its raw result)
        $matching = $class === SmartArrayHtml::class;

        [$sa, $deprecations] = $this->captureDeprecations(
            fn() => new $class(['name' => 'Bob'], ['useSmartStrings' => $matching])
        );

        $this->assertInstanceOf($class, $sa);
        $this->assertSame([], $deprecations);
    }

    /**
     * @return array<string, array{class-string<SmartArrayBase>, bool, string}>
     */
    public static function contradictoryArrayFormProvider(): array
    {
        return [
            'SmartArray useSmartStrings=true'      => [SmartArray::class, true, 'Cannot create SmartArray with useSmartStrings=true'],
            'SmartArrayHtml useSmartStrings=false' => [SmartArrayHtml::class, false, 'Cannot create SmartArrayHtml with useSmartStrings=false'],
        ];
    }

    #[DataProvider('contradictoryArrayFormProvider')]
    public function testContradictoryUseSmartStringsArrayFormThrows(string $class, bool $value, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        [$caught] = $this->captureDeprecations(fn() => new $class(['name' => 'Bob'], ['useSmartStrings' => $value]));
        unset($caught);
    }

    //endregion
}
