<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\Tests\Support\ExitCalled;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Closure;
use RuntimeException;

/**
 * The empty guards: or404(), orDie(), orThrow(), orRedirect().
 *
 * On a non-empty array every guard returns $this so it can sit mid-chain. On
 * an empty array or404/orDie/orRedirect end the request through self::exit(),
 * which throws ExitCalled under PHPUnit (tests/bootstrap.php loads the class),
 * so the test asserts the exact bytes, the exit status, and http_response_code()
 * in-process. Only the headers-already-sent fail-fast still runs in a subprocess
 * (tests/Support/bin/): PHPUnit buffers test output, so headers_sent() can never
 * become true here.
 *
 * Messages are HTML-encoded on the way out of every guard: they reach a browser
 * and usually interpolate user input. The encoding is the same in both modes.
 *
 * CLI limits: header() does nothing and headers_list() is empty under CLI, so
 * the Location and Content-Type headers cannot be asserted anywhere here.
 */
class EmptyGuardsTest extends SmartArrayTestCase
{
    /** The exact page or404() writes, with %s for the HTML-encoded message. */
    private const NOT_FOUND_PAGE = <<<'__HTML__'
        <!DOCTYPE html>
        <html>
        <head>
            <title>Not Found</title>
        </head>
        <body>
            <h1>Not Found</h1>
            <p>%s</p>
        </body>
        </html>
        __HTML__;

    protected function setUp(): void
    {
        http_response_code_clear();   // the status survives between tests in one process; start each from false
    }

    /**
     * Run $fn expecting it to end in self::exit(). Returns [the ExitCalled it threw, what it printed first].
     *
     * @return array{0: ExitCalled, 1: string}
     */
    private function expectExit(callable $fn): array
    {
        ob_start();
        try {
            $fn();
        } catch (ExitCalled $e) {
            return [$e, ob_get_clean()];
        } finally {
            if (!isset($e)) {   // returned or threw something else: close the buffer so PHPUnit does not flag the test as risky
                ob_end_clean();
            }
        }
        $this->fail('expected self::exit() to be called');
    }

    //region Non-empty arrays pass through

    /**
     * @return array<string, array{class-string<SmartArrayBase>, string, array<int, string>}>
     */
    public static function modeAndGuardProvider(): array
    {
        $guards = [
            'or404 (default message)' => ['or404', []],
            'or404'                   => ['or404', ['Not found']],
            'orDie'                   => ['orDie', ['Gone']],
            'orThrow'                 => ['orThrow', ['Gone']],
            'orRedirect'              => ['orRedirect', ['/login']],
        ];

        $cases = [];
        foreach (self::modeProvider() as $mode => [$class]) {
            foreach ($guards as $label => [$method, $args]) {
                $cases["$mode: $label"] = [$class, $method, $args];
            }
        }
        return $cases;
    }

    /**
     * orRedirect() is in here because PHPUnit buffers test output, so
     * headers_sent() is false and the fail-fast check passes.
     */
    #[DataProvider('modeAndGuardProvider')]
    public function testGuardsReturnSameInstanceWhenNotEmpty(string $class, string $method, array $args): void
    {
        $sa = $class::new(['name' => 'Bob']);

        [$result, $output] = $this->captureOutput(fn() => $sa->$method(...$args));

        $this->assertSame($sa, $result, "$method() should return the same instance for chaining");
        $this->assertSame('', $output, "$method() should print nothing on a non-empty array");
    }

    /**
     * @return array<string, array{array<int|string, mixed>}>
     */
    public static function falsyContentsProvider(): array
    {
        return [
            'zero'         => [[0]],
            'empty string' => [['']],
            'false'        => [[false]],
            'null'         => [[null]],
            'empty array'  => [[[]]],
        ];
    }

    /**
     * The guards fire on element count, not on whether the elements are falsy.
     * orThrow() stands in for all four: they share one empty($this->data) check,
     * and a broken check here throws instead of killing the PHPUnit process.
     */
    #[DataProvider('falsyContentsProvider')]
    public function testGuardsTreatFalsyElementsAsNotEmpty(array $data): void
    {
        $sa = SmartArray::new($data);

        $this->assertSame($sa, $sa->orThrow('should not throw'));
    }

