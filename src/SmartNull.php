<?php
declare(strict_types=1);

namespace Itools\SmartArray;

use Iterator, ArrayAccess, Countable;
use ReflectionMethod;
use RuntimeException;
use Itools\SmartString\SmartString;
use JetBrains\PhpStorm\Deprecated;
use JsonSerializable;
use stdClass;

/**
 * SmartNull - Chainable null object for missing elements.
 *
 * Implements SmartBase so instanceof SmartBase works for all Smart* types.
 * Extends stdClass to avoid IDE warnings related to undefined properties.
 *
 * Note: as an object, SmartNull is truthy in conditionals and compares loosely
 * equal to '' via __toString ($smartNull == '' is true). For explicit checks
 * use ->value() === null or instanceof SmartNull.
 */
class SmartNull extends stdClass implements SmartBase, Iterator, ArrayAccess, JsonSerializable, Countable
{
    use SharedHelpers;

    //region Creation and Conversion

    /**
     * @param array $properties Internal properties carried over from the SmartArray
     *                          that produced this SmartNull (mode, mysqli metadata, root).
     */
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
     *     $record = DB::select('users', ['num' => $id])->first()->asRaw();
     *     $record->name;      // Returns a string, or SmartNull when no record was found
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
     *     $record = DB::select('users', ['num' => $id])->first()->asHtml();
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
     * Displays diagnostic output. A SmartNull marks a missing key or empty result,
     * so there is no data to dump - the output says what this object is instead of
     * showing an empty array.
     */
    public function debug(): void
    {
        $class  = static::class;
        $output = <<<__TEXT__
            $class - missing key or empty result, value is null

            Property reads and method calls return SmartNull again, so chains keep working.
            Check the key name for typos, or test with ->isNotEmpty() before use.
            __TEXT__;

        echo self::xmpWrap("\n$output\n\n");
    }

    /**
     * Prints links to the online documentation.
     *
     * @deprecated Read the docs on GitHub instead - same content, easier to read:
     *             https://github.com/interactivetools-com/SmartArray#readme
     */
    #[Deprecated(reason: 'retired - read the docs on GitHub instead')]
    public function help(): void
    {
        // Keep the text in sync with Deprecations::help() - no common parent to share it
        $output = <<<'__TEXT__'
            SmartArray docs:  https://github.com/interactivetools-com/SmartArray#readme
            Method reference: https://github.com/interactivetools-com/SmartArray/blob/main/docs/method-reference.md
            __TEXT__;

        echo self::xmpWrap("\n$output\n\n");
    }

