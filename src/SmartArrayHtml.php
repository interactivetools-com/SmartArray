<?php
declare(strict_types=1);
namespace Itools\SmartArray;

use IteratorAggregate;
use Itools\SmartString\SmartString;
use JetBrains\PhpStorm\Deprecated;

// compile to single opcodes instead of runtime name lookups; see the note in SmartArrayBase.php
use function func_get_args, is_array;

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
 * Full API and docs: SmartArrayBase. Methods are redeclared here only when this
 * mode narrows the return type (SmartStrings here, raw values in SmartArray);
 * only new(), asRaw(), and asHtml() have per-class behavior.
 *
 * PhpStorm: repeated single-type @implements lines - it keeps only one object
 * member per generic union, so foreach over a union loses the second type
 * @implements IteratorAggregate<mixed, SmartArrayHtml>
 * @implements IteratorAggregate<mixed, SmartString>
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
        // Deprecated legacy forms: boolean argument, or an explicit useSmartStrings key
        if (!is_array($properties) || isset($properties['useSmartStrings'])) {
            $properties = $this->deprecatedUseSmartStringsArg($properties, requiredMode: true);
        }

        // Force useSmartStrings to true so values are SmartStrings
        $properties['useSmartStrings'] = true;
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
    //region Array Transformation

    /** {@inheritDoc} */
    public function implode(string $separator = ''): SmartString
    {
        return parent::implode($separator);
    }

    //endregion
    //region Deprecated Access

    /** {@inheritDoc} */
    public function offsetGet(mixed $offset): static|SmartNull|SmartString
    {
        return parent::offsetGet($offset);
    }

    /**
     * {@inheritDoc}
     * @deprecated Use property access: ->key, or ->{'users.id'} for keys property syntax
     *             can't type. For a missing-key default use ->key ?? $default.
     */
    #[Deprecated(reason: "use property access ->key or ->{'key'}, with ?? for defaults")]
    public function get(int|string|SmartString|SmartNull $key, mixed $default = null): static|SmartNull|SmartString
    {
        // func_get_args: get() branches on whether $default was passed, so forward the real arg count
        return parent::get(...func_get_args());
    }

    /**
     * {@inheritDoc}
     * @deprecated Use ->at() - same behavior, new name
     */
    #[Deprecated(reason: 'renamed to at()', replacement: '%class%->at()')]
    public function nth(int $index): static|SmartNull|SmartString
    {
        return parent::nth($index);
    }

    //endregion
}
