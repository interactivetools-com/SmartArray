<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\SmartNull;
use Itools\SmartArray\Tests\Support\Fixtures;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Developer output: debug(), help(), and what print_r()/var_dump() show.
 *
 * debug() has two shapes, not three: level 0 is the compact listing, and every
 * level above 0 is the verbose one (types, object ids, object properties).
 * Expectations here are whole-output strings, so key alignment, indentation,
 * and block order are all part of the contract.
 *
 * Two normalizations, both narrow:
 * - Object ids (spl_object_id) change per run, so verbose expectations carry
 *   {id} and {rootId} placeholders filled in from the objects under test. The
 *   ids stay asserted, which is what makes "Root #n" and "(self)" meaningful.
 * - debug() pads short values with trailing spaces so load() annotations line
 *   up, and this file's trailing spaces get stripped by .editorconfig. Heredoc
 *   expectations are compared line-by-line rtrimmed; one test
 *   (testDebugPadsShortValuesSoLoadAnnotationsLineUp) pins the raw bytes,
 *   padding included, using quoted strings the editor cannot touch.
 *
 * CLI limits: xmpWrap() short-circuits to plain output under CLI (PHP_SAPI),
 * so every expectation here pins the unwrapped format. The <xmp>-wrapped web
 * path can't be simulated in-process.
 */
class DebugTest extends SmartArrayTestCase
{
    //region debug(): compact output (level 0)

    #[DataProvider('modeProvider')]
    public function testDebugLevelZeroShowsHeaderAndUnquotedValues(string $class): void
    {
        $sa = $class::new(Fixtures::record());

        [, $output] = $this->captureOutput(fn() => $sa->debug());

        // The header is the only mode-specific part of the output; stored data is raw in both modes
        $header = match ($class) {
            SmartArray::class     => 'Itools\SmartArray\SmartArray - Values are returned **as-is** on access (no extra encoding)',
            SmartArrayHtml::class => 'Itools\SmartArray\SmartArrayHtml - Values are returned as **SmartStrings** on access',
        };

        $expected = <<<__TEXT__

            $header

            [
                'html'    => <p>"It's"</p>
                'int'     => 0
                'float'   => 1.23
                'string'  => "green"
                'bool'    => false
                'null'    => null
                'isFirst' => Q
            ]

            __TEXT__;

        $this->assertSame($expected, $this->rtrimLines($output));
    }

    public function testDebugLevelZeroIndentsNestedRows(): void
    {
        $sa = SmartArray::new(array_slice(Fixtures::records(), 0, 2));

        [, $output] = $this->captureOutput(fn() => $sa->debug());

        $expected = <<<'__TEXT__'

            Itools\SmartArray\SmartArray - Values are returned **as-is** on access (no extra encoding)

            [
                [0] => [
                    'html'    => <img src='' alt='"'>
                    'int'     => 7
                    'float'   => 5.7
                    'string'  => &nbsp;
                    'bool'    => true
                    'null'    => null
                    'isFirst' => C
                ],
                [1] => [
                    'html'    => <p>"It's"</p>
                    'int'     => 0
                    'float'   => 1.23
                    'string'  => "green"
                    'bool'    => false
                    'null'    => null
                    'isFirst' => Q
                ]
            ]

            __TEXT__;

        $this->assertSame($expected, $this->rtrimLines($output));
    }

    public function testDebugLevelZeroShowsEmptyArrayAsEmptyBrackets(): void
    {
        $sa = SmartArray::new([]);

        [, $output] = $this->captureOutput(fn() => $sa->debug());

        $expected = <<<'__TEXT__'

            Itools\SmartArray\SmartArray - Values are returned **as-is** on access (no extra encoding)

            [
            ]

            __TEXT__;

        $this->assertSame($expected, $this->rtrimLines($output));
    }

