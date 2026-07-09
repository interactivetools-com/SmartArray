<?php
declare(strict_types=1);

/**
 * Subprocess target for EmptyGuardsTest: runs one or*() guard on an empty
 * SmartArray so exit paths can be observed from outside the process.
 *
 *     php empty-guard.php <method> [message-or-url]
 *
 * stdout: whatever the guard echoes (404 page, die message)
 * stderr: "status=<int|false>" from a shutdown handler (http_response_code
 *         survives exit within the process), plus "NOT-REACHED" if the guard
 *         failed to exit
 *
 * CLI limits: header() is a no-op and headers_list() is always empty under
 * CLI, so the Location and Content-Type headers can't be asserted here, only
 * status codes, output, and exit behavior.
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Itools\SmartArray\SmartArray;

register_shutdown_function(function () {
    fwrite(STDERR, "status=" . var_export(http_response_code(), true));
});

$method = $argv[1] ?? '';
$arg    = $argv[2] ?? null;
$empty  = SmartArray::new([]);

match ($method) {
    'or404-default' => $empty->or404(),
    'or404'         => $empty->or404((string) $arg),
    'orDie'         => $empty->orDie((string) $arg),
    'orThrow'       => $empty->orThrow((string) $arg),
    'orRedirect'    => $empty->orRedirect((string) $arg),
    default         => fwrite(STDERR, "unknown method: $method"),
};

fwrite(STDERR, "NOT-REACHED"); // every guard above should exit or throw on an empty array
