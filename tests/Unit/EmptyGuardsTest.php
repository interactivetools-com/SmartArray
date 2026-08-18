<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * The empty guards: or404(), orDie(), orThrow(), orRedirect().
 *
 * On a non-empty array every guard returns $this so it can sit mid-chain. On
 * an empty array or404/orDie/orRedirect end the request, so those paths run in
 * a subprocess (tests/Support/bin/) and the test asserts the exact bytes, the
 * exit code, and the HTTP status the script reports from a shutdown handler.
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
     * @return array<string, array{array<int, string>, string}>
     */
    public static function or404Provider(): array
    {
        return [
            'raw: default message'  => [['or404-default', '', 'raw'], 'The requested URL was not found on this server.'],
            'raw: custom message'   => [['or404', "<b>O'Brien</b> & \"co\"", 'raw'], '&lt;b&gt;O&apos;Brien&lt;/b&gt; &amp; &quot;co&quot;'],
            'html: custom message'  => [['or404', "<b>O'Brien</b> & \"co\"", 'html'], '&lt;b&gt;O&apos;Brien&lt;/b&gt; &amp; &quot;co&quot;'],
            'html: default message' => [['or404-default', '', 'html'], 'The requested URL was not found on this server.'],
        ];
    }

    #[DataProvider('or404Provider')]
    public function testOr404WritesNotFoundPageAndExits(array $args, string $expectedMessageHtml): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('empty-guard.php', ...$args);

        $this->assertSame(sprintf(self::NOT_FOUND_PAGE, $expectedMessageHtml), $stdout);
        $this->assertSame('status=404', $stderr, 'status set to 404 and the guard exited (no NOT-REACHED)');
        $this->assertSame(1, $exitCode, 'or404() exits with status 1 like orDie(), so shells and cron see the failure');
    }

    public function testOr404WithEmptyMessageSkipsTheDefault(): void
    {
        // Only null selects the default text, so an empty message renders an empty paragraph
        [$stdout, $stderr, $exitCode] = $this->runScript('empty-guard.php', 'or404', '', 'raw');

        $this->assertSame(sprintf(self::NOT_FOUND_PAGE, ''), $stdout);
        $this->assertSame('status=404', $stderr);
        $this->assertSame(1, $exitCode);
    }

    //endregion
    //region orDie() exit path

    /**
     * @return array<string, array{string}>
     */
    public static function scriptModeProvider(): array
    {
        return [
            'raw'  => ['raw'],
            'html' => ['html'],
        ];
    }

    #[DataProvider('scriptModeProvider')]
    public function testOrDieEchoesEncodedMessageAndExitsWithStatus1(string $scriptMode): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('empty-guard.php', 'orDie', "<b>O'Brien</b> & \"co\"", $scriptMode);

        $this->assertSame('&lt;b&gt;O&apos;Brien&lt;/b&gt; &amp; &quot;co&quot;', $stdout, 'message only, no HTML shell and no trailing newline');
        $this->assertSame('status=false', $stderr, 'no HTTP status is set, and the guard exited (no NOT-REACHED)');
        $this->assertSame(1, $exitCode, 'exit 1 so shell scripts and cron jobs see the failure');
    }

    //endregion
    //region orRedirect() exit path

    public function testOrRedirectSets302AndExitsWithoutOutput(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('empty-guard.php', 'orRedirect', '/login?a=1&b=2', 'raw');

        $this->assertSame('', $stdout, 'a redirect writes no body');
        $this->assertSame('status=302', $stderr, 'status set to 302 and the guard exited (no NOT-REACHED)');
        $this->assertSame(0, $exitCode, 'orRedirect() exits with the default status 0');
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
