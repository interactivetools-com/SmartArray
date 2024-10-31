<?php

declare(strict_types=1);

namespace Itools\SmartArray;

use ArrayObject;
use InvalidArgumentException;
use Itools\SmartString\SmartString;
use JsonSerializable;

/**
 * SmartArray - Represent an array as an ArrayObject with extra features and a fluent, chainable interface.
 */
class SmartArray extends ArrayObject implements JsonSerializable                                                                                                                                                                                                                    // NOSONAR - Ignore S1448 "Too many methods"
{
    #region Creation and Conversion

    /**
     * Creates a new SmartArray object - an alias for `(new SmartArray($array))`
     *
     * This static method prevents syntax errors when chaining methods. Without it,
     *  developers must wrap 'new SmartArray()' in parentheses to chain methods inline:
     * ```
     *  $usersById = (new SmartArray($records))->indexBy('id'); // Additional parentheses
     *  $usersById = SmartArray::new($records)->indexBy('id');  // Cleaner chaining
     * ```
     *
     * @param array $array The input array to convert into a SmartArray
     * @return SmartArray A new SmartArray instance
     */
    public static function new(array $array): SmartArray
    {
        return new SmartArray($array);
    }

    /**
     * Constructs a new SmartArray object from an array, recursively converting each element to either a SmartString
     * or a nested SmartArray. It also sets special properties for nested SmartArrays to indicate their position
     * within the parent array.
     *
     * @param array $array The input array to convert into a SmartArray.
     */
    public function __construct(array $array = [])
    {
        parent::__construct([], ArrayObject::ARRAY_AS_PROPS);   // create a new ArrayObject with the given array
        $firstKey = array_key_first($array);
        $lastKey  = array_key_last($array);
        $position = 0;  // Initialize position for nested SmartArrays within the parent array (1 is the first position)

        foreach ($array as $key => $value) {
            $position++;                         // increment position for each element
            $this->offsetSet($key, $value);      // set values and convert to SmartString or SmartArray
            $newValue = $this->offsetGet($key);  // retrieve the new value, which may be a SmartString or SmartArray

            // Nested SmartArrays, set isFirst() and isLast() on child SmartArrays
            if ($newValue instanceof self) {
                if ($key === $firstKey) {
                    $newValue->setProp('isFirst', true);
                }
                if ($key === $lastKey) {
                    $newValue->setProp('isLast', true);
                }
                $newValue->setProp('position', $position);
            }
        }
    }

    /**
     * Recursively converts SmartArray back to a standard PHP array.
     *
     * This method creates an array representation of the SmartArray object's elements,
     * converting nested structures as follows:
     * - SmartArray objects are recursively converted to arrays
     * - SmartString objects are converted to their original values
     * - SmartNull objects are converted to null
     * - Unexpected types will throw an InvalidArgumentException
     *
     * @return array An array representation of the object's elements with original values.
     */
    public function toArray(): array
    {
        // Future options: We could add a default arg $smartStringsToValues = true to allow SmartStrings to be returned as objects
        $array = [];
        foreach ($this as $key => $value) {
            $array[$key] = match (true) {
                $value instanceof self        => $value->toArray(),   // Recursively convert nested SmartArrays
                $value instanceof SmartString => $value->value(),     // Convert SmartStrings to original values
                $value instanceof SmartNull   => null,                // Convert SmartNull to null
                default                       => throw new InvalidArgumentException("Unexpected value type encountered: " . get_debug_type($value)),
            };
        }

        return $array;
    }

    /**
     * Customizes JSON serialization for list-like arrays (sequential keys 0, 1, 2...).
     *
     * Returns an array of original values, not SmartArray or SmartString wrappers. For list-like arrays,
     * produces a JSON array instead of ArrayObject's default JSON object. Associative arrays retain
     * default JSON object serialization.
     *
     * Examples:
     * echo json_encode(new ArrayObject(['zero','one','two','three']));  // Output: {"0":"zero","1":"one","2":"two","3":"three"}
     * echo json_encode(new SmartArray(['zero','one','two','three']));   // Output: ["zero","one","two","three"]
     *
     * @return array An array that will be serialized as a JSON array by json_encode().
     */
    public function jsonSerialize(): array
    {
        return $this->getArrayCopy();
    }

    #endregion
    #region Array Information