    /**
     * Shows just the null value in print_r() and var_dump() output, hiding internal
     * properties. Matches SmartString's [value] dump.
     */
    public function __debugInfo(): array
    {
        return ['value' => null];
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

    /**
     * Array reads on a missing value stay missing: returns $this so chains keep working.
     * Bracket syntax is deprecated everywhere, so it dispatches per $onOffsetAccess first.
     */
    public function offsetGet(mixed $offset): SmartNull
    {
        SmartArrayBase::triggerArrayAccessDeprecation($offset, 'get');
        return $this;
    }

    /**
     * All writes throw (see __set).
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        SmartArrayBase::triggerArrayAccessDeprecation($offset, 'set');
        $this->throwCannotSet();
    }

    /**
     * No keys exist on a missing value. Silent like SmartArrayBase::offsetExists,
     * so isset() and ?? don't signal - the read carries the notice.
     */
    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    public function offsetUnset(mixed $offset): void
    {
        // Nothing to unset, but the deprecated bracket syntax still signals
        SmartArrayBase::triggerArrayAccessDeprecation($offset, 'unset');
    }

    //endregion
    //region Database Operations

    /**
     * Get mysqli result information for the last database query.
     * Returns specified property (affected_rows, insert_id) or array of all properties if no property specified.
     *
     * Keep in sync with SmartArrayBase::mysqli() - SmartNull can't share it (no common parent).
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
     * All writes throw: a SmartNull marks a missing key or empty result, so
     * there is nothing real to write to and the value would be silently lost.
     * Same guard for property syntax, array syntax, and two-argument set().
     */
    public function __set(string $name, mixed $value): void
    {
        $this->throwCannotSet();
    }

    /**
     * One argument is SmartString's set($value): produce that value and end the
     * chain, like or(). Two arguments is SmartArray's set($key, $value), a
     * write, and all writes throw (see __set above).
     */
    public function set(mixed ...$args): mixed
    {
        if (count($args) === 1 && $this->useSmartStrings) {
            return $this->__call('set', $args);
        }
        $this->throwCannotSet();
    }

    private function throwCannotSet(): never
    {
        throw new RuntimeException("Cannot set values on SmartNull - this value came from a missing key or empty result, check ->isNotEmpty() first");
    }

    /**
     * Emulate response methods for SmartArray and SmartString.
     *
     * A missing key doesn't tell us whether the caller expected a value or a
     * collection, so this object answers for both. SmartString methods (HTML mode
     * only) are tried first, and the result decides what comes back: a still-null
     * result means nothing was produced, so the SmartNull itself returns and the
     * chain stays open for either ending, ->or('n/a') for a value or ->implode()
     * for a collection. Produced results (or() fallbacks, int() and other scalars)
     * return as usual. map() and its deprecated alias apply() propagate without
     * running their callback: a missing key has no value to pass it, while a
     * NULL value in an existing key still runs the callback.
     *
     * Everything else delegates to an empty SmartArray/SmartArrayHtml of the same
     * mode. Unknown methods are forwarded too, so they throw the same undefined-method
     * Error as the rest of the library ("did you mean" hint + caller's file:line).
     *
     * @param string $name
     * @param array $arguments
     * @return mixed This SmartNull to keep the chain open, or whatever the delegated method returns
     */
    public function __call($name, array $arguments): mixed
    {
        // SmartString methods only delegate in HTML mode: raw values are plain scalars
        // with no methods, so a miss answers SmartString calls the same way - with the
        // standard undefined-method Error. The isPublic() check keeps private helpers
        // out: method_exists() reports them, but they aren't part of the API.
        // getIterator is defined by both classes and skips SmartString: a missing value
        // iterates like an empty collection, it doesn't throw SmartString's can't-foreach error
        //
        // Don't optimize: measured negligible - this only runs when a key is missing,
        // so it's rarely called. And caching the method names breaks mixed-case calls:
        // PHP doesn't care that it's ->dateFormat() not ->dateformat(), but an isset()
        // lookup would.
        // The in_array list mirrors the deprecated shims in SmartString::__call, which
        // method_exists() can't see. Keep both sites in sync: when SmartString drops a
        // shim, drop it here too.
        $nameLower           = strtolower($name); // PHP method dispatch ignores case, so every name check here must too
        $isSmartStringMethod = $this->useSmartStrings
            && $nameLower !== 'getiterator'
            && (
                (method_exists(SmartString::class, $name) && (new ReflectionMethod(SmartString::class, $name))->isPublic())
                || in_array($nameLower, ['noencode', 'tostring', 'jsencode', 'striptags'], true)
            );

        if ($isSmartStringMethod) {
            if ($nameLower === 'map' || $nameLower === 'apply') {
                return $this;
            }
            $result = SmartString::new(null)->$name(...$arguments);
            return $result instanceof SmartString && $result->isNull() ? $this : $result;
        }

        return $this->useSmartStrings
            ? (new SmartArrayHtml([], $this->getInternalProperties()))->$name(...$arguments)
            : (new SmartArray([], $this->getInternalProperties()))->$name(...$arguments);
    }

    /**
     * Outputs the same as a null SmartString.
     */
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
     *
     * Keep the key list in sync with SmartArrayBase::getInternalProperties() (its base
     * list - SmartNull has no position metadata), or arrays spawned from a SmartNull
     * silently lose the missing field.
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
