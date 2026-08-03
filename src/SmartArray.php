<?php
/** @noinspection SenselessProxyMethodInspection */
declare(strict_types=1);

namespace Itools\SmartArray;

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
        // Handle deprecated boolean parameter: true contradicts this class (throw),
        // false is redundant (deprecation only)
        if ($properties === true) {
            self::logDeprecation('Creating a SmartArray with useSmartStrings=true is deprecated. Use SmartArrayHtml::new($data) instead.');
            throw new CallerException('Cannot create SmartArray with useSmartStrings=true. Use SmartArrayHtml::new($data) instead.');
        }
        if ($properties === false) {
            self::logDeprecation('Passing false to SmartArray is deprecated. Just use SmartArray::new($data)');
            $properties = [];
        }

        // Handle deprecated useSmartStrings in array
        if (is_array($properties) && ($properties['useSmartStrings'] ?? false) === true) {
            self::logDeprecation('Creating a SmartArray with useSmartStrings=true is deprecated. Use SmartArrayHtml::new($data) instead.');
            throw new CallerException('Cannot create SmartArray with useSmartStrings=true. Use SmartArrayHtml::new($data) instead.');
        }

        // Force useSmartStrings to false for raw values
        $properties['useSmartStrings'] = false;

        // Pass through to parent with all properties
        parent::__construct($array, $properties);
    }

    /**
     * Create a new SmartArray that returns raw values without SmartString wrapping.
     *
     * Same as `new SmartArray()`, but chainable on every supported PHP version:
     * before PHP 8.4, `new SmartArray($data)->pluck('id')` is a syntax error without
     * wrapping parentheses; `SmartArray::new($data)->pluck('id')` works everywhere.
     *
     *     $users = SmartArray::new($records)->indexBy('user_id');
     *
     * @param array $array The input array to convert
     * @param array|bool $properties Optional properties to pass to the constructor (legacy boolean handled by the constructor)
     * @return static A new SmartArray instance
     */
    public static function new(array $array = [], array|bool $properties = []): static
    {
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
    public function at(int $index): static|SmartNull|string|int|float|bool|null
    {
        return parent::at($index);
    }

    //endregion
    //region Sorting & Filtering

    /** {@inheritDoc} */
    public function sort(int $flags = SORT_REGULAR): static
    {
        return parent::sort($flags);
    }

    /** {@inheritDoc} */
    public function sortBy(string $field, int $flags = SORT_REGULAR): static
    {
        return parent::sortBy($field, $flags);
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
    public function columnAt(int $index): static
    {
        return parent::columnAt($index);
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
    public function map(callable $callback): static
    {
        return parent::map($callback);
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
