<?php
declare(strict_types=1);

namespace Itools\SmartArray;

use InvalidArgumentException;

/**
 * Same as InvalidArgumentException, but it reports the caller's file and line
 * instead of ours. Throw it when the caller caused the error (bad type, bad
 * argument, wrong context) so the error names the line they need to fix:
 *
 *     Uncaught CallerException: column(): Expected a nested array, but got a flat array
 *     in /var/www/templates/race.php:345    // their code, not SmartArrayBase.php
 *
 * "Caller" means the first file outside this class's directory, so internal
 * calls are skipped: pluck() calling column() still reports the template
 * line that called pluck(). If the whole backtrace is internal, it falls back
 * to reporting the throw line as usual.
 *
 * Catch it as InvalidArgumentException - only the reported location changes.
 * The real throw site is kept in $thrownInFile/$thrownInLine.
 *
 * Not every throw belongs here. orThrow() throws RuntimeException - the
 * caller asked for an exception, it's not a mistake. Removed methods and
 * broken handler contracts throw Error - loud and not caught by accident.
 * The test: if the fix is an edit at the reported caller's line, throw this.
 */
final class CallerException extends InvalidArgumentException
{
    public readonly string $thrownInFile;  // library file that threw, e.g. .../src/SmartArrayBase.php
    public readonly int    $thrownInLine;

    public function __construct(string $message)
    {
        parent::__construct($message);

        // PHP set $file/$line to the throw site when the object was created; save them before overriding
        $this->thrownInFile = $this->file;
        $this->thrownInLine = $this->line;

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (!empty($frame['file']) && dirname($frame['file']) !== __DIR__) {
                $this->file = $frame['file'];
                $this->line = $frame['line'] ?? 0;
                break;
            }
        }
    }
}
