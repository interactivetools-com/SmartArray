<?php
declare(strict_types=1);
namespace Itools\SmartArray;

use Throwable, Error, RuntimeException, InvalidArgumentException;
use ArrayObject, Iterator, JsonSerializable, Closure;
use Itools\SmartString\SmartString;

/**
 * SmartArray - Represent an array as an ArrayObject with extra features and a fluent, chainable interface.
 *
 * When SmartStrings are disabled (default):
 *   - Scalars and null return actual types (string, int, float, bool, null), not SmartString objects, not chainable
 *   - Arrays return SmartArrayRaw, use ->toArray() for raw arrays and values
 *   - Missing keys return SmartNull, use ->value() for raw null
 *
 * When SmartStrings are enabled:
 *   - Scalars and null return SmartString objects, not raw types.  Use ->value() to get raw value.
 *   - Arrays return SmartArrayHTML, use ->toArray() for raw arrays and values
 *   - Missing keys return SmartNull, use ->value() for raw null
 */
class SmartArray extends ArrayObject implements JsonSerializable
{
    //region Global Settings

    /**
     * Controls whether array key access warnings are shown for missing keys
     */
    public static bool $warnIfMissing = true;

    /**
     * Controls whether deprecation notices are logged
     */
    public static bool $logDeprecations = false;

    //endregion
    //region Creation and Conversion

    /**
     * Constructs a new SmartArray object from an array, recursively converting each element to either a SmartString
     * or a nested SmartArray. It also sets special properties for nested SmartArrays to indicate their position
     * within the root array.
     *
     * @param array $array The input array to convert into a SmartArray.
     * @param bool|array $properties either a boolean to enable/disable SmartStrings, or an associative array of custom internal properties.
     *
     * @noinspection UnusedConstructorDependenciesInspection
     */
    public function __construct(array $array = [], bool|array $properties = [])
    {
        // Create new empty ArrayObject with object property writing enabled (STD_PROP_LIST)
        parent::__construct([], ArrayObject::STD_PROP_LIST);

        // Convert boolean to array format for backward compatibility
        if (is_bool($properties)) {
            self::logDeprecation("Passing boolean to SmartArray constructor is deprecated. Use ->asHtml() for HTML-safe SmartStrings or ->asRaw() for raw values.");
            $properties = ['useSmartStrings' => $properties];
        }

        // Set properties
        foreach ($properties as $property => $value) {
            $this->{$property} = $value;
        }
        $this->root ??= $this;  // Set root property to self if not already set

        // Add elements
        foreach ($array as $key => $value) {
            $this->offsetSet($key, $value);  // Converts nested arrays to SmartArrays
        }

        // Set position properties on child SmartArrays
        $count    = count($array);
        $position = 0;
        foreach (array_keys($array) as $key) {
            $position++;

            // Update child SmartArrays (rows)
            $row = parent::offsetGet($key); // Get raw value from parent, unmodified by our offsetGet method
            if ($row instanceof self) {
                $row->setFlags(ArrayObject::STD_PROP_LIST); // ArrayObject: Switch properties to allow standard property access (not array storage)
                $row->position = $position;
                $row->isFirst  = $position === 1;
                $row->isLast   = $position === $count;
                $row->setFlags(ArrayObject::ARRAY_AS_PROPS); // ArrayObject: Switch properties back to refer to internal array storage keys
            }
        }

        $this->setFlags(ArrayObject::ARRAY_AS_PROPS);           // ARRAY_AS_PROPS: Object properties refer to internal array storage keys (not object properties)
    }

    /**
     * Create a new SmartArray with optional SmartString wrapping.
     * This is the default factory method for creating SmartArrays.
     *
     * ```
     * // For internal data processing and JSON APIs (raw values)
     * $data = SmartArray::new($records)->indexBy('id');
     * echo json_encode($data); // Raw values, no HTML encoding
     * ```
     *
     * @param array $array The input array to convert
     * @param array|bool $properties Optional properties array, or boolean for backward compatibility (deprecated)
     * @return SmartArrayRaw|SmartArrayHtml A SmartArray that returns raw values (or SmartStrings for backward compatibility)
     */
    public static function new(array $array = [], array|bool $properties = []): SmartArrayRaw|SmartArrayHtml
    {
        // Backward compatibility: handle boolean for SmartStrings toggle
        if (is_bool($properties)) {
            match ($properties) {
                true  => self::logDeprecation("Passing `true` as the second argument to SmartArray::new(...) is deprecated. Use ::new(...)->asHtml() or `new SmartArrayHtml` instead."),
                false => self::logDeprecation("Passing `false` as the second argument to SmartArray::new(...) is deprecated. Use ::new(...)->asRaw() or `new SmartArrayRaw` instead."),
            };
            $properties = ['useSmartStrings' => $properties];
        }

        // Return appropriate type based on useSmartStrings
        return match ($properties['useSmartStrings'] ?? false) {
            true  => new SmartArrayHtml($array, $properties),
            false => new SmartArrayRaw($array, $properties),
        };
    }

    /**
     * Return values as raw PHP types for data processing.
     *
     * Explicitly guarantees SmartArrayRaw return type for IDE autocomplete and static analysis.
     * Returns the same object if already SmartArrayRaw (lazy conversion), otherwise creates a new one.
     * Useful both for conversion and type assertion.
     *
     * ```php
     * $rows = DB::select('users'); // Returns SmartArrayHtml
     * $raw = $rows->asRaw();        // Return values as raw PHP types
     * $same = $raw->asRaw();        // Returns same object (no conversion needed)
     * ```
     *
     * @return SmartArrayRaw This object if already raw, or a new SmartArrayRaw instance
     */
    public function asRaw(): SmartArrayRaw
    {
        if ($this instanceof SmartArrayRaw) {
            return $this;
        }
        return new SmartArrayRaw($this->toArray(), get_object_vars($this));
    }

    /**
     * Return values as HTML-safe SmartString objects.
     *
     * Explicitly guarantees SmartArrayHtml return type for IDE autocomplete and static analysis.
     * Returns the same object if already SmartArrayHtml (lazy conversion), otherwise creates a new one.
     * Useful both for conversion and type assertion.
     *
     * ```php
     * $data = SmartArray::new($array); // Raw values
     * $html = $data->asHtml();         // Return values as HTML-safe SmartStrings
     * $same = $html->asHtml();         // Returns same object (no conversion needed)
     * ```
     *
     * @return SmartArrayHtml This object if already HTML-safe, or a new SmartArrayHtml instance
     */
    public function asHtml(): SmartArrayHtml
    {
        if ($this instanceof SmartArrayHtml) {
            return $this;
        }
        return new SmartArrayHtml($this->toArray(), get_object_vars($this));
    }

    //endregion
    //region Value Access

