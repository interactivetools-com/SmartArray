<?php
/** @noinspection PhpUnnecessaryStaticReferenceInspection */
declare(strict_types=1);
namespace Itools\SmartArray;

use Closure;
use Iterator;
use Itools\SmartString\SmartString;
use InvalidArgumentException;

/**
 * SmartArrayHtml - Collection returning SmartString values for HTML safety.
 *
 * Values are automatically wrapped in SmartString objects that HTML-encode
 * on output, preventing XSS vulnerabilities.
 *
 * - Scalars and null return SmartString objects, not raw types. Use ->value() to get raw value.
 * - Nested arrays return SmartArrayHtml, use ->toArray() for raw arrays and values
 * - Missing keys return SmartNull, use ->value() for raw null
 *
 * PhpStorm 2025.3.1: Repeated "@implements" needed - union types in Iterator generics don't work reliably for foreach inference
 * @implements Iterator<mixed, SmartString>
 * @implements Iterator<mixed, SmartArrayHtml>
 */
class SmartArrayHtml extends SmartArrayBase
{
    //region Creation and Conversion

    /**
     * Create new SmartArray with useSmartStrings=true
     *
     * Constructs a new SmartArray object from an array, recursively converting each element to either a SmartString
     * or a nested SmartArray. It also sets special properties for nested SmartArrays to indicate their position
     * within the root array.
     *
     * @param array $array The input array to convert into a SmartArray.
     * @param bool|array|null $properties An associative array of custom internal properties (legacy boolean accepted but deprecated).
     */
    public function __construct(array $array = [], bool|array|null $properties = [])
    {
        // Handle deprecated boolean parameter
        if ($properties === false) {
            self::logDeprecation("new SmartArrayHtml(\$data, false) is deprecated. Use new SmartArray(\$data) instead.");
            throw new InvalidArgumentException("Cannot create SmartArrayHtml with useSmartStrings=false. Use new SmartArray(\$data) instead.");
        }
        elseif (is_bool($properties)) {
            $properties = [];
        }

        // Handle deprecated useSmartStrings in array
        if (is_array($properties) && ($properties['useSmartStrings'] ?? true) === false) {
            self::logDeprecation("new SmartArrayHtml(\$data, ['useSmartStrings' => false]) is deprecated. Use new SmartArray(\$data) instead.");
            throw new InvalidArgumentException("Cannot create SmartArrayHtml with useSmartStrings=false. Use new SmartArray(\$data) instead.");
        }

        // Force useSmartStrings to true so values are SmartStrings
        $properties['useSmartStrings'] = true;

        // Pass through to parent with all properties
        parent::__construct($array, $properties);
    }

    /**
     * Create a new SmartArrayHtml that returns SmartString objects for HTML safety.
     *
     * @param array $array The input array to convert
     * @param array|bool $properties Optional properties to pass to the constructor
     * @return static A new SmartArrayHtml instance
     */
    public static function new(array $array = [], array|bool $properties = []): static
    {
        if (is_bool($properties)) {
            $properties = [];
        }
        return new static($array, $properties);
    }

    /**
     * Return values as raw PHP types for data processing.
     * Creates a new SmartArray instance.
     *
     * @return SmartArray A new SmartArray instance
     */
    public function asRaw(): SmartArray
    {
        return new SmartArray($this->toArray(), $this->getInternalProperties());
    }

    /**
     * Return values as HTML-safe SmartString objects.
     * Returns the same object (already in HTML mode).
     *
     * @return SmartArrayHtml This object (already HTML-safe)
     */
    public function asHtml(): SmartArrayHtml
    {
        return $this;
    }

    //endregion
    //region Value Access

    /** {@inheritDoc} */
    public function get(int|string $key, mixed $default = null): static|SmartNull|SmartString
    {
        // Must use func_num_args() check here and call parent appropriately,
        // because parent uses func_num_args() to detect if default was provided
        if (func_num_args() >= 2) {
            return parent::get($key, $default);
        }
        return parent::get($key);
    }

    /** {@inheritDoc} */
    public function first(): static|SmartNull|SmartString
    {
        return parent::first();
    }

    /** {@inheritDoc} */
    public function last(): static|SmartNull|SmartString
    {
        return parent::last();
    }

    /** {@inheritDoc} */
    public function nth(int $index): static|SmartNull|SmartString
    {
        return parent::nth($index);
    }

    //endregion
    //region Sorting & Filtering

    /** {@inheritDoc} */
    public function sort(int $flags = SORT_REGULAR): static
    {
        return parent::sort($flags);
    }

    /** {@inheritDoc} */
    public function sortBy(string $column, int $type = SORT_REGULAR): static
    {
        return parent::sortBy($column, $type);
    }

    /** {@inheritDoc} */
    public function unique(): static
    {
        return parent::unique();
    }

    /** {@inheritDoc} */
    public function filter(?callable $callback = null): static
    {
        return parent::filter($callback);
    }

    /** {@inheritDoc} */
    public function where(array|string $conditions, mixed $value = null): static
    {
        return parent::where($conditions, $value);
    }

    /** {@inheritDoc} */
    public function whereNot(string $field, mixed $value): static
    {
        return parent::whereNot($field, $value);
    }

    /** {@inheritDoc} */
    public function whereInList(string $field, mixed $value): static
    {
        return parent::whereInList($field, $value);
    }

    //endregion
    //region Array Transformation

    /** {@inheritDoc} */
    public function keys(): static
    {
        return parent::keys();
    }

    /** {@inheritDoc} */
    public function values(): static
    {
        return parent::values();
    }


    /** {@inheritDoc} */
    public function indexBy(string $column): static
    {
        return parent::indexBy($column);
    }

    /** {@inheritDoc} */
    public function groupBy(string $column): static
    {
        return parent::groupBy($column);
    }

    /** {@inheritDoc} */
    public function pluck(string|int $valueColumn, ?string $keyColumn = null): static
    {
        return parent::pluck($valueColumn, $keyColumn);
    }

    /** {@inheritDoc} */
    public function pluckNth(int $index): static
    {
        return parent::pluckNth($index);
    }

    /** {@inheritDoc} */
    public function column(int|string|null $columnKey, int|string|null $indexKey = null): static
    {
        return parent::column($columnKey, $indexKey);
    }

    /** {@inheritDoc} */
    public function implode(string $separator = ''): SmartString
    {
        return parent::implode($separator);
    }

    /** {@inheritDoc} */
    public function sprintf(string $format): SmartArray
    {
        return parent::sprintf($format);
    }

    /** {@inheritDoc} */
    public function map(callable $callback): static
    {
        return parent::map($callback);
    }

    /** {@inheritDoc} */
    public function each(Closure $callback): static
    {
        return parent::each($callback);
    }

    /** {@inheritDoc} */
    public function merge(array|SmartArrayBase ...$arrays): static
    {
        return parent::merge(...$arrays);
    }


    //endregion
    //region Database Operations

    /** {@inheritDoc} */
    public function load(string $column): static|SmartNull
    {
        return parent::load($column);
    }

    //endregion
    //region Deprecated Array Access

    /** {@inheritDoc} */
    public function offsetGet(mixed $offset, ?bool $useSmartStrings = null): static|SmartNull|SmartString
    {
        return parent::offsetGet($offset, $useSmartStrings);
    }

    //endregion
}