    /**
     * Returns the number of elements in the SmartArray.
     *
     * This method is a wrapper around the parent ArrayObject's count() method.
     * It's included here for clarity and to remind users of its availability.
     * You can use either PHP's count($obj) or $obj->count() syntax.
     *
     * @return int The number of elements in the SmartArray.
     */
    public function count(): int  // NOSONAR - Ignore S1185 "Overriding methods should do more than superclass", this is a wrapper method for clarity
    {
        return parent::count();
    }

    /**
     * Checks if the SmartArray is empty.
     *
     * @return bool Returns true if the SmartArray contains no elements, false otherwise.
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Checks if the SmartArray is not empty.
     *
     * @return bool Returns true if the SmartArray contains at least one element, false otherwise.
     */
    public function isNotEmpty(): bool
    {
        return $this->count() !== 0;
    }

    /**
     * Checks if this is the first element in a parent SmartArray.
     *
     * @return bool True if first in parent, false if not in a parent SmartArray.
     */
    public function isFirst(): bool
    {
        return $this->getProp('isFirst');
    }

    /**
     * Checks if this is the last element in a parent SmartArray.
     *
     * @return bool True if last in parent, false if not in a parent SmartArray.
     */
    public function isLast(): bool
    {
        return $this->getProp('isLast');
    }

    /**
     * Gets position in parent SmartArray (1-based, unrelated to keys/indexes).
     *
     * @return int Position in parent (1, 2, ...), or 0 if not in a parent SmartArray.
     */
    public function position(): int
    {
        return $this->getProp('position');
    }

