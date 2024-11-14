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
            SmartArray: Enhanced Arrays with Automatic HTML Encoding and Chainable Methods
            ==========================================================================================
            SmartArray extends PHP arrays with automatic HTML encoding and chainable utility methods. 
            It preserves familiar array syntax while adding powerful features for filtering, mapping,
            and data manipulation - making common array operations simpler, safer, and more expressive.

            Core Concepts
            -------------------             
            $arr                = Itools\SmartArray\SmartArray    // Arrays become SmartArray objects (even nested arrays)
            $arr['columnName']  = Itools\SmartString\SmartString  // Values become SmartString objects with HTML-encoded output
            $arr->columnName    = Itools\SmartString\SmartString  // Optional object syntax makes code cleaner and more readable
            
            Accessing Elements
            -------------------             
            foreach ($users as $user) {          // Foreach over a SmartArray just like a regular array
                echo "Name: $user->name\n";      // SmartString output is automatically HTML-encoded, no need for htmlspecialchars()
                
                // For more complex expressions, curly braces are still required
                echo "Bio: {$user->bio->textOnly()->maxChars(120, '...')}\n";  // Chain SmartString methods on column values 
            }

            Original Values
            -------------------
            $arr->toArray()                      // Get original array with raw values
            $arr->columnName->value()            // Get original unencoded field value  
            "Bio: {$user->wysiwyg->noEncode()}"  // Alias for value(), clearer when handling WYSIWYG/HTML content

            Creating SmartArrays
            -------------------
            $ids   = SmartArray::new([1, 2, 3]);
            $user  = SmartArray::new(['name' => 'John', 'age' => 30]);
            $users = SmartArray::new(DB::select('users'));  // Nested SmartArray of SmartStrings

            Array Information
            ------------------
            ->count() or count($arr)   Number of elements
            ->isEmpty()                Check if empty
            ->isNotEmpty()             Check if not empty
            ->isFirst()                Check if first element in parent
            ->isLast()                 Check if last element in parent
            ->position()               Get position in parent (1-based)
            ->isMultipleOf($value)     Check if position is a multiple of $value, for creating grids

            Value Access
            -------------
            $arr[$key]                 Array access syntax
            $arr->key                  Object property syntax
            ->get($key)                Get element by key
            ->first()                  Get first element
            ->last()                   Get last element
            ->nth($position)           Get element by position (1 is first element, -1 is last element)

            Array Transformation
            ---------------------
            ->toArray()                Recursively convert SmartArray and SmartStrings back to a standard PHP array.
            ->keys()                   New SmartArray of keys
            ->values()                 New SmartArray of values
            ->unique()                 New SmartArray with unique values (removes duplicates, preserves keys)
            ->sort()                   New SmartArray sorted by values
            ->sortBy($column)          New SmartArray sorted by column
            ->indexBy($column)         New SmartArray indexed by column
            ->groupBy($column)         Group by column (list of rows for each key)
            ->implode($separator)      Join elements into a string
            ->map($callback)           Apply callback to each element
            ->pluck($column)           Extract single column from nested array
            ->chunk($size)             Split into smaller SmartArrays

            Debugging
            ----------
            print_r($arr)              Show array values and debug information
            $arr->help()               Display this help information

            For more details see SmartArray readme.md, and SmartString docs for chainable string methods. 
            __TEXT__;

        return self::xmpWrap($output);
    }

    /**
     * Show data and debug information when print_r() is used to examine object.
     *
     * @return array An associative array containing debugging information.
     */
    public static function debugInfo(SmartArray $smartArray): array
    {
        $indent = str_repeat(" ", 4);

        // intro
        $data = match($smartArray->metadata()->_useSmartStrings ?? false) {
            true  => "// Arrays are SmartArrays, values are SmartStrings, call ->help() for info\n\n",
            false => "// Arrays are SmartArrays, values are raw PHP types, call ->help() for info\n\n",
        };

        // metadata
        $metadata     = (array) $smartArray->metadata();
        if (!empty($metadata)) {
            $data         .= "// Metadata: Access with ->metadata()->key\n";
            $maxKeyLength = max(array_map('strlen', array_keys($metadata)));
            foreach ($metadata as $key => $value) {
                $data .= sprintf("$indent->%-{$maxKeyLength}s = %s\n", $key, self::getPrettyVarValue($value));
            }
            $data .= "\n";
        }

        // var dump
        $data      .= self::xmpWrap(self::prettyPrintR($smartArray));

        // format data
        $data = preg_replace("/^/m", $indent, $data);      // indent each line
        $data = preg_replace("/^\s+$/m", "", $data);       // remove empty lines
        $data = preg_replace("/ +$/m", "", $data);        // remove trailing whitespace

        // return data
        return ['__DEBUG_INFO__' => trim($data)];
    }

    #endregion
    #region prettyPrintR

    /**
     * Generates human-readable output for a variable.  More compact and specific than print_r().
     *
     * @param $var
     * @param int $indent
     * @param bool $skipInitialIndent
     * @param string $comment
     * @return string
     */
    public static function prettyPrintR($var, int $indent = 0, bool $skipInitialIndent = false, string $comment = ""): string
    {
        $padding      = str_repeat("    ", $indent);
        $childPadding = str_repeat("    ", $indent + 1);
        $output       = "";

        if ($var instanceof SmartArray) {
            $listValues     = self::getListValues($var, $childPadding, $indent);
            $initialPadding = $skipInitialIndent ? "" : $padding;
            $output         .= sprintf("%-19s\n", "{$initialPadding}["); //    // " . self::getProperties($var)
            //$output         .= sprintf("%-19s // Properties: %s\n", "{$initialPadding}[", self::getProperties($var));
            $output         .= "$listValues$padding],";
        } elseif (is_array($var)) {
            $listValues = self::getListValues($var, $childPadding, $indent);
            $output     .= "{$padding}array(\n$listValues$padding),";
        } else {
            $varValue      = $var instanceof SmartString ? $var->value() : $var;
            $displayValue  = self::getPrettyVarValue($varValue);
            $baseDebugType = basename(get_debug_type($var)); // e.g., "SmartString", "SmartNull", "string", "int", "float", "bool", "null"
            $comment = $comment ? " // $comment" : "";
            $output        = match (basename(get_debug_type($var))) {
                'SmartString' => "$displayValue,$comment",
                'SmartNull'   => "SmartNull(),$comment",
                default       => "$displayValue,$comment", // $baseDebugType
            };
        }

        return "$output\n";
    }

    /**
     * @param SmartArray|array $var
     * @return string
     */
    private static function getProperties(SmartArray|array $var): string {

        if (!$var instanceof SmartArray) {
            return "";
        }

        // get properties
        $properties = [];
        $reflection = new ReflectionObject($var);
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);                                // NOSONAR - ignore SonarLint false-positive warning about accessibility bypass

            $propertyName = $property->getName();
            if ($propertyName != 'parent') {
                $properties[$propertyName] = $property->getValue($var); // NOSONAR - ignore SonarLint false-positive warning about accessibility bypass
            }
        }

        // format properties
        $output = "";
        foreach ($properties as $key => $value) {
            if (in_array($key, ['metadata','parent'])) { // displayed separately
                continue;
            }
            $varExport = match(true) {
                is_array($value) => "[" .implode(", ", array_map(static fn($k,$v) => "$k => " . var_export($v, true), array_keys($value), $value)) . "]",
                default          => var_export($value, true),
            };
            $output .= sprintf("$key: %s, ", $varExport);
        }
        $output = str_replace("true, ", "true,  ", $output); // align boolean values
        return rtrim($output, ", ");
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
        $maxKeyLength = max(array_map('strlen', $keys)) + 2;
        $listValues   = "";
        foreach ($iterator as $key => $value) {
            $listValues .= match(true) {
                is_int($key) => sprintf("$padding%-{$maxKeyLength}s => ", "[$key]"),
                default      => sprintf("$padding%-{$maxKeyLength}s => ", "'$key'"),
            };

            // add load comment
            $loadResult = false;
            if (is_string($key)) {
                try {
                    $loadResult = $iterator->load($key);
                } catch (\Throwable) {
                }
            }
            $comment = $loadResult ? "load() for more" : "";

            // add list values
            $listValues .= self::prettyPrintR($iterator[$key], $indent + 1, true, $comment);
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
        $hasTabs = is_string($value) && str_contains($value, "\t");
        return match (true) {
            $hasTabs => '"' . addcslashes($value, "\t\"\0\$\\") . '"',  // Show tabs as \t for readability
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
            $output = "\n<xmp>\n".trim($output, "\n")."\n</xmp>\n";
        }
        else {
            $output = "\n".trim($output, "\n")."\n";
        }

        return $output;
    }

    #endregion
}
