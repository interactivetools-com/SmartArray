<?php
declare(strict_types=1);

namespace Itools\SmartArray;

/**
 * Shared error helper methods for SmartArray and SmartString libraries.
 *
 * Provides backtrace utilities for identifying where errors originated in calling code,
 * outside the library's own source files. Used for deprecation notices, warnings, and
 * error messages that need to point to the caller's file and line number.
 *
 * This trait is intentionally duplicated across libraries to avoid cross-library dependencies.
 */
trait ErrorHelpersTrait
{

    /**
     * Logs a deprecation notice via trigger_error() with the calling file and line number.
     *
     * The @ suppressor prevents direct output while still allowing custom error handlers
     * and PHP's built-in error logging to capture the notice.
     */
    protected static function logDeprecation(string $message): void
    {
        $caller   = self::getExternalCaller();
        $message .= " in {$caller['file']}:{$caller['line']}.";
        @trigger_error($message, E_USER_DEPRECATED);
    }

    /**
     * Find the first caller outside the library's own directory.
     *
     * Walks the debug backtrace to find the first frame that isn't in the same
     * directory as the current file, giving us the actual calling code location.
     *
     * @return array{file: string, line: string, function: string}
     */
    private static function getExternalCaller(): array
    {
        $libraryDir = dirname(__FILE__);
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $caller) {
            if (!empty($caller['file']) && dirname($caller['file']) !== $libraryDir) {
                return [
                    'file'     => basename($caller['file']),
                    'line'     => $caller['line'] ?? "unknown",
                    'function' => $caller['function'] ?? "unknown",
                ];
            }
        }
        return ['file' => "unknown", 'line' => "unknown", 'function' => "unknown"];
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
        $file      = "unknown";
        $line      = "unknown";
        $inMethod  = "";
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Find first caller outside this library (same dirname logic as getExternalCaller)
        $libraryDir = dirname(__FILE__);
        foreach ($backtrace as $index => $caller) {
            if (!empty($caller['file']) && dirname($caller['file']) !== $libraryDir) {
                $file       = $caller['file'];
                $line       = $caller['line'] ?? $line;
                $prevCaller = $backtrace[$index + 1] ?? [];
                $inMethod   = match (true) {
                    !empty($prevCaller['class'])    => " in {$prevCaller['class']}{$prevCaller['type']}{$prevCaller['function']}()",
                    !empty($prevCaller['function']) => " in {$prevCaller['function']}()",
                    default                         => "",
                };
                break;
            }
        }
        $output = "Occurred in $file:$line$inMethod\nReported"; // "Reported" is a prefix - trigger_error() appends " in file on line X"

        // Add Reported in file:line (if requested)
        if ($addReportedFileLine && isset($backtrace[0], $backtrace[1])) {
            $class        = $backtrace[1]['class'] ?? '';
            $shortClass   = $class ? (substr(strrchr($class, '\\'), 1) ?: $class) : '';
            $method       = $shortClass . ($backtrace[1]['type'] ?? '') . ($backtrace[1]['function'] ?? '');
            $reportedFile = $backtrace[0]['file'] ?? "unknown";
            $reportedLine = $backtrace[0]['line'] ?? "unknown";
            $output       .= " in $reportedFile:$reportedLine in $method()\n";
        }

        return $output;
    }

}
