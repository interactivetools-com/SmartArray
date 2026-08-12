<?php
declare(strict_types=1);

namespace Itools\SmartArray;

// import built-ins so calls resolve at compile time instead of per-call lookups; NamespacedCallsTest keeps this list exact
use function array_column, array_map, basename, debug_backtrace, dirname, headers_list, implode, in_array, preg_match, str_ireplace, str_replace, trait_exists, trigger_error, trim;

/**
 * SharedHelpers - common functions used across our libraries.
 *
 * Twin file: SmartString and SmartArray each have an identical copy, only the
 * namespace line differs. Edit one copy, then sync the others.
 *
 * This stays a per-library copy instead of a shared dependency because
 * getExternalCaller() defines "library internals" as "files in this file's
 * directory" (__DIR__ resolves to where the trait is defined), so the file
 * must sit inside each library's own src folder to report callers correctly.
 */
trait SharedHelpers
{

    /**
     * Logs a deprecation notice via trigger_error() with the calling file and line number.
     *
     * The @ suppressor mutes PHP's default display and logging. Only a custom error
     * handler (set_error_handler) receives these notices; without one, nothing is
     * shown or logged. This is deliberate: deprecation notices are meant for error
     * handlers that collect them, never for page output.
     */
    protected static function logDeprecation(string $message): void
    {
        $caller   = self::getExternalCaller();
        $message .= " in {$caller['file']}:{$caller['line']}.";
        @trigger_error($message, E_USER_DEPRECATED);
    }

    /**
     * Find the first caller outside the libraries' own directories.
     *
     * Walks the debug backtrace to find the first frame that isn't in
     * SmartString's or SmartArray's src directory, giving us the actual calling
     * code location.
     * 'method' is the caller's enclosing function or class method, or '' at the top level.
     *
     * 'file' is a basename only: these strings reach page output, so they never
     * carry full paths. Stack traces and logs report full paths on their own.
     *
     * @return array{file: string, line: int|string, function: string, method: string}
     */
    private static function getExternalCaller(): array
    {
        // Both libraries count as internal: they delegate to each other (SmartArray's
        // SmartNull forwards method calls to SmartString), so the first frame outside
        // this file's directory can be the other library's file, not the developer's
        // code. Each library keeps its copy of this trait in its own src directory
        // (see the class docblock) and every class that can appear in a backtrace
        // uses it, so the loaded traits map out both directories. trait_exists(...,
        // false) never autoloads - a library that isn't loaded can't be in the
        // backtrace anyway.
        $internalDirs = [];
        foreach ([\Itools\SmartString\SharedHelpers::class, \Itools\SmartArray\SharedHelpers::class] as $sharedHelpers) {
            if (trait_exists($sharedHelpers, false)) {
                $internalDirs[] = dirname((new \ReflectionClass($sharedHelpers))->getFileName());
            }
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtrace as $index => $caller) {
            if (!empty($caller['file']) && !in_array(dirname($caller['file']), $internalDirs, true)) {
                $nextFrame = $backtrace[$index + 1] ?? [];
                return [
                    'file'     => basename($caller['file']),
                    'line'     => $caller['line'] ?? "unknown",
                    'function' => $caller['function'] ?? "unknown",
                    'method'   => ($nextFrame['class'] ?? '') . ($nextFrame['type'] ?? '') . ($nextFrame['function'] ?? ''),
                ];
            }
        }
        return ['file' => "unknown", 'line' => "unknown", 'function' => "unknown", 'method' => ''];
    }

    /**
     * Format "Occurred in file:line" string from the backtrace.
     *
     * Finds the first caller outside the library and optionally includes
     * the "Reported in" line showing the internal method that generated the error.
     *
     * @param bool $addReportedFileLine Include "Reported in file:line in method()" detail
     */
    private static function occurredInFile(bool $addReportedFileLine = false): string
    {
        $caller   = self::getExternalCaller();
        $inMethod = $caller['method'] !== '' ? " in {$caller['method']}()" : "";
        $output   = "Occurred in {$caller['file']}:{$caller['line']}$inMethod\nReported"; // "Reported" is a prefix - trigger_error() appends " in file on line X"

        // Add Reported in file:line (if requested)
        if ($addReportedFileLine) {
            $backtrace    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $class        = $backtrace[1]['class'] ?? '';
            $shortClass   = $class ? self::stripNamespace($class) : '';
            $method       = $shortClass . ($backtrace[1]['type'] ?? '') . ($backtrace[1]['function'] ?? '');
            $reportedFile = basename($backtrace[0]['file'] ?? "unknown");
            $reportedLine = $backtrace[0]['line'] ?? "unknown";
            $output       .= " in $reportedFile:$reportedLine in $method()\n";
        }

        return $output;
    }

    /**
     * Strip the namespace prefix from a class or type name. No-op when there's no '\\'.
     *
     *     self::stripNamespace('Vendor\Package\ClassName'); // 'ClassName'
     *     self::stripNamespace('ClassName');                // 'ClassName'
     *     self::stripNamespace('string');                   // 'string'
     *
     * basename() on its own only splits on '/' on Linux, so calling it on a PHP
     * class name leaves the backslashes in place. Normalize '\\' to '/' first so
     * the result comes back consistently on any platform.
     */
    protected static function stripNamespace(string $typeOrClass): string
    {
        return basename(str_replace('\\', '/', $typeOrClass));
    }

    /**
     * Wrap output in <xmp> tag if text/html and not called from a function that already added <xmp>
     */
    private static function xmpWrap(string $output): string
    {
        $output = trim($output, "\n");
        $plain  = "\n$output\n";

        // terminals show <xmp> literally; CGI builds misreport SAPI on some hosts, so check more than PHP_SAPI
        $inCli = PHP_SAPI === 'cli'
                 || ($_SERVER['SESSIONNAME'] ?? '') === 'Console' // Windows console
                 || empty($_SERVER['SCRIPT_NAME']);               // only web servers set SCRIPT_NAME
        if ($inCli) {
            return $plain;
        }

        // non-HTML responses (json, plain text, etc.) stay unwrapped
        $headersList    = implode("\n", headers_list());
        $hasContentType = (bool)preg_match('|^\s*Content-Type:\s*|im', $headersList);                          // assume no content type will default to html
        $isTextHtml     = !$hasContentType || preg_match('|^\s*Content-Type:\s*text/html\b|im', $headersList); // match: text/html or ...;charset=utf-8
        if (!$isTextHtml) {
            return $plain;
        }

        // showme() debug helper adds its own <xmp>
        $backtraceFunctions = array_map('strtolower', array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'function'));
        if (in_array('showme', $backtraceFunctions, true)) {
            return $plain;
        }

        // escape "</xmp" so output can't break out of the block, same as CMSB's xmp_safe()
        return "\n<xmp>\n" . str_ireplace('</xmp', '<\/xmp', $output) . "\n</xmp>\n";
    }

}
