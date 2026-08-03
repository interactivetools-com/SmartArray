<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Error;
use Itools\SmartArray\CallerException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * load($field): lazy-loading related data through a handler.
 *
 * The handler is a constructor property (how the database layer wires it):
 *
 *     new SmartArray($row, ['loadHandler' => fn($row, $field) => [$rows, $mysqliProperties]]);
 *
 * Contract: the handler receives the row object and the field name, and returns
 * a two-element array of [rows, mysqliProperties], or false for "I don't know
 * that field". load() wraps the returned rows in a new array of the same mode,
 * carrying the handler forward so nested loads work.
 */
class LoadTest extends SmartArrayTestCase
{
    //region Handler contract

    #[DataProvider('modeProvider')]
    public function testLoadReturnsHandlerRowsAsNewArrayOfSameMode(string $class): void
    {
        $handler = fn($row, $field) => [['id' => 10, 'name' => '<b>Widget</b>'], []];
        $sa      = $class::new(['id' => 1], ['loadHandler' => $handler]);

        $result = $sa->load('products');

        $this->assertInstanceOf($class, $result);
        $this->assertSame(['id' => 10, 'name' => '<b>Widget</b>'], $result->toArray());
        $this->assertModeValue(10, $result->id, $class);
        $this->assertModeValue('<b>Widget</b>', $result->name, $class);
        $this->assertValidStructure($result);
    }

    #[DataProvider('modeProvider')]
    public function testLoadPassesTheRowObjectAndFieldNameToTheHandler(string $class): void
    {
        $receivedRow   = null;
        $receivedField = null;
        $handler       = function ($row, $field) use (&$receivedRow, &$receivedField) {
            $receivedRow   = $row;
            $receivedField = $field;
            return [[], []];
        };
        $sa = $class::new(['user_id' => 42, 'name' => 'John'], ['loadHandler' => $handler]);

        $sa->load('invoices');

        $this->assertSame($sa, $receivedRow, 'the handler receives the row object itself, not a copy');
        $this->assertSame('invoices', $receivedField);
    }

    public function testLoadCallsTheHandlerOncePerCallWithNoCaching(): void
    {
        $calls   = 0;
        $handler = function ($row, $field) use (&$calls) {
            $calls++;
            return [['n' => $calls], []];
        };
        $sa = SmartArray::new(['id' => 1], ['loadHandler' => $handler]);

        $this->assertSame(['n' => 1], $sa->load('orders')->toArray());
        $this->assertSame(['n' => 2], $sa->load('orders')->toArray(), 'repeat loads call the handler again');
        $this->assertSame(2, $calls);
    }

    public function testLoadTakesMysqliMetadataFromTheHandlersSecondElement(): void
    {
        $handler = fn($row, $field) => [['data' => 'value'], ['query' => 'SELECT * FROM related', 'affected_rows' => 1]];
        $sa      = SmartArray::new(['id' => 1], ['loadHandler' => $handler]);

        $result = $sa->load('related');

        $this->assertSame(['query' => 'SELECT * FROM related', 'affected_rows' => 1], $result->mysqli());
        $this->assertSame('SELECT * FROM related', $result->mysqli('query'));
        $this->assertSame(1, $result->mysqli('affected_rows'));
        $this->assertNull($result->mysqli('insert_id'), 'keys the handler did not return read as null');
    }

    public function testLoadReplacesTheSourcesMysqliMetadataInsteadOfInheritingIt(): void
    {
        $handler = fn($row, $field) => [['data' => 'value'], []];
        $sa      = SmartArray::new(['id' => 1], ['loadHandler' => $handler, 'mysqli' => ['query' => 'SELECT * FROM users']]);

        $result = $sa->load('related');

        $this->assertSame([], $result->mysqli(), 'the loaded array describes the handler query, not the one that built the source row');
    }

