<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Closure;
use DateTime;
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
 * Write access: set(), __set, offsetSet, [] append, __unset, offsetUnset.
 *
 * Pins storage conversion (arrays and Smart values become mode-correct
 * children, scalars store raw), the deprecation contract for array syntax,
 * and the rule that late writes don't recalculate sibling position metadata.
 */
class WriteAccessTest extends SmartArrayTestCase
{
    //region set() / __set

    /**
     * @return array<string, array{class-string<SmartArrayBase>, string|int|float|bool|null}>
     */
    public static function modeAndScalarProvider(): array
    {
        $cases = [];
        foreach (self::modeProvider() as $mode => [$class]) {
            foreach (Fixtures::edgeScalars() as $label => $value) {
                $cases["$mode: $label"] = [$class, $value];
            }
        }
        return $cases;
    }

    #[DataProvider('modeAndScalarProvider')]
    public function testSetStoresScalarsUnchanged(string $class, string|int|float|bool|null $value): void
    {
        $sa = $class::new([]);

        $result = $sa->set('key', $value);

        $this->assertSame($sa, $result, 'set() returns $this for chaining');
        $this->assertSame(['key' => $value], $sa->toArray(), 'values store raw; encoding happens on access, not storage');
    }

    #[DataProvider('modeProvider')]
    public function testPropertySetStoresValueSilently(string $class): void
    {
        $sa = $class::new([]);

        [, $output] = $this->captureOutput(function () use ($sa) {
            $sa->name   = 'Bob';
            $sa->count  = 0;
            $sa->middle = null;
        });

        $this->assertSame('', $output, 'property writes are the preferred syntax; no notice');
        $this->assertSame(['name' => 'Bob', 'count' => 0, 'middle' => null], $sa->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testSetConvertsArraysToSameModeChildren(string $class): void
    {
        $sa = $class::new([]);

        $sa->set('child', ['a' => 1, 'nested' => ['b' => 2]]);

        $this->assertInstanceOf($class, $sa->child);
        $this->assertInstanceOf($class, $sa->child->nested, 'conversion recurses');
        $this->assertSame(['a' => 1, 'nested' => ['b' => 2]], $sa->child->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testSetOverwritesExistingKeysIncludingTypeChanges(string $class): void
    {
        $sa = $class::new(['key' => ['a', 'b'], 'other' => 'x']);

        $sa->set('key', 'now a string');
        $sa->other = ['now', 'an', 'array'];

        $this->assertSame(['key' => 'now a string', 'other' => ['now', 'an', 'array']], $sa->toArray());
    }

    //endregion
    //region Smart value unwrapping (Q6)

    #[DataProvider('modeProvider')]
    public function testSetUnwrapsSmartStringToRawValue(string $class): void
    {
        $sa = $class::new([]);

        $sa->name = new SmartString("O'Brien");
        $sa->set('age', new SmartString(30));

        $this->assertSame(['name' => "O'Brien", 'age' => 30], $sa->toArray(), 'SmartStrings store their raw value');
    }

    #[DataProvider('modeProvider')]
    public function testSetConvertsSmartArraysToOwnMode(string $class): void
    {
        // Assigning a row from either mode stores a child of THIS array's mode
        $sa = $class::new([]);

        $sa->rawRow  = SmartArray::new(['a' => 1]);
        $sa->htmlRow = SmartArrayHtml::new(['b' => 2]);

        $this->assertInstanceOf($class, $sa->rawRow);
        $this->assertInstanceOf($class, $sa->htmlRow);
        $this->assertSame(['rawRow' => ['a' => 1], 'htmlRow' => ['b' => 2]], $sa->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testSetStoresSmartNullAsNull(string $class): void
    {
        $sa = $class::new([]);

        $sa->missing = $class::new([])->first(); // SmartNull

        $this->assertSame(['missing' => null], $sa->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testValuesCopyBetweenArraysInAnyMode(string $class): void
    {
        // The motivating case: read from an HTML-mode row, write to another array
        $source = SmartArrayHtml::new(['name' => '<b>Bob</b>', 'row' => ['x' => 1]]);
        $dest   = $class::new([]);

        $dest->name = $source->name;   // SmartString
        $dest->row  = $source->row;    // SmartArrayHtml

        $this->assertSame(['name' => '<b>Bob</b>', 'row' => ['x' => 1]], $dest->toArray(), 'raw values transfer; no encoding baked in');
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function unsupportedValueProvider(): array
    {
        return [
            'stdClass' => [new stdClass(), 'stdClass'],
            'DateTime' => [new DateTime(), 'DateTime'],
            'Closure'  => [fn() => 'x', Closure::class],
        ];
    }

    #[DataProvider('unsupportedValueProvider')]
    public function testSetUnsupportedTypesThrow(mixed $value, string $typeName): void
    {
        $sa = SmartArray::new([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("SmartArray doesn't support $typeName values. Key key");

        $sa->set('key', $value);
    }

    public function testSetResourceThrows(): void
    {
        $resource = fopen('php://memory', 'rb');
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("doesn't support resource");
            SmartArray::new([])->set('key', $resource);
        } finally {
            fclose($resource);
        }
    }

    //endregion
    //region offsetSet / append (deprecated array syntax)

    #[DataProvider('modeProvider')]
    public function testOffsetSetStoresValueAndNotifiesDeprecation(string $class): void
    {
        $this->assertSame('notify', SmartArrayBase::$onOffsetAccess, 'precondition: default mode');
        $sa = $class::new([]);

        [[, $output], $deprecations] = $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $sa['name'] = 'Bob')
        );

        $this->assertSame(['name' => 'Bob'], $sa->toArray());
        $this->assertStringContainsString('Deprecated:', $output);
        $this->assertStringContainsString("Replace ['name'] with ->name = \$value", $output);
        $this->assertCount(1, $deprecations);
    }

    #[DataProvider('modeProvider')]
    public function testAppendStoresSequentiallyAndSuggestsExplicitKey(string $class): void
    {
        $sa = $class::new(['existing']);

        [[, $output], ] = $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $sa[] = 'appended')
        );

        $this->assertSame(['existing', 'appended'], $sa->toArray());
        $this->assertStringContainsString('->set($key, $value) using an explicit key', $output);
    }

    //endregion
    //region __unset / offsetUnset

    #[DataProvider('modeProvider')]
    public function testUnsetPropertyRemovesKeySilently(string $class): void
    {
        $sa = $class::new(['name' => 'Bob', 'age' => 30]);

        [, $output] = $this->captureOutput(function () use ($sa) {
            unset($sa->name);
        });

        $this->assertSame('', $output);
        $this->assertSame(['age' => 30], $sa->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testUnsetArraySyntaxRemovesKeyAndNotifiesDeprecation(string $class): void
    {
        $sa = $class::new(['name' => 'Bob', 'age' => 30]);

        [[, $output], $deprecations] = $this->captureDeprecations(
            fn() => $this->captureOutput(function () use ($sa) {
                unset($sa['name']);
            })
        );

        $this->assertSame(['age' => 30], $sa->toArray());
        $this->assertStringContainsString("Replace ['name'] with ->name", $output);
        $this->assertCount(1, $deprecations);
    }

    //endregion
    //region Position metadata on late writes

    #[DataProvider('modeProvider')]
    public function testLateWritesDoNotRecalculatePositions(string $class): void
    {
        // Position metadata is set at construction only (documented in offsetSet):
        // a row added later reads position 0 / isFirst false / isLast false,
        // and existing siblings keep their original metadata
        $sa = $class::new([['a' => 1], ['a' => 2]]);

        $sa->set('late', ['a' => 3]);

        $late = $sa->get('late');
        $this->assertSame(0, $late->position());
        $this->assertFalse($late->isFirst());
        $this->assertFalse($late->isLast());

        $this->assertTrue($sa->nth(1)->isLast(), 'original last row keeps isLast even though it no longer is');
    }

    //endregion
}
