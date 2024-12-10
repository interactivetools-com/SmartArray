<?php

declare(strict_types=1);

namespace Itools\SmartArray;

use Throwable, Error, Exception, InvalidArgumentException, RuntimeException;
use ArrayObject, Iterator, JsonSerializable, Closure;
use Itools\SmartString\SmartString;

/**
 * SmartArray - Represent an array as an ArrayObject with extra features and a fluent, chainable interface.
 */
class SmartArray extends ArrayObject implements JsonSerializable                                                                                                                                                                                                                    // NOSONAR - Ignore S1448 "Too many methods"
{
    #region Creation and Conversion

    /**
     * Constructs a new SmartArray object from an array, recursively converting each element to either a SmartString
     * or a nested SmartArray. It also sets special properties for nested SmartArrays to indicate their position
     * within the root array.
     *
     * @param array $array The input array to convert into a SmartArray.
     */
    public function __construct(array $array = [], array $properties = [])
    {
        // Create new empty ArrayObject with object property writing enabled (STD_PROP_LIST)
        parent::__construct([], ArrayObject::STD_PROP_LIST);

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

        // Update child properties
        //$this->updateChildProperties(get_object_vars($this));  // Copies parent properties and calculates positional properties

        $this->setFlags(ArrayObject::ARRAY_AS_PROPS);           // ARRAY_AS_PROPS: Object properties refer to internal array storage keys (not object properties)

    }

    /**
     * Creates a new SmartArray object - an alias for `(new SmartArray($array))`
     *
     * Constructs a new SmartArray object from an array, recursively converting each element to either a SmartString
     * or a nested SmartArray. It also sets special properties for nested SmartArrays to indicate their position
     * within the root array.
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
     *
     */
    public static function new(array $array = [], array $properties = []): self
    {
        $properties['useSmartStrings'] = false;
        return new self($array, $properties);
    }

    /**
     * Creates a SmartArray that converts values to SmartString objects on access.
     *
     * When created with newSS(), scalar values are automatically wrapped in SmartString objects
     * when accessed. These SmartString objects provide automatic HTML encoding and chainable
     * formatting methods. The conversion is done lazily on access, so methods like toArray()
     * still return raw values.
     *
     * @param array $array The input array to convert into a SmartArray
     * @param array $properties Optional properties to set on the SmartArray
     * @return self A new SmartArray instance that converts values to SmartStrings
     *
     * @example
     * // Create array with SmartString conversion
     * $users = SmartArray::newSS([
     *     ['name' => "John O'Connor", 'city' => 'New York'],
     *     ['name' => 'Tom & Jerry',   'city' => 'Vancouver']
     * ]);
     *
     * echo $users->first()->name;  // Output is HTML-encoded: John O&apos;Connor
     * $rawName = $users->first()->name->value();  // Get original value: John O'Connor
     *
     * // Values stay as SmartArrays/SmartStrings until converted back
     * $array = $users->toArray();  // Convert back to regular PHP array with raw values
     */
    public static function newSS(array $array = [], array $properties = []): self
    {
        $properties['useSmartStrings'] = true;
        return new self($array, $properties);
    }