    #[DataProvider('modeProvider')]
    public function testLoadedRecordSetGetsFreshPositionMetadataAndIsItsOwnRoot(string $class): void
    {
        $handler = fn($row, $field) => [[['id' => 10], ['id' => 20], ['id' => 30]], []];
        $sa      = $class::new(['id' => 1], ['loadHandler' => $handler]);

        $result = $sa->load('orders');

        $this->assertSame([['id' => 10], ['id' => 20], ['id' => 30]], $result->toArray());
        $this->assertSame($result, $result->root(), 'a loaded array is a new top-level array, not a child of the row it loaded from');
        $this->assertSame(1, $result->first()->position());
        $this->assertTrue($result->first()->isFirst());
        $this->assertSame(3, $result->last()->position());
        $this->assertTrue($result->last()->isLast());
        $this->assertValidStructure($result);
    }

    #[DataProvider('modeProvider')]
    public function testLoadedArrayKeepsTheHandlerForNestedLoads(string $class): void
    {
        $calls   = 0;
        $handler = function ($row, $field) use (&$calls) {
            $calls++;
            return [['level' => $calls], []];
        };
        $sa = $class::new(['id' => 1], ['loadHandler' => $handler]);

        $level1 = $sa->load('orders');
        $level2 = $level1->load('items');

        $this->assertSame(2, $calls);
        $this->assertInstanceOf($class, $level2);
        $this->assertSame(['level' => 2], $level2->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testRowsInsideALoadedRecordSetCanLoadToo(string $class): void
    {
        $handler = fn($row, $field) => match ($field) {
            'orders' => [[['id' => 10], ['id' => 20]], []],
            default  => [['line' => 'widget'], []],
        };
        $sa = $class::new(['id' => 1], ['loadHandler' => $handler]);

        $lines = $sa->load('orders')->first()->load('lines');

        $this->assertInstanceOf($class, $lines);
        $this->assertSame(['line' => 'widget'], $lines->toArray());
    }

    public function testLoadLeavesTheSourceRowUnchanged(): void
    {
        $handler = fn($row, $field) => [['id' => 10], ['query' => 'SELECT 1']];
        $sa      = SmartArray::new(['id' => 1], ['loadHandler' => $handler, 'mysqli' => ['query' => 'SELECT * FROM users']]);

        $sa->load('orders');

        $this->assertSame(['id' => 1], $sa->toArray());
        $this->assertSame(['query' => 'SELECT * FROM users'], $sa->mysqli());
    }

    //endregion
    //region Empty source arrays

    #[DataProvider('modeProvider')]
    public function testLoadOnEmptyArrayReturnsSmartNullWithoutCallingTheHandler(string $class): void
    {
        $handler = function ($row, $field) {
            $this->fail('the handler must not be called for an empty array');
        };
        $sa = $class::new([], ['loadHandler' => $handler]);

        [$result, $output] = $this->captureOutput(fn() => $sa->load('anything'));

        $this->assertSmartNull($result);
        $this->assertSame('', $output);
    }

    #[DataProvider('modeProvider')]
    public function testLoadOnEmptyArrayReturnsSmartNullEvenWithNoHandlerSet(string $class): void
    {
        // The empty check runs before every validation, so an empty result set
        // chains safely no matter where it came from
        $this->assertSmartNull($class::new([])->load('anything'));
    }

    public function testSmartNullFromEmptyLoadKeepsQueryMetadata(): void
    {
        $sa = SmartArray::new([], ['loadHandler' => fn($row, $field) => [[], []], 'mysqli' => ['query' => 'SELECT * FROM users']]);

        $result = $sa->load('orders');

        $this->assertSmartNull($result);
        $this->assertSame(['query' => 'SELECT * FROM users'], $result->mysqli());
    }

    //endregion
    //region Error paths: setup mistakes (CallerException)

    #[DataProvider('modeProvider')]
    public function testLoadWithNoHandlerThrows(string $class): void
    {
        $sa = $class::new(['id' => 1]);

        $this->expectException(CallerException::class);
        $this->expectExceptionMessage("load(): no load handler is set. Handlers are normally provided by the database layer (ZenDB); arrays created directly don't have one.");

        $sa->load('products');
    }

    public function testLoadErrorsReportTheCallersFileAndLine(): void
    {
        $sa = SmartArray::new(['id' => 1]);

        try {
            $expectedLine = __LINE__ + 1;
            $sa->load('products');
            $this->fail('expected CallerException');
        } catch (CallerException $e) {
            $this->assertSame(__FILE__, $e->getFile(), 'the reported file is the caller, not SmartArrayBase.php');
            $this->assertSame($expectedLine, $e->getLine());
            $this->assertStringEndsWith('src/SmartArrayBase.php', str_replace('\\', '/', $e->thrownInFile), 'the real throw site is kept for library debugging');
        }
    }

    public function testLoadWithNonCallableHandlerThrows(): void
    {
        $sa = SmartArray::new(['id' => 1], ['loadHandler' => 'not a function']);

        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('Load handler is not callable');

        $sa->load('products');
    }

    public function testLoadWithEmptyFieldNameThrows(): void
    {
        $sa = SmartArray::new(['id' => 1], ['loadHandler' => fn($row, $field) => [[], []]]);

        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('Field name is required for load() method.');

        $sa->load('');
    }

    public function testLoadWithFieldNameZeroLoadsNormally(): void
    {
        // Only a blank name is rejected ($field === ''), so a column literally
        // named "0" loads like any other field
        $receivedField = null;
        $sa = SmartArray::new(['id' => 1], ['loadHandler' => function ($row, $field) use (&$receivedField) {
            $receivedField = $field;
            return [[], []];
        }]);

        $sa->load('0');

        $this->assertSame('0', $receivedField);
    }

    public function testLoadOnRecordSetThrows(): void
    {
        $sa = SmartArray::new([
            ['id' => 1, 'name' => 'Row 1'],
            ['id' => 2, 'name' => 'Row 2'],
        ], ['loadHandler' => fn($row, $field) => [[], []]]);

        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('Cannot call load() on record set, only on a single row.');

        $sa->load('products');
    }

    public function testLoadOnRowWithAnyArrayValueThrowsTheRecordSetError(): void
    {
        // Intentional: the guard fires on any array value, so a single row holding
        // a sub-array can't load. No real case hits this (database rows are always
        // flat), and the guard also keeps array values out of the handler, which
        // can only look up scalars. Revisit if a real mixed-row case appears.
        $sa = SmartArray::new(['id' => 1, 'tags' => ['red', 'blue']], ['loadHandler' => fn($row, $field) => [[], []]]);

        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('Cannot call load() on record set, only on a single row.');

        $sa->load('products');
    }

    public function testMissingHandlerIsReportedBeforeFieldAndRecordSetProblems(): void
    {
        // Validation order: no handler is the root cause worth naming first
        $sa = SmartArray::new([['id' => 1], ['id' => 2]]);

        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('load(): no load handler is set.');

        $sa->load('bad.field');
    }

    public function testBadFieldNameIsReportedBeforeTheRecordSetProblem(): void
    {
        $sa = SmartArray::new([['id' => 1], ['id' => 2]], ['loadHandler' => fn($row, $field) => [[], []]]);

        $this->expectException(CallerException::class);
        $this->expectExceptionMessage('Field name contains invalid characters: bad.field');

        $sa->load('bad.field');
    }

    //endregion
    //region Error paths: broken handler contract (Error)

    public function testHandlerReturningFalseThrowsErrorNamingTheFieldAndCaller(): void
    {
        $sa = SmartArray::new(['id' => 1], ['loadHandler' => fn($row, $field) => false]);

        try {
            $expectedLine = __LINE__ + 1;
            $sa->load('products');
            $this->fail('expected Error');
        } catch (Error $e) {
            $expected = "Load handler doesn't support field 'products'\n"
                . "Occurred in " . __FILE__ . ":$expectedLine in " . self::class . "->" . __FUNCTION__ . "()\n"
                . "Reported";
            $this->assertSame($expected, $e->getMessage());
        }
    }

    public function testHandlerReturningNonArrayFirstElementThrows(): void
    {
        $sa = SmartArray::new(['id' => 1], ['loadHandler' => fn($row, $field) => ['invalid', []]]);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Load handler must return an array as the first argument');

        $sa->load('products');
    }

    public function testHandlerReturningNonArraySecondElementThrows(): void
    {
        $sa = SmartArray::new(['id' => 1], ['loadHandler' => fn($row, $field) => [[], 'invalid']]);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Load handler must return an array as the second argument');

        $sa->load('products');
    }

    public function testHandlerReturningAScalarThrowsTheShapeError(): void
    {
        $sa = SmartArray::new(['id' => 1], ['loadHandler' => fn($row, $field) => 'oops']);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Load handler must return [rows, mysqliProperties] or false, got string');

        $sa->load('products');
    }

    public function testHandlerReturningOnlyRowsThrowsTheShapeErrorWithoutPhpWarnings(): void
    {
        // The shape is checked before destructuring, so the library's own error
        // is the only thing the caller sees - no "Undefined array key 1" warning
        $sa = SmartArray::new(['id' => 1], ['loadHandler' => fn($row, $field) => [['id' => 10]]]);

        $thrown   = null;
        $warnings = $this->captureWarnings(function () use ($sa, &$thrown) {
            try {
                $sa->load('products');
            } catch (Error $e) {
                $thrown = $e;
            }
        });

        $this->assertSame([], $warnings);
        $this->assertInstanceOf(Error::class, $thrown);
        $this->assertSame('Load handler must return [rows, mysqliProperties] or false, got array', $thrown->getMessage());
    }

    //endregion
    //region Field name characters

    /**
     * @return array<string, array{string}>
     */
    public static function validFieldNameProvider(): array
    {
        return [
            'simple name'        => ['products'],
            'with underscore'    => ['user_products'],
            'leading underscore' => ['_internal'],
            'with numbers'       => ['items2'],
            'with hyphen'        => ['related-items'],
            'uppercase'          => ['PRODUCTS'],
            'mixed case'         => ['RelatedProducts'],
        ];
    }

    #[DataProvider('validFieldNameProvider')]
    public function testLoadAcceptsWordCharactersAndHyphens(string $field): void
    {
        $sa = SmartArray::new(['id' => 1], ['loadHandler' => fn($row, $f) => [['loaded' => $f], []]]);

        $this->assertSame(['loaded' => $field], $sa->load($field)->toArray());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidFieldNameProvider(): array
    {
        return [
            'with dot'       => ['table.column'],
            'with space'     => ['user products'],
            'with bang'      => ['products!'],
            'with semicolon' => ['products;drop'],
            'with quote'     => ["products'"],
            'with asterisk'  => ['*'],
            'with tab'       => ["tab\tname"],
            'with newline'   => ["two\nlines"],
            'non-ascii'      => ['ünïcödé'],
        ];
    }

    #[DataProvider('invalidFieldNameProvider')]
    public function testLoadRejectsEverythingElseAndEchoesTheFieldBack(string $field): void
    {
        $sa = SmartArray::new(['id' => 1], ['loadHandler' => fn($row, $f) => [[], []]]);

        $this->expectException(CallerException::class);
        $this->expectExceptionMessage("Field name contains invalid characters: $field");

        $sa->load($field);
    }

    //endregion
    //region Removed setter

    public function testSetLoadHandlerIsGone(): void
    {
        $this->assertFalse(method_exists(SmartArrayBase::class, 'setLoadHandler'), 'the handler is a constructor property now');
        $this->assertFalse(method_exists(SmartArray::class, 'setLoadHandler'));
        $this->assertFalse(method_exists(SmartArrayHtml::class, 'setLoadHandler'));
    }

    //endregion
    //region Helpers

    /**
     * Run $fn collecting E_WARNING messages, so a PHP warning raised inside the
     * library can be asserted instead of leaking into the suite output.
     *
     * @return string[]
     */
    private function captureWarnings(callable $fn): array
    {
        $messages = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$messages): bool {
            $messages[] = $errstr;
            return true;
        }, E_WARNING);
        try {
            $fn();
        } finally {
            restore_error_handler();
        }
        return $messages;
    }

    //endregion
}
