<?php
declare(strict_types=1);

namespace Itools\SmartArray;

use ReflectionObject;
use Itools\SmartString\SmartString;

class DebugInfo
{
    #region Help and Debug Info

    /**
     * Displays help information about available methods and properties.
     *
     * @return string
     */
    public static function help(): string
    {
        $output = <<<'__TEXT__'
            SmartArray: An Enhanced ArrayObject with Fluent Interface
            ========================================================
            SmartArray extends ArrayObject, offering additional features and a chainable interface.
            It supports both flat and nested arrays, automatically converting elements to SmartString or SmartArray.

            Creating SmartArrays:
            ---------------------
            $ids   = new SmartArray([1, 2, 3]);
            $user  = new SmartArray(['name' => 'John', 'age' => 30]);
            $users = new SmartArray(DB::select('users'));  // Nested SmartArray of SmartStrings

            Accessing Elements:
            -------------------
            Array syntax:  $user['name']
            Object syntax: $user->name
            Method syntax: $user->get('name')

            In strings (SmartStrings are automatically HTML-encoded):
            "User: $user->name"

            Accessing original values:
            $user->name->value()  // Returns the original value without HTML encoding

            Basic Usage:
            ------------
            foreach ($users as $user) {
                echo "User: $user->name\n";
            }

            Array Information:
            ------------------
            count() or count($arr)   Number of elements
            isEmpty()                Check if empty
            isNotEmpty()             Check if not empty
            isFirst()                Check if first element in parent
            isLast()                 Check if last element in parent
            position()               Get position in parent (1-based)
            isMultipleOf($value)     Check if position is a multiple of $value, for creating grids

            Value Access:
            -------------
            get($key)               Get element by key
            first()                  Get first element
            last()                   Get last element
            at($position)           Get element by position (1-based, supports negative)
            $arr[$key]              Array access syntax
            $arr->key               Object property syntax

            Array Transformation:
            ---------------------
            toArray()                Recursively convert SmartArray and SmartStrings back to a standard PHP array.
            keys()                   New SmartArray of keys
            values()                 New SmartArray of values
            unique()                 New SmartArray with unique values (removes duplicates, preserves keys)
            sort()                   New SmartArray sorted by values
            sortBy($column)          New SmartArray sorted by column
            indexBy($column)         New SmartArray indexed by column
            groupBy($column)         Group by column (list of rows for each key)
            join($separator)         Join elements into a string
            map($callback)           Apply callback to each element
            pluck($column)           Extract single column from nested array
            chunk($size)             Split into smaller SmartArrays

            Iteration:
            ----------
            foreach ($arr as $key => $value) { ... }

            Debugging:
            ----------
            print_r($arr)            Show values and debug information
            $arr->help()             Display this help information

            For more details, refer to the class documentation.
            __TEXT__;

        return self::xmpWrap($output);
    }

    /**
     * Show data and debug information when print_r() is used to examine object.
     *
     * @return array An associative array containing debugging information.
     */
    public static function debugInfo($smartArray): array
    {
        $introText = "// SmartArray debug view, call \$var->help() for inline help";
        $data      = self::xmpWrap(self::prettyPrintR($smartArray));
        $data      = preg_replace("/^/m", str_repeat(" ", 4), $data);
        return ['__DEBUG_INFO__' => rtrim("$introText\n\n$data")];
    }

    #endregion
    #region prettyPrintR

    /**
     * Generates human-readable output for a variable.  More compact and specific than print_r().
     *
     * @param $var
     * @param int $indent
     * @param bool $skipInitialIndent
     * @return string
     */
    public static function prettyPrintR($var, int $indent = 0, bool $skipInitialIndent = false): string
    {
        $padding      = str_repeat("    ", $indent);
        $childPadding = str_repeat("    ", $indent + 1);
        $output       = "";

        if ($var instanceof SmartArray) {
            $listValues     = self::getListValues($var, $childPadding, $indent);
            $metadata       = self::getSmartArrayMetaData($var);
            $initialPadding = $skipInitialIndent ? "" : $padding;
            $output         .= sprintf("%-19s // Metadata: %s\n", "{$initialPadding}SmartArray([", $metadata);
            $output         .= "$listValues$padding]),";
        } elseif (is_array($var)) {
            $listValues = self::getListValues($var, $childPadding, $indent);
            $output     .= "{$padding}array(\n$listValues$padding),";
        } else {
            $varValue     = $var instanceof SmartString ? $var->value() : $var;
            $displayValue = self::getPrettyVarValue($varValue);
            $output       = match (basename(get_debug_type($var))) {
                'SmartString' => "SmartString($displayValue),",
                'SmartNull'   => "SmartNull(),",
                default       => $displayValue,
            };
        }

        return "$output\n";
    }

