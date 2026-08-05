<?php
declare(strict_types=1);
namespace Itools\SmartArray;

use InvalidArgumentException;
use Iterator;
use Itools\SmartString\SmartString;

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
     * Create a SmartArrayHtml from an array, recursively converting nested arrays into
     * child SmartArrayHtml instances. Scalars and nulls are wrapped in SmartString on
     * access so they HTML-encode on output. Sets position metadata on child rows.
     *
     * @param array $array The input array to convert into a SmartArrayHtml.
     * @param bool|array|null $properties An associative array of custom internal properties (legacy boolean accepted but deprecated).
     */
    public function __construct(array $array = [], bool|array|null $properties = [])
    {
        // Handle deprecated boolean parameter: false contradicts this class (throw),
        // true is redundant (deprecation only)
        if ($properties === false) {
            self::logDeprecation('Creating a SmartArrayHtml with useSmartStrings=false is deprecated. Use SmartArray::new($data) instead.');
            throw new InvalidArgumentException('Cannot create SmartArrayHtml with useSmartStrings=false. Use SmartArray::new($data) instead.');
        }
        if ($properties === true) {
            self::logDeprecation('Passing true to SmartArrayHtml is deprecated. Just use SmartArrayHtml::new($data)');
            $properties = [];
        }

        // Handle deprecated useSmartStrings in array
        if (is_array($properties) && ($properties['useSmartStrings'] ?? true) === false) {
            self::logDeprecation('Creating a SmartArrayHtml with useSmartStrings=false is deprecated. Use SmartArray::new($data) instead.');
            throw new InvalidArgumentException('Cannot create SmartArrayHtml with useSmartStrings=false. Use SmartArray::new($data) instead.');
        }

        // Force useSmartStrings to true so values are SmartStrings
        $properties['useSmartStrings'] = true;

        // Pass through to parent with all properties
        parent::__construct($array, $properties);
    }

    /**
     * Create a new SmartArrayHtml that returns SmartString objects for HTML safety.
     *
     * Same as `new SmartArrayHtml()`, but chainable on every supported PHP version:
     * before PHP 8.4, `new SmartArrayHtml($data)->pluck('id')` is a syntax error without
     * wrapping parentheses; `SmartArrayHtml::new($data)->pluck('id')` works everywhere.
     *
     *     $users = SmartArrayHtml::new($records)->indexBy('user_id');
     *
     * @param array $array The input array to convert
     * @param array|bool $properties Optional properties to pass to the constructor (legacy boolean handled by the constructor)
     * @return static A new SmartArrayHtml instance
     */
    public static function new(array $array = [], array|bool $properties = []): static
    {
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
        return new SmartArray($this->toArray(), $this->getInternalProperties(withPosition: true));
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
    public function at(int|SmartString|SmartNull $index): static|SmartNull|SmartString
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
        return parent::where(...func_get_args());  // real arg count picks the one- vs two-argument form
    }

    /** {@inheritDoc} */
    public function whereNot(string $field, mixed $value = null): static
    {
        return parent::whereNot(...func_get_args());  // real arg count picks the one- vs two-argument form
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
    public function implode(string $separator = ''): SmartString
    {
        return parent::implode($separator);
    }

    /** {@inheritDoc} */
    public function map(callable $callback): static
    {
        return parent::map($callback);
    }

    /** {@inheritDoc} */
    public function merge(array|SmartArrayBase|SmartNull ...$arrays): static
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
    public function offsetGet(mixed $offset): static|SmartNull|SmartString
    {
        return parent::offsetGet($offset);
    }

    //endregion
}
