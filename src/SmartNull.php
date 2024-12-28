<?php
declare(strict_types=1);

namespace Itools\SmartArray;

use Iterator, ArrayAccess;
use RuntimeException, InvalidArgumentException;
use Itools\SmartString\SmartString;

/**
 * NullSmartArray - A SmartArray|SmartString object that can be used as a placeholder for null values.
 */
class SmartNull implements Iterator, ArrayAccess
{
    #region Constructor

    public function __construct(array $properties = [])
    {
        // Set properties
        foreach ($properties as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
    }

    #endregion
    #region Debugging and Help

    /**
     * Displays help information about this object
     *
     * @return void
     */
    public function help(): void
    {
        $output = <<<'__TEXT__'
        SmartNull - Chainable Null Object for Missing Elements
        ===================================================
        SmartNull is returned when accessing non-existent elements where the type
        (SmartArray or SmartString) is ambiguous.

        It implements both SmartArray and SmartString interfaces. When methods
        are called, it delegates to either a new empty SmartArray or a null
        SmartString as appropriate. This allows unlimited method chaining
        without null checks, returning appropriate empty/null values when
        the final result is accessed.
        __TEXT__;

        $isHtmlOutput = stripos(implode("\n", headers_list()), 'text/html') !== false;
        if ($isHtmlOutput) {
            $output = "<xmp>$output</xmp>";
        }
        echo $output;
    }

    #endregion
    #region Iterator Methods

    public function current(): mixed
    {
        return null;
    }

    public function next(): void
    {
        // Needed for Iterator interface, but never called because valid() always returns false
    }

    public function key(): mixed
    {
        // Needed for Iterator interface, but never called
        return null;
    }

    public function valid(): bool
    {
        return false; // Always false to ensure no iteration
    }

    public function rewind(): void
    {
        // Needed for Iterator interface, but never called
    }

    #endregion
    #region ArrayAccess Methods

    public function offsetGet($offset): SmartNull
    {
        return $this;
    }

    public function offsetSet($offset, $value): void
    {
        throw new RuntimeException('Cannot set values on SmartNull');
    }

    public function offsetExists($offset): bool
    {
        return false;
    }

    public function offsetUnset($offset): void
    {
        // Needed for Iterator interface, but never called
    }

    #endregion
    #region Database Operations

    /**
     * Get mysqli result information for the last database query.
     * Returns specified property (affected_rows, insert_id) or array of all properties if no property specified.
     */
    public function mysqli(?string $property = null): int|string|null|array
    {
        // return array of all mysqli properties

        if (is_null($property)) {
            return get_object_vars($this)['mysqli'] ?? [];
        }

        // return specific mysqli property
        return get_object_vars($this)['mysqli'][$property] ?? null;
    }

    #endregion
    #region Object Methods

    /**
     * Emulate property access for SmartArray and SmartString.
     *
     * @param string $name
     * @return $this
     * @noinspection MagicMethodsValidityInspection
     */
    public function __get(string $name): SmartNull
    {
        return $this;
    }

    /**
     * Emulate response methods for SmartArray and SmartString.
     *
     * Since when we access a non-existent element we don't know if we were expecting a SmartArray or SmartString,
     * we return this object that can handle both
     *
     * @param $name
     * @param mixed ...$arguments
     * @return array|false|float|int|SmartString|string|null
     */
    public function __call($name, array $arguments): mixed
    {
        $newMethod = $this->useSmartStrings ? 'newSS' : 'new';
        return match (true) {
            method_exists(SmartArray::class, $name)  => SmartArray::$newMethod([])->$name(...$arguments),
            method_exists(SmartString::class, $name) => SmartString::new(null)->$name(...$arguments),
            default                                  => throw new InvalidArgumentException("Method '$name' not found"),
        };
    }

    public function __toString(): string
    {
        return SmartString::new(null)->__toString();
    }

    #endregion
    #region Internal Properties

    private bool  $useSmartStrings;          // Is this ArrayObject a nested array?
    private array $mysqli = [];              // NOSONAR Metadata from last mysqli result, e.g. $result->mysqli('affected_rows')

    #endregion
}