    /**
     * @param SmartArray|array $var
     * @return string
     */
    private static function getSmartArrayMetaData(SmartArray|array $var): string {

        if (!$var instanceof SmartArray) {
            return "";
        }

        // get properties
        $properties = [];
        $reflection = new ReflectionObject($var);
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);                                // NOSONAR - ignore SonarLint false-positive warning about accessibility bypass
            $properties[$property->getName()] = $property->getValue($var); // NOSONAR - ignore SonarLint false-positive warning about accessibility bypass
        }

        // format metadata
        $metadata = "";
        foreach ($properties as $key => $value) {
            $metadata .= sprintf("$key: %s, ", var_export($value, true));
        }
        $metadata = str_replace("true, ", "true,  ", $metadata); // align boolean values
        return rtrim($metadata, ", ");
    }


    /**
     * @param SmartArray|array $iterator
     * @param string $padding
     * @param int $indent
     * @return string
     */
    private static function getListValues(SmartArray|array $iterator, string $padding, int $indent): string
    {
        $array        = is_array($iterator) ? $iterator : iterator_to_array($iterator, true);

        // empty arrays
        if (count($array) === 0) {
            return "";
        }

        // non-empty arrays
        $keys         = array_keys($array);
        $maxKeyLength = max(array_map('strlen', $keys));
        $isSequential = $keys === range(0, count($iterator) - 1); // check if array is a list, e.g. keys === [0, 1, 2, 3, ...]
        $listValues   = "";
        foreach ($keys as $key) {
            if (!$isSequential) {
                $listValues .= sprintf("$padding%-{$maxKeyLength}s => ", $key); // only show keys if not sequential
            }

            if ($isSequential && $iterator[$key] instanceof SmartString) {
               $listValues .= $padding;
            }

            $skipInitialIndent = !$isSequential; // skip indent if we're already showing an indented key
            $listValues .= self::prettyPrintR($iterator[$key], $indent + 1, $skipInitialIndent);
        }

        return $listValues;
    }

    /**
     * Return a human-readable version of a variable. E.g., "string", 123, 1.23, TRUE, FALSE, NULL
     *
     * @param $value
     *
     * @return string|int|float
     */
    private static function getPrettyVarValue($value): string|int|float
    {
        return match (basename(get_debug_type($value))) {
            'string' => sprintf('"%s"', $value),               // don't escape quotes in debug output for readability (print_r style)
            'bool'   => strtoupper(var_export($value, true)),
            'null'   => "NULL",
            default  => var_export($value, true),
        };
    }

    #endregion
    #region Utility Methods

    public static function isHtmlOutput(): bool
    {
        $headerLines = implode("\n", headers_list());
        $textHtmlRx  = '|^\s*Content-Type:\s*text/html\b|im'; // Content-Type: text/html or text/html;charset=utf-8
        $isTextHtml  = (bool)preg_match($textHtmlRx, $headerLines);
        return $isTextHtml;
    }

    /**
     * Check if a function is in the call stack.
     *
     * @param $function
     *
     * @return bool
     */
    private static function inCallStack($function): bool
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtrace as $trace) {
            if ($trace['function'] === $function) {
                return true;
            }
        }
        return false;
    }

    /**
     * Wrap output in <xmp> tag if not text/plain or called from a showme() function.
     *
     * @param $output
     *
     * @return string
     */
    public static function xmpWrap($output): string
    {
        // wrap output in <xmp> tag if not text/plain or called from functions the add <xmp> tag
        if (self::isHtmlOutput() && !self::inCallStack('showme')) {
            $output = "\n<xmp>".trim($output, "\n")."</xmp>\n";
        }

        return $output;
    }

    #endregion
}
