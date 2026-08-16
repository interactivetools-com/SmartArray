<?php
declare(strict_types=1);

namespace Itools\SmartArray;

use IteratorAggregate;
use Itools\SmartString\SmartString;
use JetBrains\PhpStorm\Deprecated;

// import built-ins so calls resolve at compile time instead of per-call lookups; NamespacedCallsTest keeps this list exact
use function func_get_args, is_array;

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
 * Full API and docs: SmartArrayBase. The @method tags below narrow return types
 * to this mode (raw values here, SmartStrings in SmartArrayHtml); only new(),
 * asRaw(), and asHtml() have per-class behavior.
 *
 * PhpStorm: repeated single-type @implements lines - it keeps only one object
 * member per generic union, so foreach over a union loses the second type
 * @implements IteratorAggregate<mixed, SmartArray>
 * @implements IteratorAggregate<mixed, string|int|float|bool|null>
 *
 * @method static|SmartNull|string|int|float|bool|null first()
 * @method static|SmartNull|string|int|float|bool|null last()
 * @method static|SmartNull|string|int|float|bool|null at(int|SmartString|SmartNull $index)
 * @method string implode(string $separator = '')
 * @method static|SmartNull|string|int|float|bool|null offsetGet(mixed $offset)
 */
class SmartArray extends SmartArrayBase
{
    //region Creation and Conversion

    /**
     * Create a SmartArray from an array, recursively converting nested arrays into
     * child SmartArrays. Scalars and nulls are returned as raw PHP types on access.
     * Sets position metadata on child rows.
     *
     * @param array $array The input array to convert into a SmartArray.
     * @param bool|array|null $properties An associative array of custom internal properties (legacy boolean accepted but deprecated).
     */
    public function __construct(array $array = [], bool|array|null $properties = [])
    {
        // Deprecated legacy forms: boolean argument, or an explicit useSmartStrings key
        if (!is_array($properties) || isset($properties['useSmartStrings'])) {
            $properties = $this->deprecatedUseSmartStringsArg($properties, requiredMode: false);
        }

        // Force useSmartStrings to false for raw values
        $properties['useSmartStrings'] = false;
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
        return new SmartArrayHtml($this->toArray(), $this->getInternalProperties(withPosition: true));
    }

    //endregion
    //region Deprecated Access

    /**
     * {@inheritDoc}
     * @deprecated Use property access: ->key, or ->{'users.id'} for keys property syntax
     *             can't type. For a missing-key default use ->key ?? $default.
     */
    #[Deprecated(reason: "use property access ->key or ->{'key'}, with ?? for defaults")]
    public function get(int|string|SmartString|SmartNull $key, mixed $default = null): static|SmartNull|string|int|float|bool|null
    {
        // func_get_args: get() branches on whether $default was passed, so forward the real arg count
        return parent::get(...func_get_args());
    }

    /**
     * {@inheritDoc}
     * @deprecated Use ->at() - same behavior, new name
     */
    #[Deprecated(reason: 'renamed to at()', replacement: '%class%->at()')]
    public function nth(int $index): static|SmartNull|string|int|float|bool|null
    {
        return parent::nth($index);
    }

    //endregion
}
