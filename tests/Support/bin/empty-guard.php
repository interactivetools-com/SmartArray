<?php
declare(strict_types=1);

/**
 * Subprocess target for EmptyGuardsTest: runs or404() on an empty array so the
 * exit path can be observed from outside the process. or404() discards every
 * open output buffer, PHPUnit's capture buffer included, so it can't run
 * in-process.
 *
 *     php empty-guard.php <raw|html> <method> [message]
 *
 * The handler-* methods set a set404Handler() handler first. It prints what it
 * saw instead of a page:
 *
 *     handler-text=<var_export of the message>
 *     handler-status=<http_response_code() when it ran>
 *     handler-ob-level=<ob_get_level() when it ran>
 *
 * handler-first also sets a SmartString handler that prints
 * "smartstring-handler-text=<var_export of the message>", to show which
 * library's handler ran.
 *
 * stdout: whatever the guard echoes (the 404 page or the handler's report)
 * stderr: "status=<int|false>" from a shutdown handler (http_response_code
 *         survives exit within the process), plus "NOT-REACHED" if the guard
 *         didn't exit
 *
 * header() is a no-op under CLI, so the Content-Type can't be observed here.
 * headers_sent() DOES work under CLI (true after any output) - the
 * headers-sent variant relies on that.
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;

register_shutdown_function(function () {
    fwrite(STDERR, "status=" . var_export(http_response_code(), true));
});

$empty = match ($argv[1] ?? '') {  // an unknown mode raises UnhandledMatchError
    'raw'  => SmartArray::new([]),
    'html' => SmartArrayHtml::new([]),
};
$method = $argv[2] ?? '';
$arg    = $argv[3] ?? '';

if (str_starts_with($method, 'handler')) {
    SmartArray::set404Handler(function (?string $text): void {
        echo "handler-text=" . var_export($text, true) . "\n";
        echo "handler-status=" . var_export(http_response_code(), true) . "\n";
        echo "handler-ob-level=" . ob_get_level() . "\n";
    });
}

$run = match ($method) {
    'or404-default'       => fn() => $empty->or404(),
    'or404'               => fn() => $empty->or404($arg),
    'or404-smart-text'    => fn() => $empty->or404(SmartString::new($arg)), // unwrapped first, so the page encodes it once
    'or404-smartnull'     => fn() => $empty->or404(SmartArray::new([])->first()),
    'or404-headers-sent'  => function () use ($empty) {
        echo "already-flushed\n"; // makes headers_sent() true before the call
        $empty->or404();          // page still renders; the status can't change
    },
    'or404-ob-discard'    => function () use ($empty) {
        ob_start();
        echo "partial page content"; // buffered, not sent: headers_sent() stays false
        $empty->or404();             // discards the buffer and sets the 404
    },
    'or404-locked-buffer' => function () use ($empty) {
        // a buffer PHP can't remove: or404() must stop discarding, not spin on it.
        // The time limit turns a spin regression into a fast fatal, not a hung test run
        set_time_limit(3);
        ob_start(fn(string $s) => $s, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);
        echo "partial page content";
        $empty->or404();
    },
    'handler'               => fn() => $empty->or404($arg),
    'handler-default'       => fn() => $empty->or404(),
    'handler-smart-text'    => fn() => $empty->or404(SmartString::new($arg)),
    'handler-smart-int'     => fn() => $empty->or404(SmartString::new(404)),
    'handler-smartnull'     => fn() => $empty->or404(SmartArray::new([])->first()),
    'handler-first'         => function () use ($empty, $arg) {
        SmartString::set404Handler(fn(?string $text) => print("smartstring-handler-text=" . var_export($text, true) . "\n"));
        $empty->first()->or404($arg); // raw mode runs SmartArray's or404(), HTML mode SmartString's
    },
    'handler-headers-sent'  => function () use ($empty) {
        echo "already-flushed\n";
        $empty->or404();
    },
    'handler-ob-discard'    => function () use ($empty) {
        ob_start();
        echo "partial page content";
        $empty->or404();
    },
    'handler-reset'         => function () use ($empty) {
        SmartArray::set404Handler(null);
        $empty->or404();
    },
    'handler-throws'        => function () use ($empty) {
        SmartArray::set404Handler(fn(?string $text) => throw new LogicException('handler threw'));
        $empty->or404();
    },
    'handler-nested'        => function () use ($empty) {
        SmartArray::set404Handler(function (?string $text): void {
            echo "handler-page\n";
            SmartArray::new([])->or404('nested call'); // prints the built-in page instead of calling this handler again
        });
        $empty->or404();
    },
    default => fn() => fwrite(STDERR, "unknown method: $method"),
};
$run();

fwrite(STDERR, "NOT-REACHED"); // or404() on an empty array should exit