    //endregion
    //region orThrow()

    /**
     * @return array<string, array{class-string<SmartArrayBase>, string, string}>
     */
    public static function modeAndMessageProvider(): array
    {
        $messages = [
            'plain'        => ['No results found', 'No results found'],
            'html special' => ["No results for \"<b>O'Brien</b>\" & co", 'No results for &quot;&lt;b&gt;O&apos;Brien&lt;/b&gt;&quot; &amp; co'],
            'invalid utf8' => ["caf\xE9", "caf\u{FFFD}"],  // ENT_SUBSTITUTE replaces undecodable bytes
            'empty string' => ['', ''],
        ];

        $cases = [];
        foreach (self::modeProvider() as $mode => [$class]) {
            foreach ($messages as $label => [$text, $expected]) {
                $cases["$mode: $label"] = [$class, $text, $expected];
            }
        }
        return $cases;
    }

    #[DataProvider('modeAndMessageProvider')]
    public function testOrThrowThrowsRuntimeExceptionWithEncodedMessage(string $class, string $text, string $expectedMessage): void
    {
        $sa = $class::new([]);

        [$caught, $output] = $this->captureOutput(function () use ($sa, $text) {
            try {
                $sa->orThrow($text);
            } catch (RuntimeException $e) {
                return $e;
            }
            return null;
        });

        $this->assertNotNull($caught, 'orThrow() on an empty array should throw');
        $this->assertSame(RuntimeException::class, $caught::class, 'the contract is RuntimeException itself, not a subclass');
        $this->assertSame($expectedMessage, $caught->getMessage());
        $this->assertSame('', $output, 'orThrow() should print nothing');
    }

    //endregion
    //region or404() exit path

    /**
     * @return array<string, array{class-string<SmartArrayBase>, string|null, string}>
     */
    public static function or404Provider(): array
    {
        $messages = [
            'default message' => [null, 'The requested URL was not found on this server.'],
            'custom message'  => ["<b>O'Brien</b> & \"co\"", '&lt;b&gt;O&apos;Brien&lt;/b&gt; &amp; &quot;co&quot;'],
        ];

        $cases = [];
        foreach (self::modeProvider() as $mode => [$class]) {
            foreach ($messages as $label => [$text, $expected]) {
                $cases["$mode: $label"] = [$class, $text, $expected];
            }
        }
        return $cases;
    }

    #[DataProvider('or404Provider')]
    public function testOr404WritesNotFoundPageAndExits(string $class, ?string $text, string $expectedMessageHtml): void
    {
        [$exit, $output] = $this->expectExit(fn() => $class::new([])->or404($text));

        $this->assertSame(sprintf(self::NOT_FOUND_PAGE, $expectedMessageHtml), $output);
        $this->assertSame(404, http_response_code());
        $this->assertSame(1, $exit->status, 'or404() exits with status 1 like orDie(), so shells and cron see the failure');
    }

    public function testOr404WithEmptyMessageSkipsTheDefault(): void
    {
        // Only null selects the default text, so an empty message renders an empty paragraph
        [$exit, $output] = $this->expectExit(fn() => SmartArray::new([])->or404(''));

        $this->assertSame(sprintf(self::NOT_FOUND_PAGE, ''), $output);
        $this->assertSame(404, http_response_code());
        $this->assertSame(1, $exit->status);
    }

    //endregion
    //region orDie() exit path

    #[DataProvider('modeProvider')]
    public function testOrDieEchoesEncodedMessageAndExitsWithStatus1(string $class): void
    {
        [$exit, $output] = $this->expectExit(fn() => $class::new([])->orDie("<b>O'Brien</b> & \"co\""));

        $this->assertSame('&lt;b&gt;O&apos;Brien&lt;/b&gt; &amp; &quot;co&quot;', $output, 'message only, no HTML shell and no trailing newline');
        $this->assertFalse(http_response_code(), 'orDie() sets no HTTP status');
        $this->assertSame(1, $exit->status, 'exit 1 so shell scripts and cron jobs see the failure');
    }

    //endregion
    //region orRedirect() exit path

