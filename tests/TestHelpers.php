<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartNull;
use Itools\SmartString\SmartString;

/**
 * Helper functions for SmartArray tests
 * These are shared across multiple test files
 */
class TestHelpers
{
    /**
     * Returns test records for use in SmartArray tests
     */
    public static function getTestRecords(): array
    {
        return [
            [
                'html'    => "<img src='' alt='\"'>",
                'int'     => 7,
                'float'   => 5.7,
                'string'  => '&nbsp;',
                'bool'    => true,
                'null'    => null,
                'isFirst' => 'C',  // intentionally named after internal private property to detect any conflicts
            ],
            [
                'html'    => '<p>"It\'s"</p>',
                'int'     => 0,
                'float'   => 1.23,
                'string'  => '"green"',
                'bool'    => false,
                'null'    => null,
                'isFirst' => 'Q',
            ],
            [
                'html'    => "<hr class='line'>",
                'int'     => 1,
                'float'   => -16.7,
                'string'  => '<blue>',
                'bool'    => false,
                'null'    => null,
                'isFirst' => 'K',
            ],
        ];
    }

    /**
     * Returns a single test record
     */
    public static function getTestRecord(): array
    {
        return self::getTestRecords()[1];
    }

    /**
     * Normalize array values for comparison
     * Returns the raw value from any element returned by SmartArray
     */
    public static function normalizeArray($var): float|array|bool|int|string|null
    {
        return match (true) {
            is_scalar($var), is_null($var) => $var,
            $var instanceof SmartArray     => $var->toArray(),
            $var instanceof SmartNull      => null,
            default                        => __FUNCTION__ . "() Unexpected value type: " . get_debug_type($var),
        };
    }
    
    /**
     * Return raw value from any element returned by SmartArray::new() - includes scalar|null but not SmartStrings
     */
    public static function normalizeRaw($var): float|array|bool|int|string|null
    {
        return match (true) {
            is_scalar($var), is_null($var) => $var,
            $var instanceof SmartArray     => $var->toArray(),
            $var instanceof SmartNull      => null,
            default                        => __FUNCTION__ . "() Unexpected value type: " . get_debug_type($var),
        };
    }

    /**
     * Return strings html encoded, and everything else as raw value from any element
     * returned by SmartArray::new()->withSmartStrings() - includes SmartStrings but not scalar|null
     */
    public static function normalizeSS($var): float|array|bool|int|string|null
    {
        $isSmartString       = $var instanceof SmartString;
        $isSmartStringString = $var instanceof SmartString && is_string($var->value());
        return match (true) {
            $isSmartStringString       => $var->__toString(),            // Call __toString() on SmartString strings
            $isSmartString             => $var->value(),                 // Call value() on non-string SmartStrings
            $var instanceof SmartArray  => self::toArrayResolveSS($var), // Call toArrayResolveSS() on SmartArrays
            $var instanceof SmartNull   => null,                         // Convert SmartNull to null
            is_scalar($var), is_null($var) => $var,                      // Return scalars and null as-is
            default                    => __FUNCTION__ . "() Unexpected value type: " . get_debug_type($var),
        };
    }

    /**
     * Get array with strings as html and everything else as original value (recursive)
     */
    public static function toArrayResolveSS($smartArray): array
    {
        $result = [];
        
        // Handle ArrayObject/SmartArray with getIterator to ensure SmartStrings get converted
        if ($smartArray instanceof \ArrayObject) {
            foreach ($smartArray->getIterator() as $key => $value) {
                $result[$key] = self::normalizeSS($value);
            }
        } else {
            // Handle regular array
            foreach ($smartArray as $key => $value) {
                $result[$key] = self::normalizeSS($value);
            }
        }
        
        return $result;
    }
    
    /**
     * Recursively HTML encode values in an array
     */
    public static function recursiveHtmlEncode(mixed $var): mixed 
    {
        // Error checking
        if (is_object($var)) {
            throw new InvalidArgumentException("Unexpected object type: " . get_debug_type($var));
        }

        // Recurse over nested arrays
        if (is_array($var)) {
            foreach ($var as $key => $value) {
                $var[$key] = self::recursiveHtmlEncode($value);
            }
            return $var;
        }

        // encode values
        if (is_string($var)) {
            $var = htmlspecialchars($var, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        return $var;
    }
}