    /**
     * The one raw-bytes expectation: values are padded to a 12 character column
     * so the " // ->load('key') for more" annotations line up. Written as quoted
     * strings because .editorconfig strips trailing spaces from heredocs.
     */
    public function testDebugPadsShortValuesSoLoadAnnotationsLineUp(): void
    {
        $sa = SmartArray::new(['id' => 7, 'name' => 'Amy']);

        [, $output] = $this->captureOutput(fn() => $sa->debug());

        $expected = "\n"
                  . "Itools\\SmartArray\\SmartArray - Values are returned **as-is** on access (no extra encoding)\n"
                  . "\n"
                  . "[\n"
                  . "    'id'   => 7           \n"
                  . "    'name' => Amy         \n"
                  . "]\n";

        $this->assertSame($expected, $output);
    }

    public function testDebugAnnotatesOnlyKeysTheLoadHandlerSupports(): void
    {
        $sa = SmartArray::new(['title' => 'Post', 'author_id' => 7], ['loadHandler' => $this->authorLoadHandler()]);

        [, $output] = $this->captureOutput(fn() => $sa->debug());

        $expected = <<<'__TEXT__'

            Itools\SmartArray\SmartArray - Values are returned **as-is** on access (no extra encoding)

            [
                'title'     => Post
                'author_id' => 7            // ->load('author_id') for more
            ]

            __TEXT__;

        $this->assertSame($expected, $this->rtrimLines($output));
    }

    /**
     * load() refuses to run on a record set, so a row holding any array value
     * loses every annotation, not just the one for the array key.
     */
    public function testDebugSkipsLoadAnnotationsWhenAnyValueIsAnArray(): void
    {
        $sa = SmartArray::new(['title' => 'Post', 'author_id' => 7, 'tags' => ['php']], ['loadHandler' => $this->authorLoadHandler()]);

        [, $output] = $this->captureOutput(fn() => $sa->debug());

        $this->assertStringNotContainsString('->load(', $this->rtrimLines($output));
    }

    //endregion
    //region debug(): verbose output (level 1 and up)

    public function testDebugLevelOneAddsTypesObjectIdsAndProperties(): void
    {
        $sa = SmartArray::new(Fixtures::record());

        [, $output] = $this->captureOutput(fn() => $sa->debug(1));

        $expected = <<<'__TEXT__'

            Itools\SmartArray\SmartArray - Values are returned **as-is** on access (no extra encoding)

            [                                                                                 // SmartArray #{id}, Root #{id} (self)
                'html'    => '<p>"It\'s"</p>',                                                // string
                'int'     => 0,                                                               // int
                'float'   => 1.23,                                                            // float
                'string'  => '"green"',                                                       // string
                'bool'    => false,                                                           // bool
                'null'    => null,                                                            // null
                'isFirst' => 'Q'                                                              // string
            ]

            Object Properties[                                                                // array
                'loadHandler' => null,                                                        // null
                'mysqli'      => [                                                            // array
                ],
                'root'        => SmartArray #{id}
            ]

            __TEXT__;

        $this->assertSame($this->fillObjectIds($expected, $sa, $sa), $this->rtrimLines($output));
    }

    /**
     * A row inside a record set reports its own id and its parent's, without
     * the "(self)" marker that marks a root array.
     */
    public function testDebugLevelOneMarksNonRootArraysWithTheirRootId(): void
    {
        $rows = SmartArray::new([['a' => 1], ['b' => 2]]);
        $row  = $rows->first();

        [, $output] = $this->captureOutput(fn() => $row->debug(1));

        $expected = <<<'__TEXT__'

            Itools\SmartArray\SmartArray - Values are returned **as-is** on access (no extra encoding)

            [                                                                                 // SmartArray #{id}, Root #{rootId}
                'a' => 1                                                                      // int
            ]

            Object Properties[                                                                // array
                'loadHandler' => null,                                                        // null
                'mysqli'      => [                                                            // array
                ],
                'root'        => SmartArray #{rootId}
            ]

            __TEXT__;

        $this->assertSame($this->fillObjectIds($expected, $row, $rows), $this->rtrimLines($output));
    }

