<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Support;

use Exception;

/**
 * Thrown by self::exit() instead of exiting while the test suite runs.
 *
 * tests/bootstrap.php loads this file, and self::exit() throws this whenever the class is loaded, so the
 * test gets the output and status and PHPUnit keeps running. tests/ is never shipped, so nothing outside
 * the suite can turn this on. Extends Exception, not RuntimeException, so a catch (RuntimeException) in
 * the code under test never swallows it.
 *
 *     try {
 *         SmartArray::new([])->orDie('Gone');
 *     } catch (ExitCalled $e) {
 *         $this->assertSame(1, $e->status);   // exit(1) sets the status and prints nothing
 *     }
 *
 * Copy-in file: SmartArray and SmartString carry it byte-identical except the namespace line.
 */
final class ExitCalled extends Exception
{
    public readonly string $output;   // what exit would have printed: the string argument, or '' for an int
    public readonly int    $status;   // the process exit status: the int argument, or 0 for a string

    public function __construct(string|int $status)
    {
        $this->output = is_string($status) ? $status : '';
        $this->status = is_int($status) ? $status : 0;
        parent::__construct("self::exit() called with status $this->status" . ($this->output === '' ? '' : ": $this->output"));
    }
}