    /**
     * Retrieves an element from the SmartArray, or a SmartNull if not found, providing an alternative to $array[$key] or $array->key syntax.
     * If the key doesn't exist, a SmartNull object is returned to allow further chaining.
     */
    public function get(int|string $key, mixed $default = null): SmartArray|SmartNull|SmartString|string|int|float|bool|null
    {
        // return default if key not found
        if (func_num_args() >= 2 && !$this->offsetExists($key)) {
            $isDefaultSmartObject = $default instanceof self || $default instanceof SmartString;
            return match (true) {
                is_scalar($default), is_null($default) => $this->encodeOutput($default, $key, $this->getProperty('useSmartStrings')),
                is_array($default)                     => new static($default, get_object_vars($this)),
                $isDefaultSmartObject                  => $default,
                default                                => throw new InvalidArgumentException("Unsupported default value type: " . get_debug_type($default)),
            };
        }

        // skip if empty
        if ($this->count() === 0) {
            return $this->newSmartNull();
        }

        // Return via offsetGet if key exists, manually handle non-existent keys for no-default case
        if ($this->offsetExists($key)) {
            return $this->offsetGet($key);
        }

        // Show warning if key doesn't exist (only when no default provided)
        if (self::$warnIfMissing) {
            $this->warnIfMissing($key, 'offset');
        }

        return $this->newSmartNull();
    }

    /**
     * Get first element in array, or SmartNull if array is empty (to allow for further chaining).
     */
    public function first(): SmartArray|SmartString|SmartNull|string|int|float|bool|null
    {
        return $this->nth(0);
    }

    /**
     * Get last element in array, or SmartNull if array is empty (to allow for further chaining).
     */
    public function last(): SmartArray|SmartNull|SmartString|string|int|float|bool|null
    {
        return $this->nth(-1);
    }