    public function testDebugLevelTwoOutputsTheSameAsLevelOne(): void
    {
        $sa = SmartArray::new(Fixtures::record());

        [, $levelOne] = $this->captureOutput(fn() => $sa->debug(1));
        [, $levelTwo] = $this->captureOutput(fn() => $sa->debug(2));

        $this->assertSame($levelOne, $levelTwo, 'any level above 0 is the same verbose output');
    }

    /**
     * The properties block includes the loadHandler, and a handler from the
     * database layer is a closure - it prints as its type ("Closure") so
     * debug(1) works on database results, the arrays it exists for.
     */
    public function testDebugLevelOnePrintsClosureLoadHandlerAsItsType(): void
    {
        $sa = SmartArray::new(['author_id' => 7], ['loadHandler' => $this->authorLoadHandler()]);

        [, $output] = $this->captureOutput(fn() => $sa->debug(1));

        $this->assertStringContainsString('Closure', $output);
        $this->assertStringContainsString("'author_id'", $output, 'the data still prints');
    }

    public function testDebugLevelOneLeavesDataRowsKeyedRootUntouched(): void
    {
        // The root-property reformat runs on the properties block only, so a data
        // key that happens to be named 'root' prints its stored value
        $sa = SmartArray::new(['root' => 'abc123']);

        [, $output] = $this->captureOutput(fn() => $sa->debug(1));

        $this->assertStringContainsString("'root' => 'abc123'", $output, 'data value intact');
        $this->assertMatchesRegularExpression("/'root'\s+=> SmartArray #\d+/", $output, 'root property still reformatted');
    }

    //endregion
    //region debug(): mysqli metadata

    public function testDebugShowsQueryAboveDataAndMetadataBelow(): void
    {
        $sa = SmartArray::new(['id' => 1, 'name' => 'Amy'], [
            'mysqli' => [
                'query'         => "SELECT id, name\n  FROM users\n WHERE id = 1",
                'affected_rows' => 1,
                'insert_id'     => 0,
                'baseTable'     => 'users',
            ],
        ]);

        [, $output] = $this->captureOutput(fn() => $sa->debug());

        // The query prints twice: indented as written above the data, whitespace-collapsed in the metadata block
        $expected = <<<'__TEXT__'

            Itools\SmartArray\SmartArray - Values are returned **as-is** on access (no extra encoding)

            MySQL Query:
                SELECT id, name
                  FROM users
                 WHERE id = 1

            Array [
                'id'   => 1
                'name' => Amy
            ]

            MySQLi Metadata [
                'query'         => SELECT id, name FROM users WHERE id = 1
                'affected_rows' => 1
                'insert_id'     => 0
                'baseTable'     => users
            ]

            __TEXT__;

        $this->assertSame($expected, $this->rtrimLines($output));
    }

    public function testDebugOmitsQueryAndMetadataBlocksWhenThereIsNoMetadata(): void
    {
        $sa = SmartArray::new(['id' => 1]);

        [, $output] = $this->captureOutput(fn() => $sa->debug());

        $this->assertStringNotContainsString('MySQL Query:', $output);
        $this->assertStringNotContainsString('MySQLi Metadata', $output);
    }

    //endregion
    //region debug(): return value and xmp wrapping

    public function testDebugReturnsNull(): void
    {
        $sa = SmartArray::new(['a' => 1]);

        [$result, $output] = $this->captureOutput(fn() => $sa->debug());

        $this->assertNull($result, 'debug() is void: it echoes, it does not chain');
        $this->assertStringNotContainsString('<xmp>', $output, 'CLI output is plain - terminals show the tags literally');
    }

    /**
     * A global showme() wrapper is the common CMSB debug idiom; this pins that
     * debug() inside it produces clean plain output in a subprocess. (The
     * showme() skip inside xmpWrap() is web-only and unreachable from CLI
     * tests, so the wrapped path stays audited rather than asserted.)
     */
    public function testDebugSkipsXmpWrapWhenCalledFromGlobalShowmeFunction(): void
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $code     = <<<__PHP__
            require '$autoload';
            function showme(\$obj) { \$obj->debug(); }
            showme(\Itools\SmartArray\SmartArray::new(['a' => 1]));
            __PHP__;

        [$stdout, $stderr, $exitCode] = $this->runPhp($code);

