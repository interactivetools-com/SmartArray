<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Closure;
use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * SmartArrayBase::$onOffsetAccess - how deprecated $array['key'] syntax is surfaced.
 *
 * Covers the full matrix: modes (notify/log/throw) x operations (offsetGet,
 * offsetSet, append, offsetUnset, offsetExists via isset() and empty()), the
 * exact suggestion text for each key shape, and the invalid-mode error. Also
 * pins what must stay signal-free: property syntax and the library's own
 * internal element access.
 *
 * Every test swaps the static through withOffsetAccess(), which restores it in a
 * finally block so a failure here cannot poison other test files.
 */
class GlobalSettingsTest extends SmartArrayTestCase
{
    //region The operation matrix

    /**
     * The sample array every matrix row operates on. Keys cover the shapes the
     * suggestion text branches on: a valid property name, an integer key, a key
     * that is not a valid property name, and the empty-string key.
     */
    private static function sample(string $class): SmartArrayBase
    {
        return $class::new(['name' => 'Bob', 'users.id' => 5, '' => 'blank', 0 => 'zero']);
    }

    /**
     * One row per offset operation and key shape: the operation to run and the
     * exact deprecation messages it produces, in order, without the trailing
     * " in file:line." that getExternalCaller() appends.
     *
     * @return array<string, array{Closure, string[]}>
     */
    private static function offsetOperations(): array
    {
        return [
            // offsetGet
            'get string key'          => [fn(SmartArrayBase $sa) => $sa['name'],       ["Replace ['name'] with ->name"]],
            'get int key'             => [fn(SmartArrayBase $sa) => $sa[0],            ["Replace [0] with ->{0}"]],
            'get invalid prop name'   => [fn(SmartArrayBase $sa) => $sa['users.id'],   ["Replace ['users.id'] with ->{'users.id'}"]],
            'get empty string key'    => [fn(SmartArrayBase $sa) => $sa[''],           ["Replace [''] with ->get('')"]],

            // offsetSet
            'set string key'          => [function (SmartArrayBase $sa) { $sa['city'] = 'Vancouver'; },  ['Replace [\'city\'] with ->city = $value']],
            'set int key'             => [function (SmartArrayBase $sa) { $sa[9] = 'nine'; },            ['Replace [9] with ->set(9, $value)']],
            'set invalid prop name'   => [function (SmartArrayBase $sa) { $sa['a-b'] = 'dashed'; },      ['Replace [\'a-b\'] with ->set(\'a-b\', $value) or ->{\'a-b\'} = $value']],
            'append'                  => [function (SmartArrayBase $sa) { $sa[] = 'appended'; },         ['Replace [] with ->set($key, $value) using an explicit key']],

            // offsetUnset
            'unset string key'        => [function (SmartArrayBase $sa) { unset($sa['name']); },     ["Replace ['name'] with ->name"]],
            'unset int key'           => [function (SmartArrayBase $sa) { unset($sa[0]); },          ["Replace [0] with ->{0}"]],
            'unset invalid prop name' => [function (SmartArrayBase $sa) { unset($sa['users.id']); }, ["Replace ['users.id'] with ->{'users.id'}"]],

            // offsetExists via isset(): one call, whether or not the key is there
            'isset string key'        => [fn(SmartArrayBase $sa) => isset($sa['name']),     ["Replace ['name'] with ->name"]],
            'isset int key'           => [fn(SmartArrayBase $sa) => isset($sa[0]),          ["Replace [0] with ->{0}"]],
            'isset invalid prop name' => [fn(SmartArrayBase $sa) => isset($sa['users.id']), ["Replace ['users.id'] with ->{'users.id'}"]],
            'isset missing key'       => [fn(SmartArrayBase $sa) => isset($sa['zzz']),      ["Replace ['zzz'] with ->zzz"]],

            // offsetExists via empty(): PHP calls offsetExists, then offsetGet when the key exists,
            // so an existing key signals twice for one empty() check - both notices give the
            // same suggestion (reads and existence checks share one suggestion style)
            'empty existing key'      => [fn(SmartArrayBase $sa) => empty($sa['name']), ["Replace ['name'] with ->name", "Replace ['name'] with ->name"]],
            'empty int key'           => [fn(SmartArrayBase $sa) => empty($sa[0]),      ["Replace [0] with ->{0}", "Replace [0] with ->{0}"]],
            'empty missing key'       => [fn(SmartArrayBase $sa) => empty($sa['zzz']),  ["Replace ['zzz'] with ->zzz"]],
        ];
    }