    /**
     * Checks if the element's position is a multiple of the given value, for creating grids.
     *
     * @param int $value The divisor to check against (must be > 0).
     * @return bool True if the position is a multiple of $value, false otherwise.
     * @throws InvalidArgumentException If $value is <= 0.
     */
    public function isMultipleOf(int $value): bool
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("Value must be greater than 0.");
        }
        return $this->position() % $value === 0;
    }

    #endregion
    #region Value Access

    /**
     * Retrieves an element from the SmartArray, or a SmartNull if not found, providing an alternative to $array[$key] or $array->key syntax.
     *
     * @param int|string $key The key or offset of the element to retrieve.
     * @return SmartString|SmartArray|SmartNull The value associated with the key. If the key doesn't exist, a SmartNull object is returned to allow further chaining.
     */
    public function get(int|string $key): SmartString|SmartArray|SmartNull
    {
        return $this->offsetGet($key);
    }

    /**
     * Retrieves the first element from the SmartArray, or a SmartNull if the array is empty.
     *
     * @return SmartArray|SmartString|SmartNull The first element in the array. If the array is empty, a SmartNull object is returned to allow further chaining.
     */
    public function first(): SmartArray|SmartString|SmartNull
    {
        return $this->nth(0);
    }

    /**
     * Retrieves the last element from the SmartArray, or a SmartNull if the array is empty.
     *
     * @return SmartArray|SmartString|SmartNull The last element in the array, or a SmartNull object if empty, allowing further chaining.
     */
    public function last(): SmartArray|SmartString|SmartNull
    {
        return $this->nth(-1);
    }

    /**
     * Retrieves an element at a specific position in the SmartArray, based on element order rather than keys or indexes.
     *
     * This method supports both positive and negative positions. Positive positions count from the start (0-based),
     * while negative positions count from the end (-1 for the last element). Original array keys are ignored.
     *
     * @param int $index The position of the element to retrieve (0-based or negative for counting from the end).
     * @return SmartArray|SmartString|SmartNull The element at the specified position, or a SmartNull object if the position is out of bounds, allowing further chaining.
     */
    public function nth(int $index): SmartArray|SmartString|SmartNull
    {
        $values = $this->values();
        $count  = $values->count();
        $index  = ($index < 0) ? $count + $index : $index; // Convert negative indexes to positive

        if ($index >= 0 && $index < $count) {
            return $values->offsetGet($index);
        }

        return new SmartNull();
    }

    /**
     * Retrieves a value from the SmartArray.
     *
     * This method is called in two scenarios:
     * 1. When accessing the object using array syntax (e.g., $smartArray[16]).
     * 2. When accessing properties (e.g., $smartArray->property), if the ArrayObject::ARRAY_AS_PROPS flag is set (default in SmartArray).
     *
     * Note: With ArrayObject::ARRAY_AS_PROPS, this method handles both array and property access
     * for all keys, whether they are defined or not, completely bypassing __get.
     */
    public function offsetGet(mixed $key): SmartArray|SmartString|SmartNull
    {
        $key = $key instanceof SmartString ? $key->value() : $key; // Convert SmartString keys to raw values

        if ($this->offsetExists($key)) {
            return parent::offsetGet($key);
        }
        $this->warnIfMissing($key, 'offset');
        return new SmartNull();
    }

    /**
     * Sets a value in the SmartArray, converting it to SmartString or nested SmartArray.
     *
     * This method is called in two scenarios:
     * 1. When setting a value using array syntax (e.g., $smartArray['key'] = $value).
     * 2. When setting properties (e.g., $smartArray->property = $value), if the ArrayObject::ARRAY_AS_PROPS flag is set (default in SmartArray).
     *
     * Note: With ArrayObject::ARRAY_AS_PROPS, this method handles both array and property assignment,
     * completely bypassing __set for all keys, whether they are defined or not.
     *
     * @param mixed $key The key or property name to set. If null, the value is appended to the array.
     * @param mixed $value The value to set. Will be converted to SmartString or SmartArray as appropriate.
     *
     * @throws InvalidArgumentException If an unsupported value type is provided.
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        $typeOrClass = get_debug_type($value); // Returns SmartArray instead of \Itools\SmartArray\SmartArray
        $quotedKey   = is_numeric($key) ? $key : "'$key'";
        $newValue    = match ($typeOrClass) {
            'array' => new SmartArray($value),                                    // Convert nested arrays to SmartArrays
            'string', 'int', 'float', 'bool', 'null' => new SmartString($value),  // Convert scalars and nulls to SmartStrings
            default => throw new InvalidArgumentException("SmartArray doesn't support '$typeOrClass' values. Key $quotedKey"),
        };

        parent::offsetSet($key, $newValue);
    }

    #endregion
    #region Array Transformation

    /**
     * Returns a new SmartArray containing all the keys of the current SmartArray.
     *
     * This method is a shortcut for PHP's array_keys() function, returning the result
     * as a new SmartArray object.
     *
     * @return SmartArray A new SmartArray containing all the keys from the current SmartArray.
     *
     * @example
     * $arr = new SmartArray(['a' => 1, 'b' => 2, 'c' => 3]);
     * $keys = $arr->keys(); // Returns SmartArray(['a', 'b', 'c'])
     */
    public function keys(): SmartArray
    {
        $keys = array_keys($this->getArrayCopy());
        return new self($keys);
    }

    /**
     * Returns a new SmartArray containing all the values of the current SmartArray.
     *
     * This method is a shortcut for PHP's array_values() function, returning the result
     * as a new SmartArray object. It re-indexes the array numerically.
     *
     * @return SmartArray A new SmartArray containing all the values from the current SmartArray.
     *
     * @example
     * $arr = new SmartArray(['a' => 25, 'b' => 16, 'c' => 71]);
     * $values = $arr->values(); // Returns SmartArray([25, 16, 71])
     */
    public function values(): SmartArray
    {
        $values = array_values($this->toArray());
        return new self($values);
    }

    /**
     * Returns a new SmartArray instance with unique values, preserving keys.  Just like array_unique().
     *
     * This method removes duplicate values from the SmartArray, preserving the original keys.
     * The first occurrence of each unique value is preserved.
     *
     * @return SmartArray A new instance of SmartArray with duplicate values removed,
     *                    keeping only the first occurrence of each unique value.
     */
    public function unique(): SmartArray
    {
        $unique = array_unique($this->toArray());
        return new SmartArray($unique);
    }

    /**
     * Returns a new SmartArray instance sorted by values, uses PHP sort() function.
     */
    public function sort(int $flags = SORT_REGULAR): SmartArray
    {
        $this->assertFlatArray();

        $sorted = $this->toArray();
        sort($sorted, $flags);
        return new SmartArray($sorted);
    }

    /**
     * Returns a new SmartArray sorted by the specified column, uses PHP array_multisort().
     *
     * @param string $column
     * @param int $type
     * @return SmartArray
     */
    public function sortBy(string $column, int $type = SORT_REGULAR): SmartArray {

        $this->assertNestedArray();
        $this->first()->warnIfMissing($column, 'argument');

        // sort by key
        $sorted       = $this->toArray();
        $columnValues = array_column($sorted, $column);
        array_multisort($columnValues, SORT_ASC, $type, $sorted);

        return new SmartArray($sorted);
    }

    /**
     * Creates a new SmartArray indexed by the specified column.
     *
     * This method transforms the current SmartArray (assumed to be a nested array of rows)
     * into a new SmartArray where each element is indexed by the value of the specified column.
     *
     * @param string $column The column name to index the rows by.
     *
     * @return SmartArray A new SmartArray indexed by the specified column.
     * @throws InvalidArgumentException If the SmartArray is not nested.
     *
     * @example
     * $users = new SmartArray([
     *     ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'city' => 'New York'],
     *     ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'city' => 'New York'],
     *     ['id' => 3, 'name' => 'Mike', 'email' => 'mike@example.com', 'city' => 'Vancouver'],
     * ]);
     *
     * // Single row per key (default), no duplicates
     * $emailToUser = $users->indexBy('email'); // Result:
     * [
     *     'john@example.com' => ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'city' => 'New York'],
     *     'jane@example.com' => ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'city' => 'New York'],
     *     'mike@example.com' => ['id' => 3, 'name' => 'Mike', 'email' => 'mike@example.com', 'city' => 'Vancouver'],
     * ]
     *
     * // Single row per key (default), duplicates overwrite
     * $emailToUser = $users->indexBy('city'); // Result:
     * [
     *     'New York'  => ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'city' => 'New York'],
     *     'Vancouver' => ['id' => 3, 'name' => 'Mike', 'email' => 'mike@example.com', 'city' => 'Vancouver']
     * ]
     */
    public function indexBy(string $column): SmartArray
    {
        $values = [];
        $this->assertNestedArray();
        $this->first()->warnIfMissing($column, 'argument');

        foreach ($this->toArray() as $row) {
            $key = $row[$column];
            $values[$key] = $row;
        }

        return new SmartArray($values);
    }

    /**
     * Creates a new SmartArray indexed by the specified column.
     *
     *  This method transforms the current SmartArray (assumed to be a nested array of rows)
     *  into a new SmartArray where each element is indexed by the value of the specified column.
     *
     * @param string $column The column name to index the rows by.
     *
     * @return SmartArray A new SmartArray indexed by the specified column.
     * @throws InvalidArgumentException If the SmartArray is not nested.
     *
     * @example
     * $users = new SmartArray([
     *     ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'city' => 'New York'],
     *     ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'city' => 'New York'],
     *     ['id' => 3, 'name' => 'Mike', 'email' => 'mike@example.com', 'city' => 'Vancouver'],
     * ]);
     *
     * // Multiple rows per key
     * $cityToUsers = $users->groupBy('city'); // Result:
     * [
     *     'New York' => [
     *         ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'city' => 'New York'],
     *         ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'city' => 'New York'],
     *     ],
     *     'Vancouver' => [
     *         ['id' => 3, 'name' => 'Mike', 'email' => 'mike@example.com', 'city' => 'Vancouver'],
     *     ],
     * ]
     */
    public function groupBy(string $column): SmartArray
    {
        $values = [];
        $this->assertNestedArray();
        $this->first()->warnIfMissing($column, 'argument');

        foreach ($this->toArray() as $row) {
            $key = $row[$column];
            $values[$key][] = $row;
        }

        return new SmartArray($values);
    }

    /**
     * Joins the elements of the SmartArray into a single string with a specified separator.
     *
     * This method works on flat SmartArrays only. For SmartString elements,
     * their original values are used in the resulting string.
     *
     * @param string $separator The string to use as a separator between elements.
     *
     * @return SmartString The resulting string after joining all elements.
     * @throws InvalidArgumentException If the SmartArray is nested.
     *
     * @example
     * $arr = new SmartArray(['apple', 'banana', 'cherry']);
     * $result = $arr->join(', '); // Returns SmartString: "apple, banana, cherry"
     */
    public function join(string $separator): SmartString
    {
        $this->assertFlatArray();

        $values = array_map('strval', array_values($this->toArray()));
        $value  = implode($separator, $values);

        return new SmartString($value);
    }

    /**
     * Applies a callback function to each element of the SmartArray and returns a new SmartArray with the results.
     *
     * The callback function receives raw values (strings, numbers, arrays) instead of SmartString or SmartArray objects.
     *
     * @param callable $callback A function to apply to each element. It receives a raw value and should return a transformed value.
     *
     * @return self A new SmartArray containing the transformed elements.
     *
     * @example
     * $arr = new SmartArray(['apple', 'banana', 'cherry']);
     * $upper = $arr->map(fn(string $fruit) => strtoupper($fruit));
     * // $upper is now a SmartArray: ['APPLE', 'BANANA', 'CHERRY']
     *
     * $nested = new SmartArray([['a' => 1], ['a' => 2]]);
     * $values = $nested->map(fn(array $item) => $item['a']);
     * // $values is now a SmartArray: [1, 2]
     */
    public function map(callable $callback): self
    {
        return new SmartArray(array_map($callback, $this->toArray()));
    }

    /**
     * Extracts a single column from a nested SmartArray.
     *
     * This method retrieves the values of a specified key from all elements in the nested SmartArray,
     * returning them as a new SmartArray. It's particularly useful for extracting a specific field
     * from a collection of records.
     *
     * @param string|int $key The key of the column to extract from each nested element.
     * @return SmartArray A new SmartArray containing the extracted values.
     * @example
     * $users = new SmartArray([
     *     ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
     *     ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']
     * ]);
     * $userEmails = $users->pluck('email');                         // $userEmails is now a SmartArray: ['john@example.com', 'jane@example.com']
     * $csvEmails  = $users->pluck('email')->join(', ')->value(); // $csvEmails is now a string: "john@example.com, jane@example.com"
     */
    public function pluck(string|int $key): SmartArray
    {
        $this->assertNestedArray();
        $this->first()->warnIfMissing($key, 'argument');

        $values = array_column($this->toArray(), $key);
        return new SmartArray($values);
    }

    /**
     * Splits the SmartArray into smaller SmartArrays of a specified size.
     *
     * This method divides the current SmartArray into multiple SmartArrays, each containing
     * at most the specified number of elements. The last chunk may contain fewer elements
     * if the original SmartArray's count is not divisible by the chunk size.
     *
     * @param int $size The size of each chunk. Must be greater than 0.
     * @return SmartArray A new SmartArray containing SmartArrays of the specified size.
     * @throws InvalidArgumentException If the size is less than or equal to 0.
     *
     * @example
     * $arr = new SmartArray([1, 2, 3, 4, 5, 6, 7]);
     * $chunks = $arr->chunk(3); // $chunks is now a SmartArray containing:
     * [
     *     SmartArray([1, 2, 3]),
     *     SmartArray([4, 5, 6]),
     *     SmartArray([7])
     * ]
     */
    public function chunk(int $size): SmartArray
    {
        if ($size <= 0) {
            throw new InvalidArgumentException("Chunk size must be greater than 0.");
        }

        $chunks = array_chunk($this->toArray(), $size);
        return new SmartArray($chunks);
    }

    #endregion
    #region Debugging and Help

    /**
     * Show data and debug information when print_r() is used to examine object.
     *
     * @return array An associative array containing debugging information.
     */
    public function __debugInfo(): array
    {
        return DebugInfo::debugInfo($this);
    }

    /**
     * Handles SmartArray to string conversion attempts.
     *
     * Outputs a custom warning message instead of a fatal error when a SmartArray object is used in a string context.
     * The warning includes the file and line number of the conversion attempt and usage guidance.
     *
     * Note: A suppressed E_USER_WARNING error is triggered to activate any set_error_handler() logging handlers.
     *
     * @return string An empty string to prevent fatal errors.
     */
    public function __toString(): string
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0];
        $inFileOnLine = sprintf("in %s on line %s", $caller['file'], $caller['line']);

        // output warning and trigger PHP warning (for logging)
        // PHP Error: Fatal error: Uncaught Error: Object of class Itools\SmartArray\SmartArray could not be converted to string in C:\path\file.php:27
        $warning = "Can't convert SmartArray to string $inFileOnLine.\n\n";
        $warning .= "In double-quoted strings, use \"\$var->property\" for properties, but wrap methods and array access in braces like \"{\$var->method()} or {\$var['key']}\"\n\n";
        $warning .= "For more info: \$var->help()";

        // output warning
        echo "\nWarning: $warning\n\n";           // Output with echo so PHP doesn't add the filename and line number of this function on the end
        @trigger_error($warning, E_USER_WARNING); // Trigger a PHP warning but hide output with @ so it will still get logged
        return "";
    }

    /**
     * Displays help information about available methods and properties.
     *
     * @return void
     */
    public function help(): void
    {
        echo DebugInfo::help();
    }

    #endregion
    #region Internal Methods & Properties

    // PROPERTIES NOTICE:
    // $this is an ArrayObject with ArrayObject::ARRAY_AS_PROPS flag
    // array elements are stored in an inaccessible internal array in $this->storage by PHP's ArrayObject
    // So getting/setting properties updates the stored element values, NOT the properties themselves
    // To maintain separation, internal properties must be private and accessed via getProp() and setProp()
    private bool $isFirst  = false;                                                                                                                                                                           // Is this ArrayObject the first child element of a parent ArrayObject? // NOSONAR - Ignore S1068 false-positive Unused private fields should be removed
    private bool $isLast = false;                                                                                                                                                                                                                                                  // Is this ArrayObject the last child element of a parent ArrayObject?  // NOSONAR - Ignore S1068 false-positive Unused private fields should be removed
    private int  $position = 0;                                                                                                                                                                                                                                                     // Is this ArrayObject the last child element of a parent ArrayObject?  // NOSONAR - Ignore S1068 false-positive Unused private fields should be removed

    /**
     * Method to toggle the ArrayObject's STD_PROP_LIST flag
     *
     * @param string $name
     * @param bool|int $value
     * @return void
     */
    private function setProp(string $name, bool|int $value): void
    {
        $this->setFlags(ArrayObject::STD_PROP_LIST); // Allow access to private/protected properties
        $this->{$name} = $value;
        $this->setFlags(ArrayObject::ARRAY_AS_PROPS); // Hide private/protected properties
    }

    /**
     * @param string $name
     * @return bool|int
     */
    private function getProp(string $name): bool|int
    {
        $this->setFlags(ArrayObject::STD_PROP_LIST); // Allow access to private/protected properties
        $value = $this->{$name};
        $this->setFlags(ArrayObject::ARRAY_AS_PROPS); // Hide private/protected properties
        return $value;
    }

    /**
     * @return bool
     */
    private function isNestedSmartArray(): bool
    {
        return $this->getIterator()->current() instanceof self; // check if first element is a SmartArray
    }

    #endregion
    #region Error Handling

    /**
     * @return void
     */
    private function assertFlatArray(): void
    {
        if ($this->count() > 0 && $this->isNestedSmartArray()) {
            $function = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
            $error    = "$function(): Expected a flat array, but got a nested SmartArray";
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * @return void
     */
    private function assertNestedArray(): void
    {
        if ($this->count() > 0 && !$this->isNestedSmartArray()) {
            $function = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
            $error    = "$function(): Expected a nested SmartArray, but got a flat array";
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * Throws a PHP warning if the specified key isn't in the list of keys, but only if the array isn't empty.
     * Throws PHP warning if the column is missing in the array to help debugging, but only if the array is not empty
     *
     * @param string|int $key
     * @param string $warningType
     * @return void
     */
    public function warnIfMissing(string|int $key, string $warningType): void
    {
        // Skip warning if the array is empty or key exists
        if ($this->count() === 0 || $this->offsetExists($key)) {
            return;
        }

        // If array isn't empty and key doesn't exist, throw warning to help debugging
        // Note that we only check when array is not empty, so we don't throw warnings for every column on empty arrays
        $caller       = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $function     = $caller['function'] ?? "unknownFunction";
        $inFileOnLine = sprintf("in %s on line %s", $caller['file'], $caller['line']);

        $warning = match ($warningType) {
            'offset'   => "Undefined property or array key '$key' $inFileOnLine.\n", // ArrayObject treats properties as offsets
            'argument' => "$function(): '$key' doesn't exist $inFileOnLine.\n",
            default    => throw new InvalidArgumentException("Invalid warning type '$warningType'"),
        };

        // Catch if user tried to call a method in a double-quoted string without braces
        if (is_string($key) && method_exists($this, $key)) { // Catch cases such as "Nums: $users->pluck('num')->join(',')->value();" which are missing braces
            $warning .= "\nIn double-quoted strings, use \"\$var->property\" for properties, but wrap methods and array access in braces like \"{\$var->method()} or {\$var['key']}\"\n";
        } else {
            // But if it's not a method, show a list of valid keys in case they mistyped a property/offset name
            $validKeys = implode(', ', array_keys($this->getArrayCopy()));
            $warning   .= "Valid keys are: $validKeys\n";
        }

        $warning .= "\nFor more info: \$var->help()";

        // output warning and trigger PHP warning (for logging)
        echo "\nWarning: $warning\n\n";           // Output with echo so PHP doesn't add the filename and line number of this function on the end
        @trigger_error($warning, E_USER_WARNING); // Trigger a PHP warning but hide output with @ so it will still get logged
    }

    #endregion
}
