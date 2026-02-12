<?php
declare(strict_types=1);

namespace Itools\SmartArray;

use InvalidArgumentException;
use Iterator, ArrayAccess, Countable;
use Itools\SmartString\SmartString;
use JsonSerializable;
use RuntimeException;
use stdClass;

/**
 * SmartNull - Chainable null object for missing elements.
 *
 * Implements SmartBase so instanceof SmartBase works for all Smart* types.
 * Extends stdClass to avoid IDE warnings related to undefined properties.
 */
class SmartNull extends stdClass implements SmartBase, Iterator, ArrayAccess, JsonSerializable, Countable
{
    //region Creation and Conversion

    public function __construct(array $properties = [])
    {
        // Set properties
        foreach ($properties as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
    }

    /**
     * Convert SmartNull to an empty SmartArray, preserving internal properties.
     *
     * Use this when you need a typed SmartArray for IDE autocompletion or to access
     * metadata like mysqli() on a potentially empty result. Returns raw PHP values
     * on property access (strings, ints, etc.).
     *
     *     $record = DB::get('users', ['num' => $id])->first()->asRaw();
     *     $record->name;      // Returns string or null (via SmartNull chaining)
     *     $record->mysqli();  // Access query metadata even if no results
     *
     * @return SmartArray An empty SmartArray with preserved internal properties
     */
    public function asRaw(): SmartArray
    {
        return new SmartArray([], $this->getInternalProperties());
    }

    /**
     * Convert SmartNull to an empty SmartArrayHtml, preserving internal properties.
     *
     * Use this when you need a typed SmartArrayHtml for IDE autocompletion or to access
     * metadata like mysqli() on a potentially empty result. Returns HTML-safe SmartString
     * objects on property access.
     *
     *     $record = DB::get('users', ['num' => $id])->first()->asHtml();
     *     $record->name;      // Returns SmartString or SmartNull (safe for output)
     *     $record->mysqli();  // Access query metadata even if no results
     *
     * @return SmartArrayHtml An empty SmartArrayHtml with preserved internal properties
     */
    public function asHtml(): SmartArrayHtml
    {
        return new SmartArrayHtml([], $this->getInternalProperties());
    }

    /**
     * Return the underlying value (always null for SmartNull).
     *
     * @return null
     */
    public function value(): mixed
    {
        return null;
    }

    //endregion
    //region Debugging and Help

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

    //endregion
    //region Iterator Methods

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

    //endregion
    //region Countable Methods

    /**
     * Always returns 0 since SmartNull is effectively an empty collection
     *
     * @return int
     */
    public function count(): int
    {
        return 0;
    }

    //endregion
    //region ArrayAccess Methods

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

    //endregion
    //region Database Operations

    /**
     * Get mysqli result information for the last database query.
     * Returns specified property (affected_rows, insert_id) or array of all properties if no property specified.
     */
    public function mysqli(?string $property = null): int|string|null|array
    {
        if (is_null($property)) {
            return $this->mysqli;
        }

        return $this->mysqli[$property] ?? null;
    }

    //endregion
    //region Object Methods

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
        return match (true) {
            method_exists(SmartArrayBase::class, $name) => $this->useSmartStrings
                ? SmartArrayHtml::new()->$name(...$arguments)
                : SmartArray::new()->$name(...$arguments),
            method_exists(SmartString::class, $name)    => SmartString::new(null)->$name(...$arguments),
            default                                     => throw new InvalidArgumentException("Method '$name' not found"),
        };
    }

    public function __toString(): string
    {
        return SmartString::new(null)->__toString();
    }

    /**
     * Implement JsonSerializable interface
     */
    public function jsonSerialize(): ?string
    {
        return null;
    }

    //endregion
    //region Internal Properties

    private bool              $useSmartStrings = false;  // Determines which SmartArray type to create when delegating method calls
    private array             $mysqli          = [];     // Metadata from last mysqli result, accessed via mysqli() method
    private mixed             $loadHandler     = null;   // Callback for lazy-loading related data
    private ?SmartArrayBase   $root            = null;   // Reference to root SmartArray (for nested arrays)

    /**
     * Get internal properties for passing to SmartArray/SmartArrayHtml constructors.
     */
    private function getInternalProperties(): array
    {
        return [
            'loadHandler' => $this->loadHandler,
            'mysqli'      => $this->mysqli,
            'root'        => $this->root,
        ];
    }

    //endregion
}