    /**
     * @return array<string, array{class-string<SmartArrayBase>, Closure, string[]}>
     */
    public static function offsetOperationProvider(): array
    {
        $cases = [];
        foreach (self::modeProvider() as $mode => [$class]) {
            foreach (self::offsetOperations() as $label => [$operation, $messages]) {
                $cases["$mode: $label"] = [$class, $operation, $messages];
            }
        }
        return $cases;
    }

    //endregion
    //region Default mode

    public function testOnOffsetAccessDefaultsToNotify(): void
    {
        $declaredDefault = (new ReflectionClass(SmartArrayBase::class))->getDefaultProperties()['onOffsetAccess'];

        $this->assertSame('notify', $declaredDefault, 'the declared default in the class');
        $this->assertSame('notify', SmartArrayBase::$onOffsetAccess, 'the live value; an earlier test leaked if this differs');
    }

    //endregion
    //region notify mode

    #[DataProvider('offsetOperationProvider')]
    public function testNotifyEchoesNoticeAndFiresDeprecation(string $class, Closure $operation, array $expectedMessages): void
    {
        $sa = self::sample($class);

        [[, $output], $deprecations] = $this->withOffsetAccess('notify', fn() => $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $operation($sa))
        ));

        $this->assertSame($this->expectedEcho($expectedMessages), $this->normalizeCaller($output));
        $this->assertSame($this->expectedMessages($expectedMessages), $this->normalizeCaller($deprecations));
    }

    #[DataProvider('modeProvider')]
    public function testNotifyPerformsTheOperation(string $class): void
    {
        $sa = self::sample($class);

        [[$name, $issetName, $issetMissing], $output] = $this->withOffsetAccess('notify', fn() => $this->captureOutput(function () use ($sa) {
            $sa['city'] = 'Vancouver';
            $sa[]       = 'appended';
            unset($sa['users.id']);
            return [$sa['name'], isset($sa['name']), isset($sa['zzz'])];
        }));

        $this->assertModeValue('Bob', $name, $class);
        $this->assertTrue($issetName);
        $this->assertFalse($issetMissing);
        $this->assertSame(['name' => 'Bob', '' => 'blank', 0 => 'zero', 'city' => 'Vancouver', 1 => 'appended'], $sa->toArray());
        $this->assertSame(6, substr_count($output, "\nDeprecated: "), 'one notice per offset operation');
    }

    //endregion
    //region log mode

    #[DataProvider('offsetOperationProvider')]
    public function testLogFiresDeprecationWithoutEchoing(string $class, Closure $operation, array $expectedMessages): void
    {
        $sa = self::sample($class);

        [[, $output], $deprecations] = $this->withOffsetAccess('log', fn() => $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $operation($sa))
        ));

        $this->assertSame('', $output, 'log mode is for legacy code mid-migration: nothing reaches the page');
        $this->assertSame($this->expectedMessages($expectedMessages), $this->normalizeCaller($deprecations));
    }

    #[DataProvider('modeProvider')]
    public function testLogPerformsTheOperation(string $class): void
    {
        $sa = self::sample($class);

        [[$name, $issetName, $issetMissing], $output] = $this->withOffsetAccess('log', fn() => $this->captureOutput(function () use ($sa) {
            $sa['city'] = 'Vancouver';
            $sa[]       = 'appended';
            unset($sa['users.id']);
            return [$sa['name'], isset($sa['name']), isset($sa['zzz'])];
        }));

        $this->assertModeValue('Bob', $name, $class);
        $this->assertTrue($issetName);
        $this->assertFalse($issetMissing);
        $this->assertSame(['name' => 'Bob', '' => 'blank', 0 => 'zero', 'city' => 'Vancouver', 1 => 'appended'], $sa->toArray());
        $this->assertSame('', $output);
    }

    //endregion
    //region throw mode

    #[DataProvider('offsetOperationProvider')]
    public function testThrowRaisesRuntimeExceptionWithTheSuggestion(string $class, Closure $operation, array $expectedMessages): void
    {
        $sa = self::sample($class);

        [[$thrown, $output], $deprecations] = $this->withOffsetAccess('throw', fn() => $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $this->catchThrowable(fn() => $operation($sa)))
        ));

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame($expectedMessages[0] . ' in FILE:LINE.', $this->normalizeCaller($thrown->getMessage()));
        $this->assertSame('', $output, 'throw mode replaces the notice, it does not add to it');
        $this->assertSame([], $deprecations, 'throw mode exits before trigger_error()');
    }

    #[DataProvider('modeProvider')]
    public function testThrowLeavesWritesUnapplied(string $class): void
    {
        $sa = self::sample($class);

        $this->withOffsetAccess('throw', function () use ($sa) {
            $this->assertInstanceOf(RuntimeException::class, $this->catchThrowable(function () use ($sa) {
                $sa['city'] = 'Vancouver';
            }));
            $this->assertInstanceOf(RuntimeException::class, $this->catchThrowable(function () use ($sa) {
                $sa[] = 'appended';
            }));
        });

        $this->assertFalse(isset($sa->city), 'the assignment must not run');
        $this->assertSame(['name' => 'Bob', 'users.id' => 5, '' => 'blank', 0 => 'zero'], $sa->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testThrowLeavesUnsetUnapplied(string $class): void
    {
        $sa = self::sample($class);

        $this->withOffsetAccess('throw', function () use ($sa) {
            $this->assertInstanceOf(RuntimeException::class, $this->catchThrowable(function () use ($sa) {
                unset($sa['name']);
            }));
        });

        $this->assertTrue(isset($sa->name), 'the unset must not run');
        $this->assertSame(['name' => 'Bob', 'users.id' => 5, '' => 'blank', 0 => 'zero'], $sa->toArray());
    }

    #[DataProvider('modeProvider')]
    public function testThrowPreemptsTheMissingKeyWarning(string $class): void
    {
        $sa = self::sample($class);

        [[$thrown, $output], $deprecations] = $this->withOffsetAccess('throw', fn() => $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $this->catchThrowable(fn() => $sa['zzz']))
        ));

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame("Replace ['zzz'] with ->zzz in FILE:LINE.", $this->normalizeCaller($thrown->getMessage()));
        $this->assertSame('', $output, 'the read never happens, so there is no "zzz is undefined" warning');
        $this->assertSame([], $deprecations);
    }

    //endregion
    //region Invalid mode values

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidModeProvider(): array
    {
        return [
            'unknown word'  => ['bogus',   "Invalid SmartArrayBase::\$onOffsetAccess value: 'bogus'. Expected 'log', 'notify', or 'throw'."],
            'empty string'  => ['',        "Invalid SmartArrayBase::\$onOffsetAccess value: ''. Expected 'log', 'notify', or 'throw'."],
            'wrong case'    => ['NOTIFY',  "Invalid SmartArrayBase::\$onOffsetAccess value: 'NOTIFY'. Expected 'log', 'notify', or 'throw'."],
            'padded'        => ['notify ', "Invalid SmartArrayBase::\$onOffsetAccess value: 'notify '. Expected 'log', 'notify', or 'throw'."],
        ];
    }

    #[DataProvider('invalidModeProvider')]
    public function testInvalidModeThrowsInvalidArgumentException(string $mode, string $expectedMessage): void
    {
        $sa = SmartArray::new(['name' => 'Bob']);

        [[$thrown, $output], $deprecations] = $this->withOffsetAccess($mode, fn() => $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $this->catchThrowable(fn() => $sa['name']))
        ));

        $this->assertInstanceOf(InvalidArgumentException::class, $thrown);
        $this->assertSame($expectedMessage, $thrown->getMessage());
        $this->assertSame('', $output);
        $this->assertSame([], $deprecations);
    }

    public function testInvalidModeThrowsForEveryOffsetOperation(): void
    {
        $expectedMessage = "Invalid SmartArrayBase::\$onOffsetAccess value: 'bogus'. Expected 'log', 'notify', or 'throw'.";

        $this->withOffsetAccess('bogus', function () use ($expectedMessage) {
            $sa = SmartArray::new(['name' => 'Bob']);

            $operations = [
                'get'    => fn() => $sa['name'],
                'set'    => function () use ($sa) { $sa['city'] = 'Vancouver'; },
                'append' => function () use ($sa) { $sa[] = 'appended'; },
                'unset'  => function () use ($sa) { unset($sa['name']); },
                'isset'  => fn() => isset($sa['name']),
            ];

            foreach ($operations as $label => $operation) {
                $thrown = $this->catchThrowable($operation);
                $this->assertInstanceOf(InvalidArgumentException::class, $thrown, "$label should report the invalid setting");
                $this->assertSame($expectedMessage, $thrown->getMessage(), "$label message");
            }

            $this->assertSame(['name' => 'Bob'], $sa->toArray(), 'no operation runs while the setting is invalid');
        });
    }

    //endregion
    //region Signal-free access

    #[DataProvider('modeProvider')]
    public function testPropertySyntaxIsSignalFreeInEveryMode(string $class): void
    {
        foreach (['notify', 'log', 'throw', 'bogus'] as $mode) {
            $sa = $class::new(['name' => 'Bob']);

            [[$name, $output], $deprecations] = $this->withOffsetAccess($mode, fn() => $this->captureDeprecations(
                fn() => $this->captureOutput(function () use ($sa) {
                    $this->assertTrue(isset($sa->name));
                    $this->assertFalse(isset($sa->zzz));
                    $this->assertFalse(empty($sa->name));
                    $sa->age = 30;
                    unset($sa->age);
                    $sa->set('city', 'Vancouver');
                    $sa->get('city');
                    return $sa->name;
                })
            ));

            $this->assertModeValue('Bob', $name, $class, "$mode mode");
            $this->assertSame('', $output, "$mode mode: property syntax must not echo");
            $this->assertSame([], $deprecations, "$mode mode: property syntax must not log");
        }
    }

    #[DataProvider('modeProvider')]
    public function testInternalMethodsNeverUseOffsetAccess(string $class): void
    {
        // Run in throw mode: any internal $this['key'] would raise instead of quietly
        // logging. The old suite only filtered the deprecation list for this.
        [[, $output], $deprecations] = $this->withOffsetAccess('throw', fn() => $this->captureDeprecations(
            fn() => $this->captureOutput(function () use ($class) {
                $flat = $class::new(['a' => 1, 'b' => 2, 'c' => 3]);
                $flat->first();
                $flat->last();
                $flat->nth(1);
                $flat->at(-1);
                $flat->get('a');
                $flat->set('d', 4);
                $flat->keys();
                $flat->values();
                $flat->contains(1);
                $flat->filter();
                $flat->unique();
                $flat->sort();
                $flat->map(fn($value) => $value);
                $flat->each(fn($value, $key) => null);
                $flat->implode(',');
                $flat->count();
                $flat->isEmpty();
                $flat->toArray();
                json_encode($flat);

                $iterated = [];
                foreach ($flat as $key => $value) {
                    $iterated[$key] = $value;
                }
                $this->assertCount(4, $iterated, 'iteration must not go through offsetGet either');

                $rows = $class::new([['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]);
                $rows->column('name');
                $rows->columnAt(0);
                $rows->indexBy('id');
                $rows->groupBy('id');
                $rows->where('id', 1);
                $rows->whereNot('id', 1);
                $rows->sortBy('id');
                $rows->merge([['id' => 3, 'name' => 'Carol']]);
                $rows->asHtml();
                $rows->asRaw();
                $rows->first()->get('name');
            })
        ));

        $this->assertSame('', $output);
        $this->assertSame([], $deprecations);
    }

    //endregion
    //region Key shapes the suggestion text gets wrong

    #[DataProvider('modeProvider')]
    public function testNumericStringKeySuggestsBraceSyntax(string $class): void
    {
        // PHP converts '5' to int 5 in an array literal, but hands ArrayAccess the
        // string it was written with, so the suggestion is ->{'5'} for a key that
        // ->{5} also reaches. Both work; the text just doesn't match the stored key.
        $sa = $class::new(['5' => 'five']);

        [[$value, $output], $deprecations] = $this->withOffsetAccess('notify', fn() => $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $sa['5'])
        ));

        $this->assertModeValue('five', $value, $class);
        $this->assertSame("\nDeprecated: Replace ['5'] with ->{'5'} in FILE:LINE.\n", $this->normalizeCaller($output));
        $this->assertSame(["Replace ['5'] with ->{'5'} in FILE:LINE."], $this->normalizeCaller($deprecations));
        $this->assertSame([5 => 'five'], $sa->toArray(), 'the stored key is an integer');
    }

    public function testNullOffsetReadUsesTheEmptyStringKey(): void
    {
        // PHP array semantics: $arr[null] reads key '' - same as offsetUnset()
        // and offsetExists() (tested below)
        $sa = SmartArray::new(['' => 'blank']);

        [[$value, $output], $deprecations] = $this->withOffsetAccess('notify', fn() => $this->captureDeprecations(
            fn() => $this->captureOutput(fn() => $sa[null])
        ));

        $this->assertSame('blank', $value);
        $this->assertSame("\nDeprecated: Replace [''] with ->get('') in FILE:LINE.\n", $this->normalizeCaller($output));
        $this->assertSame(["Replace [''] with ->get('') in FILE:LINE."], $this->normalizeCaller($deprecations));
    }

    public function testNullOffsetExistsAndUnsetUseTheEmptyStringKey(): void
    {
        $sa = SmartArray::new(['' => 'blank']);

        [[$exists, $output], $deprecations] = $this->withOffsetAccess('notify', fn() => $this->captureDeprecations(
            fn() => $this->captureOutput(function () use ($sa) {
                $found = isset($sa[null]);
                unset($sa[null]);
                return $found;
            })
        ));

        $this->assertTrue($exists);
        $this->assertSame([], $sa->toArray());
        $this->assertSame(
            "\nDeprecated: Replace [] with ->get('') in FILE:LINE.\n\nDeprecated: Replace [] with ->get('') in FILE:LINE.\n",
            $this->normalizeCaller($output),
        );
        $this->assertSame(
            ["Replace [] with ->get('') in FILE:LINE.", "Replace [] with ->get('') in FILE:LINE."],
            $this->normalizeCaller($deprecations),
        );
    }

    //endregion
    //region Nested chains

    public function testNestedIssetChainSignalsThreeTimes(): void
    {
        // isset($sa['user']['name']) reports ['user'] twice - PHP checks the outer key
        // with offsetExists, then reads it with offsetGet to reach the inner one. That
        // call sequence is PHP's, not ours; all notices agree on the suggestion.
        $sa = SmartArray::new(['user' => ['name' => 'Bob']]);

        [$exists, $deprecations] = $this->withOffsetAccess('log', fn() => $this->captureDeprecations(
            fn() => isset($sa['user']['name'])
        ));

        $this->assertTrue($exists);
        $this->assertSame([
            "Replace ['user'] with ->user in FILE:LINE.",
            "Replace ['user'] with ->user in FILE:LINE.",
            "Replace ['name'] with ->name in FILE:LINE.",
        ], $this->normalizeCaller($deprecations));
    }

    public function testNestedReadChainSignalsOncePerLevel(): void
    {
        $sa = SmartArrayHtml::new(['user' => ['name' => 'Bob']]);

        [$value, $deprecations] = $this->withOffsetAccess('log', fn() => $this->captureDeprecations(
            fn() => $sa['user']['name']
        ));

        $this->assertModeValue('Bob', $value, SmartArrayHtml::class);
        $this->assertSame([
            "Replace ['user'] with ->user in FILE:LINE.",
            "Replace ['name'] with ->name in FILE:LINE.",
        ], $this->normalizeCaller($deprecations));
    }

    //endregion
    //region Helpers

    /**
     * Swap $onOffsetAccess for the duration of $fn and restore it afterwards, so a
     * failing assertion inside $fn cannot leave the static set for the next test.
     */
    private function withOffsetAccess(string $mode, callable $fn): mixed
    {
        $original                       = SmartArrayBase::$onOffsetAccess;
        SmartArrayBase::$onOffsetAccess = $mode;
        try {
            return $fn();
        } finally {
            SmartArrayBase::$onOffsetAccess = $original;
        }
    }

    /**
     * Run $fn and return whatever it threw, or null if it completed. Used instead of
     * try/catch + fail() because PHPUnit's own failure exceptions extend RuntimeException.
     */
    private function catchThrowable(callable $fn): ?Throwable
    {
        try {
            $fn();
        } catch (Throwable $e) {
            return $e;
        }
        return null;
    }

    /**
     * Replace this file's name and line number in messages with FILE:LINE so the
     * rest of the text can be asserted literally.
     */
    private function normalizeCaller(string|array $text): string|array
    {
        if (is_array($text)) {
            return array_map(fn(string $line) => $this->normalizeCaller($line), $text);
        }
        return preg_replace('/GlobalSettingsTest\.php:\d+/', 'FILE:LINE', $text);
    }

    /**
     * @param string[] $messages
     * @return string[]
     */
    private function expectedMessages(array $messages): array
    {
        return array_map(fn(string $message) => "$message in FILE:LINE.", $messages);
    }

    /**
     * @param string[] $messages
     */
    private function expectedEcho(array $messages): string
    {
        $echoed = array_map(fn(string $message) => "\nDeprecated: $message in FILE:LINE.\n", $messages);
        return implode('', $echoed);
    }

    //endregion
}