    /**
     * Get an element by its position in the array, ignoring keys.
     *
     * Uses zero-based indexing (0=first, 1=second) and negative indices (-1=last, -2=second-to-last).
     * Returns SmartNull if out of bounds.
     *
     * Useful for MySQL queries with unaliased columns:
     * ```
     * $result = DB::query("SELECT MAX(`order`) FROM `uploads`");
     * $max    = $result->first()->nth(0)->value(); // Get "MAX(`order`)" column
     * ```
     */
    public function nth(int $index): SmartArray|SmartNull|SmartString|string|int|float|bool|null
    {
        $count = count($this);
        $index = ($index < 0) ? $count + $index : $index; // Convert negative indexes to positive
        $keys  = array_keys($this->getArrayCopy());

        if (array_key_exists($index, $keys)) {
            return $this->offsetGet($keys[$index]);
        }

        return $this->newSmartNull();
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
     * Note: If you add a key after the array is created the position properties will not be updated.  If needed you can recreate the
     * array like this: $newArray = SmartArray::new($oldArray->toArray());
     *
     * @param mixed $key The key or property name to set. If null, the value is appended to the array.
     * @param mixed $value The value to set. Will be converted to SmartString or SmartArray as appropriate.
     *
     * @throws InvalidArgumentException If an unsupported value type is provided.
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        // Store scalars and nulls as-is (encoded on access by offsetGet)
        if (is_scalar($value) || is_null($value)) {
            parent::offsetSet($key, $value);
            return;
        }

        // Convert nested arrays to SmartArrays (preserving the current class type)
        if (is_array($value)) {
            $value = new static($value, get_object_vars($this));
            parent::offsetSet($key, $value);
            return;
        }

        // Throw an exception for unsupported types or anything else
        $error = sprintf("%s: SmartArray doesn't support %s values. Key %s", __METHOD__, get_debug_type($value), $key);
        throw new InvalidArgumentException($error);
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
     *
     * @noinspection SpellCheckingInspection // ignore lowercase method names in match block
     */
    public function offsetGet(mixed $key, ?bool $useSmartStrings = null): SmartArray|SmartNull|SmartString|string|int|float|bool|null
    {
        $key             = self::getRawValue($key); // Convert SmartString keys to raw values
        $useSmartStrings ??= $this->getProperty('useSmartStrings');

        // Return value if key exists, or SmartNull if not found
        if ($this->offsetExists($key)) {
            $value = parent::offsetGet($key);
            return $this->encodeOutput($value, $key, $useSmartStrings);
        }

        // Show warning if key doesn't exist and array isn't empty
        if (self::$warnIfMissing) {
            $this->warnIfMissing($key, 'offset');
        }

        return $this->newSmartNull();
    }

    /**
     * Converts Smart* objects to their original values while leaving other types unchanged,
     * useful if you don't know the type but want the original value.
     */
    public static function getRawValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof SmartString      => $value->value(),
            $value instanceof self             => $value->toArray(),
            $value instanceof SmartNull        => null,
            is_scalar($value), is_null($value) => $value,
            is_array($value)                   => array_map([self::class, 'getRawValue'], $value), // for manually passed in arrays
            default                            => throw new InvalidArgumentException("Unsupported value type: " . get_debug_type($value)),
        };
    }

    //endregion
    //region Array Information

    /**
     * Check if array has no elements.
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Check if array has any elements.
     */
    public function isNotEmpty(): bool
    {
        return $this->count() !== 0;
    }

    /**
     * Check if array contains a specific value.
     */
    public function contains(mixed $value): bool
    {
        return in_array(self::getRawValue($value), $this->toArray());
    }

    //endregion
    //region Position & Layout

    /**
     * Checks if first element in root, false if no root.
     */
    public function isFirst(): bool
    {
        return $this->getProperty('isFirst');
    }

    /**
     * Checks if last element in root, false if no root.
     */
    public function isLast(): bool
    {
        return $this->getProperty('isLast');
    }

    /**
     * Get position in root (starting at 1, unrelated to keys).  Returns 0 if no root.
     */
    public function position(): int
    {
        return $this->getProperty('position');
    }

    /**
     * Returns true for every nth element (positions start at 1).
     * For example, isMultipleOf(3) matches every 3rd element.
     * Useful for creating grid layouts or other repeating patterns.
     */
    public function isMultipleOf(int $value): bool
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("Value must be greater than 0.");
        }
        return $this->position() % $value === 0;
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
        return new static($chunks, get_object_vars($this));
    }

    //endregion
    //region Sorting & Filtering

    /**
     * Returns a new array sorted by values, using PHP sort() function.
     */
    public function sort(int $flags = SORT_REGULAR): SmartArray
    {
        $this->assertFlatArray();

        $sorted = $this->toArray();
        sort($sorted, $flags);
        return new static($sorted, get_object_vars($this));
    }

    /**
     * Returns a new SmartArray sorted by the specified column, using PHP array_multisort().
     */
    public function sortBy(string $column, int $type = SORT_REGULAR): SmartArray
    {
        $this->assertNestedArray();
        if ($this->first() instanceof self) {
            $this->first()->warnIfMissing($column);
        }


        // sort by key
        $sorted       = $this->toArray();
        $columnValues = array_column($sorted, $column);
        array_multisort($columnValues, SORT_ASC, $type, $sorted);

        return new static($sorted, get_object_vars($this));
    }

    /**
     * Returns a new array with duplicate values removed, keeping only the first
     * occurrence of each unique value, and preserving keys.
     */
    public function unique(): SmartArray
    {
        $this->assertFlatArray();

        $unique = array_unique($this->toArray());
        return new static($unique, get_object_vars($this));
    }

    /**
     * Filters elements of the SmartArray using a callback function and returns a new SmartArray with the results.
     *
     * The callback received both value and key, and should return true to keep the element, false to remove it.
     *
     * The callback function receives raw values (arrays, strings, numbers) instead of SmartString or SmartArray objects,
     * and should return a boolean indicating whether to include the element in the result.
     *
     * @param callable|null $callback A function that tests each element. Should return true to keep the element, false to remove it.
     *
     * @return self A new SmartArray containing only the elements that passed the callback test.
     */
    public function filter(?callable $callback = null): self
    {
        $values = array_filter($this->toArray(), $callback, ARRAY_FILTER_USE_BOTH);
        return new static($values, get_object_vars($this));
    }

    /**
     * Returns a new SmartArray containing only the array elements where all conditions match.
     * Elements that are not arrays are automatically skipped.
     *
     * Uses loose comparison (==) to allow matching between different types (e.g., '1' == 1).
     *
     * Examples:
     *   $data->where(['status' => 'active'])           // Array of conditions
     *   $data->where(['status' => 'active', 'type' => 'user'])  // Multiple conditions
     *   $data->where('status', 'active')               // Two arguments shorthand
     *
     * @param array|string $conditions Key-value pairs to match against each element, or field name if using two-arg syntax
     * @param mixed $value Optional value when using two-argument syntax
     * @return SmartArray A new SmartArray containing only matching elements
     */
    public function where(array|string $conditions, mixed $value = null): SmartArray
    {
        // Convert two-argument syntax to array format
        if (is_string($conditions) && func_num_args() === 2) {
            $conditions = [$conditions => $value];
        }

        // Type check after conversion
        if (!is_array($conditions)) {
            throw new InvalidArgumentException(
                "SmartArray::where() expects an array of conditions or two arguments (field, value). Got: " . gettype($conditions)
            );
        }

        // Guard against common mistake: passing ['field', 'value'] instead of ['field' => 'value']
        // Prevents silent failures where filter returns empty results with no explanation
        if (!empty($conditions) && array_is_list($conditions)) {
            throw new InvalidArgumentException(
                "SmartArray::where() expects an associative array of key=>value pairs. " .
                "Got a list array. Use format: ['field' => 'value'] not ['field', 'value']"
            );
        }

        // Filter rows that match all conditions
        $filtered = array_filter($this->toArray(), static function ($row) use ($conditions) {
            // skip elements that are not arrays
            if (!is_array($row)) {
                return false;
            }

            // check if all conditions are met (using non-strict comparison)
            foreach ($conditions as $key => $value) {
                /** @noinspection TypeUnsafeComparisonInspection */
                if (!array_key_exists($key, $row) || $row[$key] != $value) {    // intentional non-strict comparison
                    return false;
                }
            }
            return true;
        });

        return new static($filtered, get_object_vars($this));
    }

    //endregion
    //region Array Transformation

    /**
     * Recursively converts SmartArray back to a standard PHP array with original values.
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
        foreach ($this->getArrayCopy() as $key => $value) {  // getArrayCopy so getIterator doesn't convert everything to SmartStrings
            $array[$key] = match (true) {
                $value instanceof self             => $value->toArray(),   // Recursively convert nested SmartArrays
                is_scalar($value), is_null($value) => $value,              // Scalars and nulls are returned as-is
                $value instanceof SmartNull        => null,                // Convert SmartNull to null
                default                            => throw new InvalidArgumentException(__METHOD__ . ": Unexpected value type encountered: " . get_debug_type($value)),
            };
        }

        return $array;
    }

    /**
     * Returns a new array of keys
     */
    public function keys(): SmartArray
    {
        $keys = array_keys($this->getArrayCopy());
        return new static($keys, get_object_vars($this));
    }

    /**
     * Returns a new array of values
     */
    public function values(): SmartArray
    {
        $values = array_values($this->toArray());
        return new static($values, get_object_vars($this));
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
        $this->assertNestedArray();
        if ($this->first() instanceof self) {
            $this->first()->warnIfMissing($column);
        }

        // Index by column
        $values = array_column($this->toArray(), null, $column);
        return new static($values, get_object_vars($this));
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
        $this->assertNestedArray();
        if ($this->first() instanceof self) {
            $this->first()->warnIfMissing($column);
        }

        $values = [];
        foreach ($this->toArray() as $row) {
            $key            = $row[$column];
            $values[$key][] = $row;
        }

        return new static($values, get_object_vars($this));
    }

    /**
     * Extracts a single column from a nested SmartArray.
     *
     * This method retrieves the values of a specified key from all elements in the nested SmartArray,
     * returning them as a new SmartArray. It's particularly useful for extracting a specific field
     * from a collection of records.
     *
     * @param string|int $valueColumn The key of the column to extract from each nested element.
     * @return SmartArray A new SmartArray containing the extracted values.
     * @example
     * $users = new SmartArray([
     *     ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
     *     ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']
     * ]);
     * $userEmails = $users->pluck('email');                        // $userEmails is now a SmartArray: ['john@example.com', 'jane@example.com']
     * $csvEmails  = $users->pluck('email')->implode(', ')->value(); // $csvEmails is now a string: "john@example.com, jane@example.com"
     */
    public function pluck(string|int $valueColumn, ?string $keyColumn = null): SmartArray
    {
        $this->assertNestedArray();
        if ($this->first() instanceof self) {
            $this->first()->warnIfMissing($valueColumn);
        }

        $values = array_column($this->toArray(), $valueColumn, $keyColumn);
        return new static($values, get_object_vars($this));
    }

    /**
     * Extracts values at a specific position from each row in a nested SmartArray, ignoring key names.
     * Particularly useful for MySQL results where key names are unpredictable, like SHOW TABLES.
     *
     * @param int $index
     * @return SmartArray A new SmartArray containing the extracted values.
     *
     * @example MySQL `SHOW TABLES LIKE 'cms_%'` returns:
     *
     * [
     *   ['Tables_in_yourDbName (cms_%)' => 'cms_accounts'],
     *   ['Tables_in_yourDbName (cms_%)' => 'cms_settings']
     *   ['Tables_in_yourDbName (cms_%)' => 'cms_pages'],
     * ]
     *
     * $tables = $resultSet->pluckNth(0);   // Position 0 (first value): Returns ["cms_accounts", "cms_settings", "cms_pages"]
     */
    public function pluckNth(int $index): SmartArray
    {
        $this->assertNestedArray();

        $values = [];
        foreach ($this->toArray() as $row) {
            $count    = count($row);
            $rowIndex = ($index < 0) ? $count + $index : $index; // Convert negative indexes to positive

            if ($rowIndex >= 0 && $rowIndex < $count) {
                $values[] = array_values($row)[$rowIndex];
            }
        }
        return new static($values, get_object_vars($this));
    }

    /**
     * Mirrors PHP's array_column() - extract a column of values, optionally indexed by another column.
     *
     *     $arr->column('name');        // same as array_column($arr, 'name'), returns values from 'name' column
     *     $arr->column('name', 'id');  // same as array_column($arr, 'name', 'id'), returns id => name mapping
     *     $arr->column(null, 'id');    // same as array_column($arr, null, 'id'), returns rows indexed by 'id'
     *
     * @param int|string|null $columnKey Column to extract (null = entire rows via indexBy)
     * @param int|string|null $indexKey  Column to use as array keys
     * @return SmartArray
     */
    public function column(int|string|null $columnKey, int|string|null $indexKey = null): SmartArray
    {
        return match (true) {
            $columnKey !== null && $indexKey === null => $this->pluck($columnKey),
            $columnKey !== null && $indexKey !== null => $this->pluck($columnKey, (string)$indexKey),
            $columnKey === null && $indexKey !== null => $this->indexBy((string)$indexKey),
            default                                   => throw new RuntimeException("column() unexpected arguments"),
        };
    }

    /**
     * Joins the elements of the SmartArray into a single string with a specified separator.
     *
     * This method works on flat SmartArrays only. For SmartString elements,
     * their original values are used in the resulting string.
     *
     * @param string $separator The string to use as a separator between elements.
     *
     * @return SmartString|string The resulting string after joining all elements.
     * @throws InvalidArgumentException If the SmartArray is nested.
     *
     * @example
     * $arr = new SmartArray(['apple', 'banana', 'cherry']);
     * $result = $arr->implode(', '); // Returns SmartString: "apple, banana, cherry"
     */
    public function implode(string $separator = ''): SmartString|string
    {
        $this->assertFlatArray();

        $values = array_map('strval', $this->toArray());
        $value  = implode($separator, $values);

        return $this->encodeOutput($value, null, $this->getProperty('useSmartStrings'));
    }

    /**
     * Applies sprintf formatting to each element and returns SmartArrayRaw.
     *
     * - For SmartArrayHtml input, values are automatically HTML-encoded (safe for HTML output)
     * - For SmartArrayRaw input, values pass through as-is
     * - Format string is not encoded (only the values are)
     *
     * Example:
     *
     *     $row = SmartArray::new(["O'Brien", '<script>'])->asHtml();
     *     <tr><?= $row->sprintf("<td>%s</td>")->implode() ?></tr>
     *     // Output: <tr><td>O&apos;Brien</td><td>&lt;script&gt;</td></tr>
     *
     * @param string $format The sprintf format string (e.g., "<td>%s</td>")
     * @return SmartArrayRaw Pre-formatted strings (won't be re-encoded on output)
     */
    public function sprintf(string $format): SmartArrayRaw
    {
        $this->assertFlatArray();

        $newArray = [];
        foreach ($this as $key => $value) {
            $value          = $value instanceof SmartString ? $value->htmlEncode() : $value;
            $newArray[$key] = sprintf($format, $value);
        }

        return new SmartArrayRaw($newArray, get_object_vars($this));
    }

    /**
     * Applies a callback to each element *as raw PHP values* (i.e., unwrapped scalars/arrays)
     * and returns a new SmartArray with the results.
     *
     * The callback receives two parameters if it is a Closure:
     *   - $value (the raw element from ->toArray())
     *   - $key   (integer or string)
     *
     * If it's a built-in function or a non-closure callable, only the $value is passed to avoid
     * accidental interpretation of $key as an extra parameter. For example, calling `intval($value, $key)`
     * might parse the key as the base argument, leading to unexpected results.
     *
     * Preserves array keys in the returned SmartArray.
     *
     * @param callable $callback A function/callable to transform each element.
     *                           Signature if Closure: fn($value, $key) => mixed
     *                           Signature if non-Closure: fn($value) => mixed
     *
     * @return self A new SmartArray containing the transformed elements.
     *
     * @example
     *  $arr = new SmartArray(['apple', 'banana', 'cherry']);
     *  $upper = $arr->map(fn(string $fruit) => strtoupper($fruit));
     *  // $upper is now a SmartArray: ['APPLE', 'BANANA', 'CHERRY']
     *
     * @example
     *  $nested = new SmartArray([['a' => 1], ['a' => 2]]);
     *  $values = $nested->map(fn(array $item) => $item['a']);
     *  // $values is now a SmartArray: [1, 2]
     */
    public function map(callable $callback): self
    {
        $newArray  = [];
        $isClosure = $callback instanceof Closure;
        foreach ($this->toArray() as $key => $rawValue) {
            // For closures, pass both $value and $key, but not for non-Closure callbacks to avoid unexpected behavior, e.g., intval($value, $base) would misinterpret $key as $base
            $newArray[$key] = $isClosure ? $callback($rawValue, $key) : $callback($rawValue);
        }

        return new static($newArray, get_object_vars($this));
    }

    /**
     * Applies a callback to each element *as Smart objects* (i.e., SmartString or nested SmartArray),
     * and returns a new SmartArray with the results.
     *
     * The callback receives two parameters:
     *   - $value SmartString|SmartArray
     *   - $key   (int|string, the array key)
     *
     * Because built-in PHP functions may not expect these Smart objects (and could fail or behave
     * unpredictably), this method restricts to Closures, which can handle them safely.
     *
     * Preserves array keys in the returned SmartArray.
     *
     * Note: When using arrow functions (fn()), use print instead of echo for output.
     * Echo cannot be used in arrow function expressions.
     *
     * @param Closure $callback A closure with signature: fn($smartValue, $key) => mixed
     *
     * @return self A new SmartArray containing the transformed elements.
     *
     * @example
     *  $arr = new SmartArray(['hello', 'world'], true); // with SmartStrings
     *  $exclaimed = $arr->smartMap(fn($str, $k) => $str->upper()->append('!'));
     *  // $exclaimed -> ['HELLO!', 'WORLD!']
     */
    public function smartMap(closure $callback): self
    {
        $newArray        = [];
        $useSmartStrings = $this->getProperty('useSmartStrings');

        foreach (array_keys($this->getArrayCopy()) as $key) {
            $smartValue     = $this->offsetGet($key, $useSmartStrings);
            $newArray[$key] = $callback($smartValue, $key);
        }

        return new static($newArray, get_object_vars($this));
    }

    /**
     * Calls the given callback on each element in the SmartArray (as SmartString or nested SmartArray),
     * primarily for side effects. Returns $this for chaining.
     *
     * @param closure $callback A callback with the signature: fn(SmartString|SmartArray $value, int|string $key): void
     * @return $this
     *
     * Example:
     *  $users = new SmartArray($results, true);
     *  $users->each(function($user, $key) {
     *      echo "$user->num - $user->name\n";
     *  });
     *
     * If you need to transform or collect results, consider ->map() or ->smartMap() instead.
     */
    public function each(Closure $callback): self
    {
        $useSmartStrings = $this->getProperty('useSmartStrings');

        // Iterate over keys so we can call offsetGet($key, $useSmartStrings) for Smart values
        foreach (array_keys($this->getArrayCopy()) as $key) {
            $smartValue = $this->offsetGet($key, $useSmartStrings);
            $callback($smartValue, $key);
        }

        return $this;
    }

    /**
     * Merges the SmartArray with one or more arrays or SmartArrays.
     * Numeric keys are renumbered, string keys are overwritten by later values.
     *
     * @param array|SmartArray ...$arrays Arrays to merge with
     * @return self Returns a new SmartArray with the merged results
     *
     * @example
     * $arr1 = SmartArray::new(['a' => 1, 'b' => 2]);
     * $arr2 = ['b' => 3, 'c' => 4];
     * $arr3 = SmartArray::new(['d' => 5]);
     *
     * $result = $arr1->merge($arr2, $arr3);
     * // ['a' => 1, 'b' => 3, 'c' => 4, 'd' => 5]
     */
    public function merge(array|SmartArray ...$arrays): self
    {
        $arrays = array_map([self::class, 'getRawValue'], $arrays); // convert SmartArrays to arrays
        $merged = array_merge($this->toArray(), ...$arrays);
        return new static($merged, get_object_vars($this));
    }


    //endregion
    //region Database Operations

    /**
     * Get mysqli result information for the last database query.
     * Returns specified property (affected_rows, insert_id) or array of all properties if no property specified.
     */
    public function mysqli(?string $property = null): int|string|null|array
    {
        // return array of all mysqli properties
        if (is_null($property)) {
            return $this->getProperty('mysqli') ?? [];
        }

        // return specific mysqli property
        $resultInfo = $this->getProperty('mysqli');
        return $resultInfo[$property] ?? null;
    }

    /**
     * Returns SmartArray or throws an exception load() handler is not available for column
     * @throws RuntimeException
     */
    public function load(string $column): SmartArray|SmartNull
    {
        // return SmartNull if array is empty (or is SmartNull already)
        if ($this->count() === 0) {
            return $this->newSmartNull();
        }

        // get load handler
        $loadHandler = $this->getProperty('loadHandler');

        // error checking
        match (true) {
            !$loadHandler                         => throw new RuntimeException("No loadHandler property is defined"),
            !is_callable($loadHandler)            => throw new RuntimeException("Load handler is not callable"),
            empty($column)                        => throw new InvalidArgumentException("Column name is required for load() method."),
            (bool)preg_match('/[^\w-]/', $column) => throw new RuntimeException("Column name contains invalid characters: $column"),
            $this->isNested()                     => throw new RuntimeException("Cannot call load() on record set, only on a single row."),
            default                               => null,
        };

        // get handler output
        $result = $loadHandler($this, $column);
        if ($result === false) {
            throw new Error("Load handler not available for '$column'\n" . self::occurredInFile());
        }

        // output error checking
        [$array, $mysqliProperties] = $result; // Get new array data
        match (true) {
            !is_array($array)            => throw new Error("Load handler must return an array as the first argument"),
            !is_array($mysqliProperties) => throw new Error("Load handler must return an array as the second argument"),
            default                      => null,
        };

        // return new SmartArray
        return new static($array, [
            'useSmartStrings' => $this->getProperty('useSmartStrings'), // persist smart strings setting
            'loadHandler'     => $this->getProperty('loadHandler'),     // persist load handler
            'mysqli'          => $mysqliProperties ?? [],
            //'root'          => // skipped, set by constructor to self
            //'isFirst'       => // skipped, instance defaults are accurate for root array
            //'isLast'        => // skipped, instance defaults are accurate for root array
            //'position'      => // skipped, instance defaults are accurate for root array
        ]);
    }

    /**
     * Set the load handler for lazy-loading nested arrays.
     * @noinspection PhpUnused
     */
    public function setLoadHandler(callable $customLoadHandler): void
    {
        $this->setProperty('loadHandler', $customLoadHandler);
    }

    /**
     * Return the root SmartArray object for nested arrays, or the current object if not nested.
     */
    public function root(): self
    {
        return $this->getProperty('root');
    }

    //endregion
    //region Debugging and Help

    /**
     * Displays help information about available methods and properties.
     * Loads content from help.txt file.
     */
    public function help(): void
    {
        $helpPath = __DIR__ . '/help.txt';

        if (is_file($helpPath)) {
            $docs = file_get_contents($helpPath);
        } else {
            $docs = "SmartArray help documentation not found.\nExpected location: $helpPath";
        }

        echo self::xmpWrap("\n$docs\n\n");
    }

    /**
     * Displays help information about available methods and properties.
     * @throws RuntimeException
     */
    public function debug($debugLevel = 0): void
    {
        // show data header
        $className = self::class;
        $output    = match ($this->getProperty('useSmartStrings')) {
            true  => "$className - Values are returned as **SmartStrings** on access\n\n",
            false => "$className - Values are returned **as-is** on access (no extra encoding)\n\n",
        };

        // Show mysqli query
        if ($this->mysqli('query')) {
            $query  = preg_replace("/^/m", "    ", $this->mysqli('query')); // indent query
            $output .= "MySQL Query:\n$query\n\nArray ";
        }

        // show data
        $output .= self::prettyPrintR($this, $debugLevel);

        // Show mysqli metadata
        if ($this->mysqli()) {
            $output   .= "\n";
            $metadata = $this->mysqli();
            if (array_key_exists('query', $metadata)) {
                $metadata['query'] = preg_replace("/\s+/", " ", trim((string)$metadata['query'])); // remove extra spaces
            }
            $output .= self::prettyPrintR($metadata, $debugLevel, 0, "MySQLi Metadata ");
        }

        // show properties
        if ($debugLevel > 0) {
            $output             .= "\n";
            $properties         = get_object_vars($this); // gets public properties
            $properties['root'] = get_debug_type($properties['root']) . " #" . spl_object_id($properties['root']);
            $output             .= self::prettyPrintR($properties, $debugLevel, 0, "Object Properties");
            $output             = preg_replace("/^(\s+'root'\s+=> ).*?(\d+).*?$/m", "$1SmartArray #$2", $output); // format root property as: SmartArray #123
        }

        $output .= "\n";
        echo self::xmpWrap($output);
    }


    private static function prettyPrintR(mixed $var, int $debugLevel = 0, int $depth = 0, string $keyPrefix = '', string $loadComment = ""): array|string|null
    {
        $indent        = $depth ? '    ' : '';
        $commentOffset = $debugLevel > 0 ? 81 - (strlen($indent) * $depth) : 0;

        // get var type
        $debugType = basename(get_debug_type($var));
        $comment   = $debugLevel > 0 ? " // $debugType" : "";

        // get output

        if ($var instanceof self || is_array($var)) {
            $arrayCopy    = is_array($var) ? $var : $var->getArrayCopy();
            $maxKeyLength = max(array_map('strlen', array_filter(array_keys($arrayCopy), 'is_string')) + [0]) + 2; // skip numeric keys

            if ($debugLevel > 0 && $var instanceof self) {
                $self    = $var === $var->root() ? " (self)" : "";
                $comment = rtrim($comment) . sprintf(" #%s, Root #%s%s", spl_object_id($var), spl_object_id($var->root()), $self);
            }

            $output = sprintf("%-{$commentOffset}s%s\n", $keyPrefix . "[", $comment);
            foreach ($arrayCopy as $key => $value) {
                $wrappedKey    = is_int($key) ? "[$key]" : "'$key'";
                $thisKeyPrefix = str_pad($wrappedKey, $maxKeyLength) . " => ";

                // add load comment
                $loadComment = "";
                $loadResult  = false;
                try {
                    $loadResult = $var->load($key);
                } catch (Throwable) {
                    // ignore errors
                }
                if ($loadResult !== false && !$loadResult instanceof SmartNull) {
                    $loadComment = " // ->load('$key') for more";
                }

                // get output
                $output .= self::prettyPrintR($value, $debugLevel, $depth + 1, $thisKeyPrefix, $loadComment);
            }
            $output = preg_replace("|,(\s*//.*)?$|", " $1", $output); // Remove trailing commas
            $output .= $depth ? "],\n" : "]\n";                       // skip trailing comma on top level
        } elseif (is_scalar($var) || is_null($var)) {
            $hasTabs     = is_string($var) && str_contains($var, "\t");
            $varExport   = match (true) {
                is_null($var) => "null",
                is_bool($var) => $var ? "true" : "false",
                !$debugLevel  => $var,                                         // Show raw values without quotes for compact mode
                $hasTabs      => '"' . addcslashes($var, "\t\"\0\$\\") . '"',  // Show tabs as \t for readability
                default       => var_export($var, true),
            };
            $varExport   .= $debugLevel ? "," : "";                                         // add trailing comma for debug mode > 0
            $loadComment = str_repeat(" ", max(12 - strlen($varExport), 0)) . $loadComment; // line up after common short value lengths
            $output      = str_pad("$keyPrefix$varExport$loadComment", $commentOffset) . "$comment\n";
        } elseif ($var instanceof SmartNull) {
            $varExport = 'SmartNull()';
            $output    = str_pad("$keyPrefix$varExport,", $commentOffset) . "$comment\n";
        } else {
            throw new RuntimeException("Unsupported type: $debugType");
        }

        // Indent each line
        return preg_replace("/^/m", $indent, $output);
    }

    /**
     * Wrap output in <xmp> tag if text/html and not called from a function that already added <xmp>
     * @noinspection SpellCheckingInspection // ignore all lowercase strtolower function name
     */
    private static function xmpWrap($output): string
    {
        $output             = trim($output, "\n");
        $headersList        = implode("\n", headers_list());
        $hasContentType     = (bool)preg_match('|^\s*Content-Type:\s*|im', $headersList);                          // assume no content type will default to html
        $isTextHtml         = !$hasContentType || preg_match('|^\s*Content-Type:\s*text/html\b|im', $headersList); // match: text/html or ...;charset=utf-8
        $backtraceFunctions = array_map('strtolower', array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'function'));
        $wrapInXmp          = $isTextHtml && !in_array('showme', $backtraceFunctions, true);
        return $wrapInXmp ? "\n<xmp>\n$output\n</xmp>\n" : "\n$output\n";
    }

    /**
     * Show internal array data print_r() is used to examine object.
     *
     * You can temporarily comment out this function to see the properties while debugging.
     */
    public function __debugInfo(): array
    {
        // show help information for root array (but not for every child array)
        $output = [];
        if ($this === $this->root()) {
            // Call ->help() for usage examples and documentation, or ->debug() to view metadata
            $output["README:SmartArray:private"] = "Call \$obj->help() for documentation, or ->debug() to view metadata";
            $output["*useSmartStrings*:private"] = match ($this->getProperty('useSmartStrings')) {
                true  => "true, // Values are returned as SmartString objects on access\n",
                false => "false, // Values are returned **as-is** on access (no extra encoding)\n",
            };
        }

        // show array data
        $output += $this->getArrayCopy();
        return $output;
    }

    /**
     * Type hint for IDEs to enable property autocomplete. Never actually called at runtime.
     *
     * Properties are handled by ArrayObject::offsetGet() instead of __get() due to the
     * ARRAY_AS_PROPS flag. This means $obj->property behaves the same as $obj['property'].
     * @throws RuntimeException
     */
    public function __get(string $key): SmartArray|SmartNull|SmartString|string|int|float|bool|null
    {
        throw new RuntimeException(
            "Property access on SmartArray is handled by ArrayObject::offsetGet() due to ARRAY_AS_PROPS flag. " .
            "If you see this error, something has gone wrong with ArrayObject property handling.",
        );
    }

    //endregion
    //region Error Handling

    /**
     * Sends a 404 header and message if the array is empty, then exits.
     *
     * @param string|null $message The message to display when sending 404.
     * @return self Returns self if not empty, exits with 404 if empty
     */
    public function or404(?string $message = null): self
    {
        if ($this->count() > 0) {
            return $this;
        }

        // Send 404 header and message
        http_response_code(404);
        header("Content-Type: text/html; charset=utf-8");
        $message ??= "The requested URL was not found on this server.";
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        echo <<<__HTML__
            <!DOCTYPE html>
            <html lang>
            <head>
                <title>Not Found</title>
            </head>
            <body>
                <h1>Not Found</h1>
                <p>$message</p>
            </body>
            </html>
            __HTML__;
        exit;
    }

    /**
     * Dies with a message if the array is empty
     *
     * @param string $message Error message to show
     * @return self Returns $this for method chaining if not empty, dies if empty
     */
    public function orDie(string $message): self
    {
        if ($this->count() === 0) {
            $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            die($message);
        }
        return $this;
    }

    /**
     * Throws Exception if the array is empty
     *
     * @param string $message Error message to show
     * @return self Returns $this for method chaining if not empty
     * @throws RuntimeException If array is empty
     */
    public function orThrow(string $message): self
    {
        if ($this->count() === 0) {
            $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            throw new RuntimeException($message);
        }
        return $this;
    }

    /**
     * Redirects to a URL if the array is empty
     *
     * Uses a simple Location header redirect (HTTP 302 Temporary Redirect).
     * If headers have already been sent, this method will throw an exception.
     *
     * @param string $url The URL to redirect to if array is empty
     * @return self Returns $this for method chaining if not empty, redirects if empty
     * @throws RuntimeException If headers have already been sent
     */
    public function orRedirect(string $url): self
    {
        // Check if headers have already been sent (fail fast)
        if (headers_sent($file, $line)) {
            throw new RuntimeException("Cannot redirect: headers already sent in $file on line $line");
        }

        if ($this->count() === 0) {
            // Send redirect headers (302 Temporary Redirect)
            http_response_code(302);
            header("Location: " . $url);
            exit;
        }
        return $this;
    }

    /**
     * Assert that array has no nested arrays
     */
    private function assertFlatArray(): void
    {
        if ($this->count() > 0 && $this->isNested()) {
            $function = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
            $error    = "$function(): Expected a flat array, but got a nested array";
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * Assert that array has at least one nested array in values
     */
    private function assertNestedArray(): void
    {
        if ($this->count() > 0 && $this->isFlat()) {
            $function = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
            $error    = "$function(): Expected a nested array, but got a flat array";
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * Throws a PHP warning if the specified key isn't in the list of keys, but only if the array isn't empty.
     * Throws PHP warning if the column is missing in the array to help debugging, but only if the array is not empty
     *
     * Method argument warnings always display, but property/offset warnings can be toggled with $warnIfMissing.
     *
     * @param string|int $key The key to check for existence
     * @param string $warningType 'offset' for properties, 'argument' for method arguments
     */
    private function warnIfMissing(string|int $key, string $warningType = 'argument'): void
    {
        // Skip warning if the array is empty or if the key exists
        if ($this->count() === 0 || $this->offsetExists($key)) {
            return;
        }

        // For offset access, respect the global toggle
        if ($warningType === 'offset' && !self::$warnIfMissing) {
            return;
        }

        // Always show warnings for method arguments regardless of global setting

        // If array isn't empty and key doesn't exist, throw warning to help debugging
        // Note that we only check when array is not empty, so we don't throw warnings for every column on empty arrays
        $caller           = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $function         = $caller['function'] ?? "unknownFunction";
        $keyOrEmptyQuotes = $key === "" ? "''" : $key; // Show empty quotes for empty string keys

        $warning = match ($warningType) {
            'offset'   => "$keyOrEmptyQuotes is undefined in {$caller['file']}:{$caller['line']}\n",
            'argument' => "$function(): '$key' doesn't exist\n",
            default    => throw new InvalidArgumentException("Invalid warning type '$warningType'"),
        };

        // Catch if user tried to call a method in a double-quoted string without braces
        if (is_string($key) && method_exists($this, $key)) { // Catch cases such as "Nums: $users->pluck('num')->implode(',')->value();" which are missing braces
            $warning .= "\nIn double-quoted strings, use \"\$var->property\" for properties, but wrap methods and array access in braces like \"{\$var->method()} or {\$var['key']}\"";
        }
        if ($warningType === 'argument') {
            $warning .= self::occurredInFile(true);
        }

        // Emulate PHP warning: output warning and trigger PHP warning (for logging)
        echo "\nWarning: $warning\n";                  // Output with echo so PHP doesn't add the filename and line number of this function on the end
        @trigger_error($warning, E_USER_WARNING);      // Trigger a PHP warning but hide output with @ so it will still get logged
    }

    /**
     * Logs a deprecation warning if logging is enabled
     */
    private static function logDeprecation($error): void
    {
        if (self::$logDeprecations) {
            @user_error($error, E_USER_DEPRECATED);  // Trigger a silent deprecation notice for logging purposes
        }
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
        $caller       = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0];
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
     * @param $method
     * @param $args
     * @return mixed
     *
     * @noinspection SpellCheckingInspection // ignore lowercase method names in match block
     */
    public function __call($method, $args)
    {
        $methodLc = strtolower($method);

        // Deprecated Warnings: (optionally) log warning and return proper value.  This will be removed in a future version
        [$return, $deprecationError] = match ($methodLc) {  // use lowercase names below for comparison
            'getcolumn'                              => [null, "Replace ->$method() with ->pluck() or another method"],
            'exists'                                 => [$this->isNotEmpty(), "Replace ->$method() with ->isNotEmpty()"],
            'firstrow', 'getfirst'                   => [$this->first(), "Replace ->$method() with ->first()"],
            'getvalues'                              => [$this->values(), "Replace ->$method() with ->values()"],
            'item'                                   => [$this->get(...$args), "Replace ->$method() with ->get()"],
            'join'                                   => [$this->implode(...$args), "Replace ->$method() with ->implode()"],
            'raw'                                    => [$this->toArray(), "Replace ->$method() with ->toArray()"],
            'withsmartstrings', 'enablesmartstrings' => [$this->asHtml(), "Replace ->$method() with ->asHtml()"],
            'nosmartstrings', 'disablesmartstrings'  => [$this->asRaw(), "Replace ->$method() with ->asRaw()"],
            default                                  => [null, null],
        };
        if ($deprecationError) {
            self::logDeprecation($deprecationError);
            return $return;
        }

        // Common aliases: throw error with suggestion.  These are used by other libraries or common LLM suggestions
        $methodAliases = [
            // value access
            'get'         => ['fetch', 'value'],
            'first'       => ['head'],
            'last'        => ['tail'],
            'nth'         => ['index', 'at'],
            'getRawValue' => ['raw'],

            // emptiness & search
            'isEmpty'     => ['empty'],
            'isNotEmpty'  => ['any', 'not_empty'],
            'contains'    => ['has', 'includes'],

            // position helpers
            'position'    => ['pos'],
            'chunk'       => ['split'],

            // sorting & filtering
            'sort'        => ['order', 'orderby'],
            'unique'      => ['distinct', 'uniq'],
            'filter'      => ['select'],
            'where'       => ['filter_by'],

            // array transforms
            'toArray'     => ['array', 'raw'],
            'keys'        => ['keyset'],
            'values'      => ['vals', 'list'],
            'indexBy'     => ['keyby'],
            'groupBy'     => ['group'],
            'pluckNth'    => ['columnnth'],
            'implode'     => ['join', 'concat'],
            'map'         => ['transform', 'apply'],
            'each'        => ['foreach', 'iterate'],
            'merge'       => ['append', 'union'],

            // utilities
            'help'        => ['docs'],
            'debug'       => ['dump'],
        ];


        // Check if the called method is an alias
        $suggestion = null;
        foreach ($methodAliases as $correctMethod => $aliases) {
            if (in_array($methodLc, $aliases, true)) {
                $suggestion = "did you mean ->$correctMethod()?";
                break;
            }
        }

        // throw unknown method exception
        // PHP Default Error: Fatal error: Uncaught Error: Call to undefined method class::method() in /path/file.php:123
        $suggestion ??= "call ->help() for available methods.";
        $error      = sprintf("Call to undefined method %s->$method(), $suggestion\n", basename(self::class));
        throw new Error($error . self::occurredInFile());
    }

    /**
     * @noinspection SpellCheckingInspection // ignore lowercase method names in match block
     */
    public static function __callStatic($method, $args): mixed
    {
        $methodLc = strtolower($method);

        // Deprecated/renamed methods (case-insensitive)
        [$return, $deprecationError] = match ($methodLc) {
            'rawvalue' => [self::getRawValue(...$args), "Replace ::$method() with ::getRawValue()"],
            'newss'    => [new SmartArrayHtml($args[0] ?? []), "Replace ::$method(...) with SmartArray::new(...)->asHtml()"],
            default    => null,
        };
        if ($deprecationError) {
            self::logDeprecation($deprecationError);
            return $return;
        }

        // throw unknown method exception
        // PHP Default Error: Fatal error: Uncaught Error: Call to undefined method class::method() in /path/file.php:123
        $baseClass = basename(self::class);
        $error     = "Call to undefined method $baseClass->$method(), call ->help() for available methods.\n";
        throw new Error($error . self::occurredInFile());
    }

    /**
     * Add "Occurred in file:line" to the end of the error messages with the first non-SmartArray file and line number.
     */
    private static function occurredInFile($addReportedFileLine = false): string
    {
        $file      = "unknown";
        $line      = "unknown";
        $inMethod  = "";
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Add Occurred in file:line
        foreach ($backtrace as $index => $caller) {
            if (empty($caller['file']) || $caller['file'] !== __FILE__) {
                $file       = $caller['file'] ?? $file;
                $line       = $caller['line'] ?? $line;
                $prevCaller = $backtrace[$index + 1] ?? [];
                $inMethod   = match (true) {
                    !empty($prevCaller['class'])    => " in {$prevCaller['class']}{$prevCaller['type']}{$prevCaller['function']}()",
                    !empty($prevCaller['function']) => " in {$prevCaller['function']}()",
                    default                         => "",
                };
                break;
            }
        }
        $output = "Occurred in $file:$line$inMethod\nReported";

        // Add Reported in file:line (if requested)
        if ($addReportedFileLine) {
            $method       = basename($backtrace[1]['class']) . $backtrace[1]['type'] . $backtrace[1]['function'];
            $reportedFile = $backtrace[0]['file'] ?? "unknown";
            $reportedLine = $backtrace[0]['line'] ?? "unknown";
            $output       .= " in $reportedFile:$reportedLine in $method()\n";
        }

        // return output
        return $output;
    }

    //endregion
    //region Internal Methods

    /**
     * Return SmartArrays as is, and raw values as SmartStrings if that's enabled, otherwise as raw
     */
    private function encodeOutput(mixed $value, string|int|null $key = null, bool $useSmartStrings = false): mixed
    {
        static $errorFormat = __METHOD__ . ": SmartArray doesn't support '%s' values.%s";

        return match (true) {
            is_scalar($value) || is_null($value) => $useSmartStrings ? new SmartString($value) : $value,
            $value instanceof self               => $value,
            default                              => throw new InvalidArgumentException(sprintf($errorFormat, get_debug_type($value), !is_null($key) ? " Key '$key'." : "")),
        };
    }

    /**
     * Return a new SmartNull object with the same SmartString setting as the current SmartArray.
     */
    public function newSmartNull(): SmartNull
    {
        return new SmartNull(get_object_vars($this));
    }

    /**
     * Check if array doesn't contain any nested arrays.
     */
    public function isFlat(): bool
    {
        return !$this->isNested();
    }

    /**
     * Check if array contains any nested arrays.  Does not check if all values are arrays, only if any are.
     */
    public function isNested(): bool
    {
        foreach ($this->getArrayCopy() as $value) {
            if ($value instanceof self) {
                return true;
            }
        }
        return false;
    }


    public function getIterator(): Iterator
    {
        // Return an iterator that calls offsetGet for each element
        foreach (array_keys($this->getArrayCopy()) as $key) {
            $value = parent::offsetGet($key);
            yield $key => $this->encodeOutput($value, $key, $this->getProperty('useSmartStrings'));
        }
    }

    /**
     * This function is called by json_encode() via JsonSerializable to get serializable data.
     * Returns an array of original values, not SmartArray or SmartString wrappers.
     *
     * For list-like arrays, produces a JSON "array" instead of ArrayObject's default JSON "object".
     * Associative arrays retain default JSON object serialization.
     *
     * Example of difference:
     * echo json_encode(new ArrayObject(['zero','one','two','three']));  // Output: {"0":"zero","1":"one","2":"two","3":"three"}
     * echo json_encode(new SmartArray(['zero','one','two','three']));   // Output: ["zero","one","two","three"]
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->getArrayCopy();
    }

    //endregion
    //region Instance Properties

    /**
     * PROPERTIES NOTICE:
     * $this is an ArrayObject with ArrayObject::ARRAY_AS_PROPS flag
     * array elements are stored in an inaccessible internal array in $this->storage by PHP's ArrayObject
     * So getting/setting properties updates the stored element values, NOT the properties themselves
     * To maintain separation, internal properties must be private and accessed via getProp() and setProp()
     *
     * These properties are set on creation, passed on to nested SmartArrays, and never changed after creation
     * The noinspections tag hide false positives caused by us setting these properties via getProperty()/setProperty()
     */

    /* @noinspection PhpUnusedPrivateFieldInspection */
    private bool  $useSmartStrings = false;

    /* @noinspection PhpUnusedPrivateFieldInspection */
    private mixed $loadHandler;              // The handler for lazy-loading nested arrays, e.g. '\Your\Class\SmartArrayLoadHandler::load', receives $smartArray, $fieldName

    /* @noinspection PhpUnusedPrivateFieldInspection */
    private array $mysqli          = [];     // NOSONAR Metadata from last mysqli result, e.g. $result->mysqli('affected_rows')
    private self  $root;                     // The root SmartArray, set on nested SmartArrays and self

    // These properties are calculated when the SmartArray is created or modified
    private bool $isFirst  = false;
    private bool $isLast   = false;
    private int  $position = 0;      // Is this ArrayObject the last child element of a root ArrayObject?  // NOSONAR - Ignore S1068 false-positive Unused private fields should be removed

    public function usingSmartStrings(): bool
    {
        return $this->getProperty('useSmartStrings');
    }

    /**
     * Return object property value or throw an exception if property does not exist.
     *
     * @param string $name
     * @return bool|int|array|SmartArray|null
     */
    private function getProperty(string $name): mixed
    {
        if (!property_exists($this, $name)) {
            throw new InvalidArgumentException("Property '$name' does not exist.");
        }

        $this->setFlags(ArrayObject::STD_PROP_LIST); // Allow access to private/protected properties
        $value = $this->{$name} ?? null;
        $this->setFlags(ArrayObject::ARRAY_AS_PROPS); // Hide private/protected properties
        return $value;
    }

    /**
     * Set object property value or throw an exception if property does not exist.
     * @noinspection PhpSameParameterValueInspection
     */
    private function setProperty(string $name, mixed $value): void
    {
        if (!property_exists($this, $name)) {
            throw new InvalidArgumentException("Property '$name' does not exist.");
        }

        $this->setFlags(ArrayObject::STD_PROP_LIST); // Allow access to private/protected properties
        $this->{$name} = $value;
        $this->setFlags(ArrayObject::ARRAY_AS_PROPS); // Hide private/protected properties
    }

    //endregion

}
