<?php
/** @noinspection SenselessProxyMethodInspection */
declare(strict_types=1);

namespace Itools\SmartArray;

use InvalidArgumentException;
use Closure;

/**
 * SmartArray - Collection returning raw PHP values (string, int, float, bool, null).
 *
 * This is the default mode for data processing. For HTML-safe output with
 * automatic encoding, use SmartArrayHtml or ->asHtml().
 *
 * - Scalars and null return actual types (string, int, float, bool, null), not SmartString objects
 * - Nested arrays return SmartArray, use ->toArray() for raw arrays
 * - Missing keys return SmartNull, use ->value() for raw null
 *
 * PhpStorm 2025.3.1: Repeated "@implements" needed - union types in Iterator generics don't work reliably for foreach inference
 * @implements \Iterator<mixed, SmartArray>
 * @implements \Iterator<mixed, string>
 * @implements \Iterator<mixed, int>
 * @implements \Iterator<mixed, float>
 * @implements \Iterator<mixed, bool>
 * @implements \Iterator<mixed, null>
 */
class SmartArray extends SmartArrayBase
{
    //region Creation and Conversion

    /**
     * Create new SmartArray with useSmartStrings=false
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
        if ($properties === true) {
            self::logDeprecation("new SmartArray(\$data, true) is deprecated. Use new SmartArrayHtml(\$data) instead.");
            throw new InvalidArgumentException("Cannot create SmartArray with useSmartStrings=true. Use new SmartArrayHtml(\$data) instead.");
        }
        elseif (is_bool($properties)) {
            $properties = [];
        }

        // Handle deprecated useSmartStrings in array
        if (is_array($properties) && ($properties['useSmartStrings'] ?? false) === true) {
            self::logDeprecation("new SmartArray(\$data, ['useSmartStrings' => true]) is deprecated. Use new SmartArrayHtml(\$data) instead.");
            throw new InvalidArgumentException("Cannot create SmartArray with useSmartStrings=true. Use new SmartArrayHtml(\$data) instead.");
        }

        // Force useSmartStrings to false for raw values
        $properties['useSmartStrings'] = false;

        // Pass through to parent with all properties
        parent::__construct($array, $properties);
    }

    /**
     * Create a new SmartArray that returns raw values without SmartString wrapping.
     *
     * @param array $array The input array to convert
     * @param array|bool $properties Optional properties to pass to the constructor
     * @return static A new SmartArray instance
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
     * Returns the same object (already in raw mode).
     *
     * @return SmartArray This object (already raw)
     */
    public function asRaw(): SmartArray
    {
        return $this;
    }

    /**
     * Return values as HTML-safe SmartString objects.
     * Creates a new SmartArrayHtml instance.
     *
     * @return SmartArrayHtml A new SmartArrayHtml instance
     */
    public function asHtml(): SmartArrayHtml
    {
        return new SmartArrayHtml($this->toArray(), $this->getInternalProperties());
    }

    //endregion
    //region Value Access

    /** {@inheritDoc} */
    public function get(int|string $key, mixed $default = null): static|SmartNull|string|int|float|bool|null
    {
        // Must use func_num_args() check here and call parent appropriately,
        // because parent uses func_num_args() to detect if default was provided
        if (func_num_args() >= 2) {
            return parent::get($key, $default);
        }
        return parent::get($key);
    }

    /** {@inheritDoc} */
    public function first(): static|SmartNull|string|int|float|bool|null
    {
        return parent::first();
    }

    /** {@inheritDoc} */
    public function last(): static|SmartNull|string|int|float|bool|null
    {
        return parent::last();
    }

    /** {@inheritDoc} */
    public function nth(int $index): static|SmartNull|string|int|float|bool|null
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
    public function sortBy(string $field, int $type = SORT_REGULAR): static
    {
        return parent::sortBy($field, $type);
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
    public function where(array|string $field, mixed $value = null): static
    {
        return parent::where($field, $value);
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
    public function indexBy(string $field): static
    {
        return parent::indexBy($field);
    }

    /** {@inheritDoc} */
    public function groupBy(string $field): static
    {
        return parent::groupBy($field);
    }

    /** {@inheritDoc} */
    public function pluck(string|int $valueField, ?string $keyField = null): static
    {
        return parent::pluck($valueField, $keyField);
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
    public function implode(string $separator = ''): string
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
    public function load(string $field): static|SmartNull
    {
        return parent::load($field);
    }

    //endregion
    //region Deprecated Array Access

    /** {@inheritDoc} */
    public function offsetGet(mixed $offset): static|SmartNull|string|int|float|bool|null
    {
        return parent::offsetGet($offset);
    }

    //endregion
}