        $this->assertSame('', $stderr);
        $this->assertSame(0, $exitCode);
        $this->assertStringNotContainsString('<xmp>', $stdout);

        $expected = <<<'__TEXT__'

            Itools\SmartArray\SmartArray - Values are returned **as-is** on access (no extra encoding)

            [
                'a' => 1
            ]

            __TEXT__;

        $this->assertSame($expected, $this->rtrimLines($stdout));
    }

    //endregion
    //region help()

    /** help() is deprecated; until removed, it prints links to the online docs */
    public function testHelpPrintsDocLinksPlainOnCli(): void
    {
        $sa = SmartArray::new(['a' => 1]);

        [$result, $output] = $this->captureOutput(fn() => $sa->help());

        $this->assertNull($result, 'help() is void');
        $this->assertStringContainsString('https://github.com/interactivetools-com/SmartArray#readme', $output);
        $this->assertStringContainsString('https://github.com/interactivetools-com/SmartArray/blob/main/docs/method-reference.md', $output);
        $this->assertStringNotContainsString('<xmp>', $output, 'plain output on CLI');
    }

    //endregion
    //region print_r(), var_dump(), var_export()

    public function testPrintRShowsOnlyTheArrayData(): void
    {
        $sa = SmartArray::new(['name' => 'Amy', 'age' => 30]);

        [, $output] = $this->captureOutput(fn() => print_r($sa));

        $expected = <<<'__TEXT__'
            Itools\SmartArray\SmartArray Object
            (
                [name] => Amy
                [age] => 30
            )

            __TEXT__;

        $this->assertSame($expected, $output);
    }

    public function testPrintRShowsNestedRowsAsChildObjectsOfTheSameMode(): void
    {
        $sa = SmartArrayHtml::new([['id' => 1]]);

        [, $output] = $this->captureOutput(fn() => print_r($sa));

        $expected = <<<'__TEXT__'
            Itools\SmartArray\SmartArrayHtml Object
            (
                [0] => Itools\SmartArray\SmartArrayHtml Object
                    (
                        [id] => 1
                    )

            )

            __TEXT__;

        $this->assertSame($expected, $output);
    }

    #[DataProvider('modeProvider')]
    public function testDebugInfoReturnsTheStoredDataOnly(string $class): void
    {
        $sa = $class::new(['name' => 'Amy', 'rows' => [['id' => 1]]]);

        $info = $sa->__debugInfo();

        $this->assertSame(['name', 'rows'], array_keys($info), 'no injected pseudo-properties');
        $this->assertSame('Amy', $info['name']);
        $this->assertInstanceOf($class, $info['rows'], 'nested rows stay objects, as print_r shows them');
    }

    public function testVarDumpHidesInternalProperties(): void
    {
        $sa = SmartArray::new(['name' => 'Amy']);

        [, $output] = $this->captureOutput(fn() => var_dump($sa));

        // Exact format varies (xdebug overrides var_dump), so assert what is shown and what is not
        $this->assertStringContainsString('Itools\SmartArray\SmartArray', $output);
        $this->assertStringContainsString('name', $output);
        $this->assertStringContainsString('Amy', $output);
        foreach (['useSmartStrings', 'loadHandler', 'mysqli', 'root', 'isLast', 'position'] as $internal) {
            $this->assertStringNotContainsString($internal, $output, "var_dump should not expose $internal");
        }
    }

    /**
     * var_export() ignores __debugInfo(), so it still prints every internal
     * property, and the self-referencing root triggers a PHP warning. Pinned
     * because it is the one dump that leaks internals; use print_r()/debug().
     */
    public function testVarExportStillShowsInternalPropertiesAndWarnsOnRoot(): void
    {
        $sa       = SmartArray::new(['name' => 'Amy']);
        $warnings = [];

        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        }, E_WARNING);
        try {
            [, $output] = $this->captureOutput(fn() => var_export($sa));
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['var_export does not handle circular references'], $warnings);
        $this->assertStringContainsString('Itools\SmartArray\SmartArray::__set_state(array(', $output);
        $this->assertStringContainsString("'useSmartStrings' => false,", $output);
        $this->assertStringContainsString("'root' => NULL,", $output, 'the circular root is exported as null');
    }

    //endregion
    //region SmartNull

    public function testSmartNullPrintRShowsValueOnly(): void
    {
        $smartNull = SmartArray::new([])->first();

        [, $output] = $this->captureOutput(fn() => print_r($smartNull));

        // Quoted strings, not a heredoc: print_r writes a null value as a trailing space
        $expected = "Itools\\SmartArray\\SmartNull Object\n"
                  . "(\n"
                  . "    [value] => \n"
                  . ")\n";

        $this->assertInstanceOf(SmartNull::class, $smartNull);
        $this->assertSame($expected, $output);
    }

    public function testSmartNullDebugInfoReturnsNullValue(): void
    {
        $smartNull = SmartArray::new([])->first();

        $this->assertSame(['value' => null], $smartNull->__debugInfo());
    }

    /**
     * SmartNull::help() prints the same doc links as SmartArray::help(),
     * with the same wrapping rule: plain on CLI, <xmp> only for text/html.
     */
    public function testSmartNullHelpPrintsDocLinksPlainOnCli(): void
    {
        $smartNull = SmartArray::new([])->first();

        [$result, $output] = $this->captureOutput(fn() => $smartNull->help());

        $expected = <<<'__TEXT__'
            SmartArray docs:  https://github.com/interactivetools-com/SmartArray#readme
            Method reference: https://github.com/interactivetools-com/SmartArray/blob/main/docs/method-reference.md
            __TEXT__;

        $this->assertNull($result, 'help() is void');
        $this->assertSame("\n$expected\n", $output, 'same xmpWrap() framing as SmartArray::help()');
    }

    /**
     * debug() on a SmartNull says what the object is - a missing key or empty
     * result - instead of dumping an empty array of the wrong class.
     */
    public function testSmartNullDebugDescribesTheMissingValue(): void
    {
        $smartNull = SmartArrayHtml::new(['title' => 'Hello'])->titel;

        [$result, $output] = $this->captureOutput(fn() => $smartNull->debug());

        $this->assertNull($result, 'debug() is void');
        $this->assertStringContainsString('Itools\SmartArray\SmartNull - missing key or empty result, value is null', $output);
        $this->assertStringContainsString('->isNotEmpty()', $output);
        $this->assertStringNotContainsString('SmartArrayHtml', $output, 'no longer misreported as an empty array');
    }

    //endregion
    //region Helpers

    /**
     * Compare expectations line by line with trailing spaces removed: debug()
     * pads values to a fixed column, and this file's trailing spaces are
     * stripped on save. testDebugPadsShortValuesSoLoadAnnotationsLineUp pins
     * the padding itself.
     */
    private function rtrimLines(string $output): string
    {
        return implode("\n", array_map('rtrim', explode("\n", $output)));
    }

    /**
     * Fill {id} and {rootId} in a verbose-output expectation with the real
     * spl_object_id values, which change from run to run.
     */
    private function fillObjectIds(string $expected, SmartArrayBase $obj, SmartArrayBase $root): string
    {
        return str_replace(
            ['{id}', '{rootId}'],
            [(string)spl_object_id($obj), (string)spl_object_id($root)],
            $expected,
        );
    }

    /**
     * A load handler that resolves one field, like a database layer would.
     */
    private function authorLoadHandler(): callable
    {
        return static fn(SmartArrayBase $obj, string $field) => match ($field) {
            'author_id' => [['id' => 7, 'name' => 'Amy'], ['query' => 'SELECT * FROM authors WHERE id = 7']],
            default     => false,
        };
    }

    /**
     * Run PHP code in a subprocess. Returns [stdout, stderr, exit code].
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private function runPhp(string $code): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $process = proc_open([PHP_BINARY, '-r', $code], $descriptors, $pipes);
        $this->assertNotFalse($process, 'could not start php subprocess');

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [$stdout, $stderr, proc_close($process)];
    }

    //endregion
}