    #endregion
    #region Value Access

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
                is_scalar($default), is_null($default) => $this->encodeOutput($default),
                is_array($default)                     => new SmartArray($default),
                $isDefaultSmartObject                  => $default,
                default                                => throw new InvalidArgumentException("Unsupported default value type: " . get_debug_type($default)),
            };
        }

        // skip if empty
        if ($this->isEmpty()) {
            return $this->newSmartNull();
        }

        // Deprecated: legacy support for ZenDB/Collection, this will be removed in a future version
        if (is_int($key)) {
            $firstEl  = $this->keys()->first();
            $firstKey = is_object($firstEl) ? $firstEl->value() : $firstEl;
            if (is_int($firstKey)) {
                self::logDeprecation("Replace ->get($key) with ->nth($key) to access the nth element of on associative arrays.");
                return $this->nth($key);
            }
        }

        return $this->offsetGet($key);
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
        $count  = count($this);
        $index  = ($index < 0) ? $count + $index : $index; // Convert negative indexes to positive
        $keys   = array_keys($this->getArrayCopy());

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

        // Convert nested arrays to SmartArrays
        if (is_array($value)) {
            $value = new self($value, get_object_vars($this));
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
    public function offsetGet(mixed $key): SmartArray|SmartNull|SmartString|string|int|float|bool|null
    {
        $key = self::rawValue($key); // Convert SmartString keys to raw values

        // Deprecated: legacy support for ZenDB/ResultSet, this will be removed in a future version
        if (is_string($key) && ($this->isEmpty() || $this->offsetExists(0))) { // If string is key, and (at least first) array key is numeric (array_is_list)
            $return = match (strtolower(($key))) {
                'affectedrows' => self::logDeprecationAndReturn($this->mysqli('affected_rows'), "Replace ->$key with ->mysqli('affected_rows')"),
                'insertid'     => self::logDeprecationAndReturn($this->mysqli('insert_id'), "Replace ->$key with ->mysqli('insert_id')"),
                'errno'        => self::logDeprecationAndReturn(0, "Replace ->$key with a try/catch block around the query"),
                'error'        => self::logDeprecationAndReturn('', "Replace ->$key with a try/catch block around the query"),
                'count'        => self::logDeprecationAndReturn($this->count(), "Replace ->$key with ->count() (add brackets)"),
                'first'        => self::logDeprecationAndReturn($this->first(), "Replace ->$key with ->first() (add brackets)"),
                'isfirst'      => self::logDeprecationAndReturn($this->isFirst(), "Replace ->$key with ->isFirst() (add brackets)"),
                'islast'       => self::logDeprecationAndReturn($this->isLast(), "Replace ->$key with ->isLast() (add brackets)"),
                'raw'          => self::logDeprecationAndReturn($this->toArray(), "Replace ->$key with ->toArray()"),
                'toarray'      => self::logDeprecationAndReturn($this->toArray(), "Replace ->$key with ->toArray() (add brackets)"),
                'values'       => self::logDeprecationAndReturn($this->values(), "Replace ->$key with ->values() (add brackets)"),
                default        => null,
            };
            if (!is_null($return)) {
                return $return;
            }
        }

        // Return value if key exists, or SmartNull if not found
        if ($this->offsetExists($key)) {
            $value = parent::offsetGet($key);
            return $this->encodeOutput($value, $key);
        }
        $this->warnIfMissing($key, 'offset');
        return $this->newSmartNull();
    }

    /**
     * Converts Smart* objects to their original values while leaving other types unchanged,
     * useful if you don't know the type but want the original value.
     */
    public static function rawValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof SmartString      => $value->value(),
            $value instanceof self             => $value->toArray(),
            $value instanceof SmartNull        => null,
            is_scalar($value), is_null($value) => $value,
            is_array($value)                   => array_map([self::class, 'rawValue'], $value), // for manually passed in arrays
            default                            => throw new InvalidArgumentException("Unsupported value type: " . get_debug_type($value)),
        };
    }

    #endregion
    #region Array Information

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

    #endregion
    #region Position & Layout

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
        return new self($chunks, get_object_vars($this));
    }

    #endregion
    #region Sorting & Filtering

    /**
     * Returns a new array sorted by values, using PHP sort() function.
     */
    public function sort(int $flags = SORT_REGULAR): SmartArray
    {
        $this->assertFlatArray();

        $sorted = $this->toArray();
        sort($sorted, $flags);
        return new self($sorted, get_object_vars($this));
    }

    /**
     * Returns a new SmartArray sorted by the specified column, using PHP array_multisort().
     */
    public function sortBy(string $column, int $type = SORT_REGULAR): SmartArray {

        $this->assertNestedArray();
        if ($this->first() instanceof self) {
            $this->first()->warnIfMissing($column, 'argument');
        }



        // sort by key
        $sorted       = $this->toArray();
        $columnValues = array_column($sorted, $column);
        array_multisort($columnValues, SORT_ASC, $type, $sorted);

        return new self($sorted, get_object_vars($this));
    }

    /**
     * Returns a new array with duplicate values removed, keeping only the first
     * occurrence of each unique value, and preserving keys.
     */
    public function unique(): SmartArray
    {
        $this->assertFlatArray();

        $unique = array_unique($this->toArray());
        return new self($unique, get_object_vars($this));
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
        return new self($values, get_object_vars($this));
    }

    /**
     * Returns a new SmartArray containing only the elements that satisfy the callback function.
     *
     * Note, this method doesn't require nested arrays but will skip any elements that are not arrays.
     * This allows us to work with CMSB schema files.
     *
     * @param array $conditions
     * @return SmartArray
     */
    public function where(array $conditions): SmartArray
    {
        // Filter rows that match all conditions
        $filtered = array_filter($this->toArray(), static function($row) use ($conditions) {
            // skip elements that are not arrays
            if (!is_array($row)) {
                return false;
            }

            // check if all conditions are met
            foreach ($conditions as $key => $value) {
                if (!array_key_exists($key, $row) || $row[$key] !== $value) {
                    return false;
                }
            }
            return true;
        });

        return new self($filtered, get_object_vars($this));
    }

    #endregion
    #region Array Transformation

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
        return new self($keys, get_object_vars($this));
    }

    /**
     * Returns a new array of values
     */
    public function values(string|int|null $key = null): SmartArray
    {
        // Deprecated: legacy support for ZenDB/Collection, this will be removed in a future version
        if (!is_null($key) && $this->isNested()) {
            $isLookupByPosition = is_int($key) && !is_int($this->keys()->first());
            if ($isLookupByPosition) {
                self::logDeprecation("Replace ->values($key) with ->pluckNth(\$key) for an array of the nth column of each row.");
                $values = $this->map(fn($row) => array_values($row))->pluck($key);
            }
            else {
                self::logDeprecation("Replace ->values($key) with ->pluck($key) for an array of column values by key.");
                $values = $this->pluck($key);
            }
            return $values; // Return the new SmartArray created above
        }

        $values = array_values($this->toArray());
        return new self($values, get_object_vars($this));
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
    public function indexBy(string $column, bool $asList = false): SmartArray
    {
        $this->assertNestedArray();
        if ($this->first() instanceof self) {
            $this->first()->warnIfMissing($column, 'argument');
        }

        // Deprecated: legacy support for ZenDB/Collection, this will be removed in a future version
        if ($asList === true) {
            self::logDeprecation("Replace ->indexBy(column, true) with ->groupBy(column) to group rows by column value.");
            return $this->groupBy($column);
        }

        // Index by column
        $values = array_column($this->toArray(), null, $column);
        return new self($values, get_object_vars($this));
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
            $this->first()->warnIfMissing($column, 'argument');
        }

        $values = [];
        foreach ($this->toArray() as $row) {
            $key = $row[$column];
            $values[$key][] = $row;
        }

        return new self($values, get_object_vars($this));
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
            $this->first()->warnIfMissing($valueColumn, 'argument');
        }

        $values = array_column($this->toArray(), $valueColumn, $keyColumn);
        return new self($values, get_object_vars($this));
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
        return new self($values, get_object_vars($this));
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
    public function implode(string $separator): SmartString|string
    {
        $this->assertFlatArray();

        $values = array_map('strval', array_values($this->toArray()));
        $value  = implode($separator, $values);

        return $this->encodeOutput($value);
    }


    /**
     * Applies a callback function to each element of the SmartArray and returns a new SmartArray with the results.
     *
     * The callback function receives raw values (arrays, strings, numbers) instead of SmartString or SmartArray objects.
     *
     * Note: Keys are preserved in all cases, and the callback function receives both the value and the key. e.g., fn($value, $key) =>
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
        $oldArray  = $this->toArray();
        $oldKeys   = array_keys($oldArray);
        $oldValues = array_values($oldArray);

        // Pass both $value and $key to closures, e.g., fn($value, $key) => ...
        if ($callback instanceof Closure) {
            $newValues = array_map($callback, $oldValues, $oldKeys);
        } else {
            // Pass only $value for non-closure callbacks to avoid unexpected behavior
            // e.g., intval($value, $base) would misinterpret $key as $base, so [1] => 2 would returns 0 from intval(2, 1)
            $newValues = array_map($callback, $oldValues);
        }

        // combine modified values with original keys
        $newArray  = array_combine($oldKeys, $newValues);

        // return new SmartArray
        return new self($newArray, get_object_vars($this));
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
        $arrays = array_map([self::class, 'rawValue'], $arrays); // convert SmartArrays to arrays
        $merged = array_merge($this->toArray(), ...$arrays);
        return new self($merged, get_object_vars($this));
    }


    #endregion
    #region Database Operations

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
     *
     * @param string $column
     * @return SmartArray|false
     * @throws Exception
     */
    public function load(string $column): SmartArray|false
    {
        $loadHandler = $this->getProperty('loadHandler');

        // error checking
        match (true) {
            !$loadHandler                   => throw new Exception("No loadHandler property is defined"),
            !is_callable($loadHandler)      => throw new Exception("Load handler is not callable"),
            empty($column)                  => throw new InvalidArgumentException("Column name is required for load() method."),
            preg_match('/[^\w-]/', $column) => throw new Exception("Column name contains invalid characters: $column"),
            $this->isNested()               => throw new Exception("Cannot call load() on record set, only on a single row."),
            default                         => null,
        };

        // get handler output
        $result = $loadHandler($this, $column);
        if ($result === false) {
            throw new Error("Load handler not available for '$column'\n" . $this->occurredInFile());
        }

        // output error checking
        [$array, $mysqliProperties] = $result; // Get new array data
        match (true) {
            !is_array($array)            => throw new Error("Load handler must return an array as the first argument"),
            !is_array($mysqliProperties) => throw new Error("Load handler must return an array as the second argument"),
            default                      => null,
        };

        // return new SmartArray
        return new self($array, [
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
     */
    public function setLoadHandler(callable $customLoadHandler): void
    {
        $this->setFlags(ArrayObject::STD_PROP_LIST); // ArrayObject: Switch properties to allow standard property access
        $this->loadHandler = $customLoadHandler;
        $this->setFlags(ArrayObject::ARRAY_AS_PROPS); // ArrayObject: Switch properties back to refer to internal array keys
    }

    /**
     * Return the root SmartArray object for nested arrays, or the current object if not nested.
     */
    public function root(): SmartArray
    {
        return $this->getProperty('root');
    }

    #endregion
    #region Debugging and Help

    /**
     * Displays help information about available methods and properties.
     */
    public static function help(): void
    {
        $output = <<<'__TEXT__'
            SmartArray: Enhanced Arrays with Automatic HTML Encoding and Chainable Methods
            ==========================================================================================
            SmartArray extends PHP arrays with automatic HTML encoding and chainable utility methods.
            It preserves familiar array syntax while adding powerful features for filtering, mapping,
            and data manipulation - making common array operations simpler, safer, and more expressive.
            
            Core Concepts
            -------------------
            $obj                = Itools\SmartArray\SmartArray    // Arrays become SmartArray objects (even nested arrays)
            $obj['columnName']  = Itools\SmartString\SmartString  // Values become SmartString objects with HTML-encoded output
            $obj->columnName    = Itools\SmartString\SmartString  // Optional object syntax makes code cleaner and more readable
            
            Accessing Elements
            -------------------
            foreach ($users as $user) {          // Foreach over a SmartArray just like a regular array
                echo "Name: $user->name\n";      // SmartString output is automatically HTML-encoded, no need for htmlspecialchars()
            
                // For more complex expressions, curly braces are still required
                echo "Bio: {$user->bio->textOnly()->maxChars(120, '...')}\n";  // Chain SmartString methods on column values
            }
            
            Original Values
            -------------------
            $obj->toArray()                      // Get original array with raw values
            $obj->columnName->value()            // Get original unencoded field value
            "Bio: {$user->wysiwyg->noEncode()}"  // Alias for value(), clearer when handling WYSIWYG/HTML content
            
            Creating SmartArrays
            -------------------
            $ids   = SmartArray::new([1, 2, 3]);
            $user  = SmartArray::new(['name' => 'John', 'age' => 30]);
            $users = SmartArray::new(DB::select('users'));  // Nested SmartArray of SmartStrings
            
            Value Access
            -------------
            $obj[key]               Get a value using array syntax
            $obj->key               Get a value using object syntax
            ->get(key)              Get a value using method syntax
            ->get(key, default)     Get a value with optional default if key not found
            ->first()               Get the first element
            ->last()                Get the last element
            ->nth(position)         Get element by position, ignoring keys (0 is first, -1 is last)
            
            Array Information
            ------------------
            ->count()               Get the number of elements
            ->isEmpty()             Returns true if array has no elements
            ->isNotEmpty()          Returns true if array has any elements
            
            Position & Layout
            ------------------
            ->isFirst()             Check if first in root array
            ->isLast()              Check if last in root array
            ->position()            Get position in root array (starting at 1)
            ->isMultipleOf(n)       Check if position is multiple of n (3 for every 3rd element)
            ->chunk(size)           Split array into smaller arrays of specified size
            
            Sorting & Filtering
            --------------------
            ->sort()                Sort elements by value, reindexing keys
            ->sortBy(column)        Sort nested array by column value, reindexing keys
            ->unique()              Remove duplicate values, keeping first occurrence
            ->filter(callback)      Keep elements where callback returns true, using raw values
            ->where(conditions)     Keep elements matching [column => value] conditions
            
            Array Transformation
            ---------------------
            ->toArray()             Convert SmartArray/SmartString structure back to array/values
            ->keys()                Get array of just the keys
            ->values()              Get array of just the values
            ->indexBy(column)       Get array using column as keys to single rows (duplicates overwrite)
            ->groupBy(column)       Get array using column as keys to arrays of rows (duplicates group)
            ->pluck(column)         Extract single column from nested array
            ->pluckNth(position)    Get array containing nth element from each row
            ->implode(separator)    Join elements with separator into string
            ->map(callback)         Transform each element using callback that receives raw values
            ->merge(...$arrays)      Merges with one or more arrays. Numeric keys are renumbered, string keys are overwritten by later values.
            
            Database Operations
            --------------------
            ->mysqli()              Get an array of all mysqli result metadata (set when creating array from DB result)
            ->mysqli(key)           Get specific mysqli result metadata (affected_rows, insert_id, etc)
            ->load()                Loads related record(s) if available for column
            
            Debugging
            ----------
            print_r($obj)           Show array values
            $obj->debug()           Show array values, mysqli metadata, and available load() handlers
            $obj->help()            Display this help information
            
            For more details see SmartArray readme.md, and SmartString docs for chainable string methods.
            __TEXT__;

        echo self::xmpWrap($output);
    }

    /**
     * Displays help information about available methods and properties.
     */
    public function debug($debugLevel = 0): void
    {
        // show data header
        $className = self::class;
        $output = match ($this->getProperty('useSmartStrings')) {
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
            $output            .= "\n";
            $metadata          = $this->mysqli();
            $metadata['query'] = preg_replace("/\s+/", " ", trim($metadata['query'])); // remove extra spaces
            $output            .= self::prettyPrintR($metadata, $debugLevel, 0, "MySQLi Metadata ");
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
        $debugType   = basename(get_debug_type($var));
        $comment     = $debugLevel > 0 ? " // $debugType" : "";

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
                $thisKeyPrefix = str_pad($wrappedKey, $maxKeyLength) ." => ";

                // add load comment
                $loadResult = false;
                try {
                    $loadResult = $var->load($key);
                } catch (Throwable) {
                    // ignore errors
                }
                $loadComment = $loadResult ? " // ->load('$key') for more" : "";

                // get output
                $output .= self::prettyPrintR($value, $debugLevel, $depth + 1, $thisKeyPrefix, $loadComment);
            }
            $output = preg_replace("|,(\s*//.*)?$|", " $1", $output); // Remove trailing commas
            $output .= $depth ? "],\n" : "]\n"; // skip trailing comma on top level
        } elseif (is_scalar($var) || is_null($var)) {
            $hasTabs     = is_string($var) && str_contains($var, "\t");
            $varExport   = match (true) {
                is_null($var) => "null",
                is_bool($var) => $var ? "true" : "false",
                !$debugLevel  => "$var",                                       // Show raw values without quotes for compact mode
                $hasTabs      => '"' . addcslashes($var, "\t\"\0\$\\") . '"',  // Show tabs as \t for readability
                default       => var_export($var, true),
            };
            $varExport  .= $debugLevel ? "," : ""; // add trailing comma for debug mode > 0
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
        $hasContentType     = (bool)preg_match('|^\s*Content-Type:\s*|im', $headersList);  // assume no content type will default to html
        $isTextHtml         = !$hasContentType || preg_match('|^\s*Content-Type:\s*text/html\b|im', $headersList); // match: text/html or ...;charset=utf-8
        $backtraceFunctions = array_map('strtolower',array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'function'));
        $wrapInXmp          = $isTextHtml && !in_array('showme', $backtraceFunctions);
        return $wrapInXmp ? "\n<xmp>\n$output\n</xmp>\n" : "\n$output\n";
    }

    /**
     * Show internal array data print_r() is used to examine object.
     *
     * You can temporarily comment out this function to see the properties while debugging.
     */
    public function __debugInfo(): array
    {
        // show help information for root array
        $output = [];
        if ($this === $this->root()) {
            // Call ->help() for usage examples and documentation, or ->debug() to view metadata
            $output["README:SmartArray:private"] = "Call \$obj->help() for documentation, or ->debug() to view metadata";
            $output["*useSmartStrings*:private"] = match($this->getProperty('useSmartStrings')) {
              true  => "true, // Values are returned as SmartString objects on access\n",
              false => "false, // Values are returned **as-is** on access (no extra encoding)\n",
            };
        }

        // show array data
        $output += $this->getArrayCopy();
        return $output;
    }

    #endregion
    #region Error Handling

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
     * @param string $warningType 'offset' for properties, 'argument' for method arguments
     */
    private function warnIfMissing(string|int $key, string $warningType): void
    {
        // Skip warning if the array is empty or key exists
        if ($this->count() === 0 || $this->offsetExists($key)) {
            return;
        }

        // If array isn't empty and key doesn't exist, throw warning to help debugging
        // Note that we only check when array is not empty, so we don't throw warnings for every column on empty arrays
        $caller       = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $function     = $caller['function'] ?? "unknownFunction";
        //$inFileOnLine = sprintf("in %s:%s", $caller['file'] ?? 'unknown', $caller['line'] ?? 'unknown'); //

        $warning = match ($warningType) {
            'offset'   => "Undefined property or array key '$key', use ->offsetExists() or ->get() with a default value to avoid this warning.", // ArrayObject treats properties as offsets
            'argument' => "$function(): '$key' doesn't exist",
            default    => throw new InvalidArgumentException("Invalid warning type '$warningType'"),
        };
        $warning .= "\n\n";

        // Catch if user tried to call a method in a double-quoted string without braces
        if (is_string($key) && method_exists($this, $key)) { // Catch cases such as "Nums: $users->pluck('num')->implode(',')->value();" which are missing braces
            $warning .= "In double-quoted strings, use \"\$var->property\" for properties, but wrap methods and array access in braces like \"{\$var->method()} or {\$var['key']}\"";
        } else {
            // But if it's not a method, show a list of valid keys in case they mistyped a property/offset name
            $validKeys = implode(', ', array_keys($this->getArrayCopy()));
            $warning   .= wordwrap("Valid keys are: $validKeys", 120);
        }
        $warning .= "\n\n";
        $warning .= $this->occurredInFile(true);


        // output warning and trigger PHP warning (for logging)
        echo "\nWarning: $warning\n";           // Output with echo so PHP doesn't add the filename and line number of this function on the end
        @trigger_error($warning, E_USER_WARNING); // Trigger a PHP warning but hide output with @ so it will still get logged
    }

    private static function logDeprecation($error): void {
        @user_error($error, E_USER_DEPRECATED);  // Trigger a silent deprecation notice for logging purposes
    }

    private static function logDeprecationAndReturn($returnOrCallback, $error) {
        self::logDeprecation($error);
        return is_callable($returnOrCallback) ? $returnOrCallback() : $returnOrCallback;
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
     * @param $method
     * @param $args
     * @return mixed
     *
     * @noinspection PhpDeprecationInspection
     * @noinspection SpellCheckingInspection // ignore lowercase method names in match block
     */
    public function __call($method, $args)
    {
        // Deprecated: legacy support for ZenDB/Collection, this will be removed in a future version
        $return = match (strtolower($method)) {
            'column', 'getcolumn'  => self::logDeprecationAndReturn($this->col(...$args), "Replace ->$method() with ->col()"),
            'exists'               => self::logDeprecationAndReturn($this->isNotEmpty(), "Replace ->$method() with ->isNotEmpty()"),
            'firstrow', 'getfirst' => self::logDeprecationAndReturn($this->first(), "Replace ->$method() with ->first()"),
            'getvalues'            => self::logDeprecationAndReturn($this->values(), "Replace ->$method() with ->values()"),
            'item'                 => self::logDeprecationAndReturn($this->get(...$args), "Replace ->$method() with ->get()"),
            'join'                 => self::logDeprecationAndReturn($this->implode(...$args), "Replace ->$method() with ->implode()"),
            'raw'                  => self::logDeprecationAndReturn($this->toArray(), "Replace ->$method() with ->toArray()"),
            default                => null,
        };
        if (!is_null($return)) { // All methods should return objects
            return $return;
        }

        // throw unknown method exception
        // PHP Default Error: Fatal error: Uncaught Error: Call to undefined method class::method() in /path/file.php:123
        $baseClass = basename(self::class);
        $error     = "Call to undefined method $baseClass->$method(), call ->help() for available methods.\n";
        throw new Error($error . $this->occurredInFile());
    }

    /**
     * Add "Occurred in file:line" to the end of the error messages with the first non-SmartArray file and line number.
     */
    private function occurredInFile($addReportedFileLine = false): string
    {
        $file = "unknown";
        $line = "unknown";
        $inMethod = "";
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Add Occurred in file:line
        foreach ($backtrace as $index => $caller) {
            if (empty($caller['file']) || $caller['file'] !== __FILE__) {
                $file = $caller['file'] ?? $file;
                $line = $caller['line'] ?? $line;
                $prevCaller =  $backtrace[$index + 1] ?? [];
                $inMethod = match (true) {
                    !empty($prevCaller['class'])    => " in {$prevCaller['class']}{$prevCaller['type']}{$prevCaller['function']}()",
                    !empty($prevCaller['function']) => " in {$prevCaller['function']}()",
                    default                           => "",
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
            $output .= " in $reportedFile:$reportedLine in $method()\n";
        }

        // return output
        return $output;
    }

    #endregion
    #region Internal Methods

    /**
     * Return SmartArrays as is, and raw values as SmartStrings if that's enabled, otherwise as raw
     */
    private function encodeOutput(mixed $value, string|int $key = ''): mixed
    {
        if (is_scalar($value) || is_null($value)) {
            return $this->getProperty('useSmartStrings') ? new SmartString($value) : $value;
        }
        if ($value instanceof self) {
            return $value;
        }

        // throw error if value is not supported
        $error = __METHOD__ . ": SmartArray doesn't support '" . get_debug_type($value) . "' values.";
        if (func_num_args() >= 2) {
            $error .= " Key '$key'.";
        }
        throw new InvalidArgumentException($error);
    }

    /**
     * Return a new SmartNull object with the same SmartString setting as the current SmartArray.
     */
    public function newSmartNull(): SmartNull
    {
        return new SmartNull($this->getProperty('useSmartStrings'));
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
            yield $key => $this->encodeOutput($value, $key);
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

    #endregion
    #region Internal Properties

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
     * PROPERTIES NOTICE:
     * $this is an ArrayObject with ArrayObject::ARRAY_AS_PROPS flag
     * array elements are stored in an inaccessible internal array in $this->storage by PHP's ArrayObject
     * So getting/setting properties updates the stored element values, NOT the properties themselves
     * To maintain separation, internal properties must be private and accessed via getProp() and setProp()
     */

    // These properties are set on creation, passed on to nested SmartArrays, and never changed after creation
    // NOSONAR suppresses SonarLint false-positive: Unused private fields should be removed

    private bool  $useSmartStrings = false;  // NOSONAR Is this ArrayObject a nested array?
    private mixed $loadHandler;              // The handler for lazy-loading nested arrays, e.g. '\Your\Class\SmartArrayLoadHandler::load', receives $smartArray, $fieldName
    private array $mysqli          = [];     // NOSONAR Metadata from last mysqli result, e.g. $result->mysqli('affected_rows')
    private self  $root;                     // The root SmartArray, set on nested SmartArrays and self

    // These properties are calculated when the SmartArray is created or modified
    private bool $isFirst  = false;
    private bool $isLast   = false;
    private int  $position = 0;      // Is this ArrayObject the last child element of a root ArrayObject?  // NOSONAR - Ignore S1068 false-positive Unused private fields should be removed

    #endregion
    #region Deprecated Methods

    /**
     * Legacy function to return column(s) by key or position
     *
     * Flat Arrays: Returns a single value referenced by key or position
     * Nested Arrays: Returns a new SmartArray with values from the specified column (key or position)
     *
     * @param string|int $key The name or offset of the column to retrieve.
     *
     * @return SmartArray|SmartNull|SmartString The Field object for a single column or a Collection of Fields, or null if the key is not found.
     * @deprecated See replacement suggests in method body.
     */
    public function col(string|int $key): SmartArray|SmartNull|SmartString
    {
        // Skip processing if the array is empty
        if ($this->isEmpty()) {
            self::logDeprecation("Replace ->col() with another method.  Can't determine replacement method because array is empty.");
            return $this->newSmartNull();
        }

        // Determine if the lookup is by position
        $isLookupByPosition = is_int($key) && !is_int($this->keys()->first());

        // Handle flat arrays (non-nested)
        if (!$this->isNested()) {
            if ($isLookupByPosition) {
                self::logDeprecation("Replace ->col() with ->nth() to access the nth element of on associative arrays.");
                return $this->nth($key);
            }
            self::logDeprecation("Replace ->col() with ->get() to access array elements by key.");
            return $this->get($key);
        }

        // Handle nested Arrays
        if ($isLookupByPosition) {
            self::logDeprecation("Replace ->col() with ->pluckNth() for an array of the nth column of each row.");
            $values = $this->map(fn($row) => array_values($row))->pluck($key);
        }
        else {
            self::logDeprecation("Replace ->col() with ->pluck() for an array of column values by key.");
            $values = $this->pluck($key);
        }
        return $values; // Return the new SmartArray created above
    }

    #endregion
}