    public function testOrRedirectSets302AndExitsWithoutOutput(): void
    {
        [$exit, $output] = $this->expectExit(fn() => SmartArray::new([])->orRedirect('/login?a=1&b=2'));

        $this->assertSame('', $output, 'a redirect writes no body');
        $this->assertSame(302, http_response_code());
        $this->assertSame(0, $exit->status, 'orRedirect() exits with the default status 0');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function arrayStateProvider(): array
    {
        return [
            'empty array'     => ['empty'],
            'non-empty array' => ['filled'],
        ];
    }

    /**
     * Fail-fast: a call placed after output throws on every request, not only on
     * the requests where the array happens to be empty.
     */
    #[DataProvider('arrayStateProvider')]
    public function testOrRedirectThrowsWhenHeadersAlreadySent(string $arrayState): void
    {
        $script = 'redirect-after-output.php';

        [$stdout, $stderr, $exitCode] = $this->runScript($script, $arrayState);

        // The script reports the line it echoed on; the message should name that same line
        $this->assertSame(1, preg_match('/^output-line=(\d+)$/m', $stderr, $matches), "stderr should report the output line: $stderr");
        $outputLine = $matches[1];

        // basename only: the message can reach page output, so it never carries the full path
        $expectedStderr = "class=" . RuntimeException::class . "\n"
                        . "message=orRedirect(): headers already sent in $script on line $outputLine\n"
                        . "output-line=$outputLine\n"
                        . "status=false";

        $this->assertSame($expectedStderr, $stderr, 'throws RuntimeException naming the file and line output started on, sets no status, and does not redirect');
        $this->assertSame('output-before-redirect', $stdout);
        $this->assertSame(0, $exitCode);
    }

    //endregion
    //region self::exit()

    /**
     * @return array<string, array{string|int, string, int}>
     */
    public static function exitCases(): array
    {
        return [
            'string prints and exits 0'       => ['Not found', 'Not found', 0],
            'int sets status, prints nothing' => [3, '', 3],
            'no argument exits 0'             => [0, '', 0],
        ];
    }

    /**
     * The seam every guard ends in. While tests/bootstrap.php has ExitCalled loaded, self::exit()
     * throws it, carrying the output and status with the same string-or-int rules as PHP's exit.
     */
    #[DataProvider('exitCases')]
    public function testExitThrowsExitCalledUnderPhpunit(string|int $arg, string $expectedOutput, int $expectedStatus): void
    {
        $exit = Closure::bind(static fn() => SmartArray::exit($arg), null, SmartArray::class);   // protected: call from inside the class

        try {
            $exit();
        } catch (ExitCalled $e) {
            $this->assertSame($expectedOutput, $e->output);
            $this->assertSame($expectedStatus, $e->status);
            $this->assertNotInstanceOf(RuntimeException::class, $e, 'a catch (RuntimeException) in the code under test must not swallow it');
            return;
        }
        $this->fail('self::exit() should have thrown ExitCalled');
    }

    /**
     * Outside PHPUnit nothing loads ExitCalled, so self::exit() is a real exit: the message reaches
     * stdout and the process ends with the status. One fresh php with the script on stdin: no shell
     * (Windows escapeshellarg() drops "!" and cmd.exe reads 2>/dev/null as a file path) and no
     * stderr pipe to deadlock on.
     */
    public function testExitPrintsAndSetsStatusOutsidePhpunit(): void
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script   = '<?php require ' . var_export($autoload, true) . '; \Itools\SmartArray\SmartArray::new([])->orDie("Gone!");';
        $process  = proc_open([PHP_BINARY], [['pipe', 'r'], ['pipe', 'w']], $pipes);
        fwrite($pipes[0], $script);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $status = proc_close($process);

        $this->assertSame('Gone!', $stdout);
        $this->assertSame(1, $status, 'orDie() exits 1 so shell scripts and cron jobs see the failure');
    }

    //endregion
    //region Subprocess runner

    /**
     * Run a script from tests/Support/bin in its own PHP process.
     *
     * @return array{0: string, 1: string, 2: int} stdout, stderr, exit code
     */
    private function runScript(string $script, string ...$args): array
    {
        return $this->runCommand([PHP_BINARY, dirname(__DIR__) . "/Support/bin/$script", ...$args]);
    }

    //endregion
}
