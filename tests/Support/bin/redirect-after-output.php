<?php
declare(strict_types=1);

/**
 * Subprocess target for EmptyGuardsTest: echoes output first, then calls
 * orRedirect(), so the headers-already-sent fail-fast can be observed.
 * PHPUnit buffers everything a test prints, so headers_sent() stays false
 * in-process and this path is unreachable there.
 *
 *     php redirect-after-output.php <empty|filled>
 *
 * The array state is an argument because the fail-fast is supposed to throw
 * either way, not only when the array is empty.
 *
 * stdout: the marker echoed before the call (the output that "sends headers")
 * stderr: "class=" and "message=" from the caught exception, "output-line="
 *         the line the marker was echoed on (what the message should name),
 *         "status=<int|false>" from a shutdown handler, and "NOT-THROWN" if
 *         orRedirect() returned instead of throwing
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Itools\SmartArray\CallerException;
use Itools\SmartArray\SmartArray;

register_shutdown_function(function () {
    fwrite(STDERR, "status=" . var_export(http_response_code(), true));
});

$data = match ($argv[1] ?? '') {  // an unknown state raises UnhandledMatchError
    'empty'  => [],
    'filled' => ['a'],
};
$array = SmartArray::new($data);

$outputLine = __LINE__ + 1; // headers_sent() reports the line output started on
echo "output-before-redirect";

try {
    $array->orRedirect('/login');
    fwrite(STDERR, "NOT-THROWN\n");
} catch (CallerException $e) {
    fwrite(STDERR, "class=" . $e::class . "\n");
    fwrite(STDERR, "message=" . $e->getMessage() . "\n");
}

fwrite(STDERR, "output-line=$outputLine\n");
