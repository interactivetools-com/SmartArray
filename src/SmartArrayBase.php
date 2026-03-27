<?php
/** @noinspection PhpLoopCanBeConvertedToArrayFilterInspection - foreach is faster than array_filter with closure */
declare(strict_types=1);
namespace Itools\SmartArray;

use stdClass;
use Throwable, Error, RuntimeException, InvalidArgumentException;
use ArrayAccess, IteratorAggregate, Iterator, Countable, JsonSerializable, Closure;
use Itools\SmartString\SmartString;

/**
 * SmartArrayBase - Base implementation for SmartArray and SmartArrayHtml.
 *
 * Uses wide return types that child classes narrow via covariance.
 * Do not instantiate directly - use SmartArray or SmartArrayHtml.
 *
 * Extends stdClass to enable clean IDE property autocomplete. Without this,
 * IDEs show "processed by magic method" warnings on every $row->property access.
 */
abstract class SmartArrayBase extends stdClass implements SmartBase, ArrayAccess, IteratorAggregate, Countable, JsonSerializable
{
    use ErrorHelpersTrait;
    use DeprecationsTrait;

    //region Internal Storage

    /**
     * Internal array storage (replaces ArrayObject's internal storage)
     */
    private array $data = [];

    //endregion
    //region Position Properties

    /**
     * Position metadata for nested SmartArrays (child rows).
     * Set during parent construction, used for template rendering.
     */
    protected bool $isFirst = false;
    protected bool $isLast  = false;
    private int $position   = 0;

    /**
     * Returns true if this element is the first child in its parent SmartArray.
     *
     *     foreach ($rows as $row) {
     *         if ($row->isFirst()) { echo '<ul>'; }
     *         echo "<li>$row->name</li>";
     *         if ($row->isLast()) { echo '</ul>'; }
     *     }
     *
     * @return bool
     */
    public function isFirst(): bool
    {
        return $this->isFirst;
    }

    /**
     * Returns true if this element is the last child in its parent SmartArray.
     *
     * @return bool
     */
    public function isLast(): bool
    {
        return $this->isLast;
    }

    /**
     * Returns the 1-based position of this element within its parent SmartArray.
     *
     *     foreach ($rows as $row) {
     *         echo "Row {$row->position()} of " . $rows->count();
     *     }
     *
     * @return int 1-based position (0 if not a child element)
     */
    public function position(): int
    {
        return $this->position;
    }

    //endregion
    //region Creation and Conversion

    /**
     * Constructs a new SmartArray from an array, recursively converting nested arrays
     * into child SmartArray instances. Scalar values are stored as-is and wrapped in
     * SmartString on access (when enabled). Sets position metadata on child SmartArrays.
     *
     *     $sa = new SmartArray(['name' => 'Alice', 'age' => 30]);
     *     $sa = new SmartArray($records); // nested arrays become child SmartArrays
     *
     * @param array      $array      The input array to convert into a SmartArray.
     * @param bool|array $properties An associative array of internal properties. Boolean is deprecated.
     *
     * @noinspection UnusedConstructorDependenciesInspection
     */
    public function __construct(array $array = [], bool|array $properties = [])
    {
        // Convert boolean to array format for backward compatibility
        if (is_bool($properties)) {
            self::logDeprecation("Passing boolean to SmartArray constructor is deprecated. Use ->asHtml() for HTML-safe SmartStrings or ->asRaw() for raw values");
            $properties = ['useSmartStrings' => $properties];
        }

        // Set internal properties from properties array
        foreach ($properties as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
        $this->root ??= $this;  // Set root property to self if not already set

        // Add elements and set position metadata on child SmartArrays
        $count    = count($array);
        $position = 0;
        foreach ($array as $key => $value) {
            $position++;
            $this->setElement($key, $value);

            // Set position properties on child SmartArrays (rows)
            $element = $this->data[$key];
            if ($element instanceof self) {
                $element->position = $position;
                $element->isFirst  = $position === 1;
                $element->isLast   = $position === $count;
            }
        }
    }

    /**
     * Create a new instance of the current SmartArray type.
     *
     *     $data = SmartArray::new($records)->filter(...)->map(...);
     *     $html = SmartArrayHtml::new($records)->first();
     *
     * @param array      $array      The input array to convert
     * @param array|bool $properties Optional properties array. Boolean is deprecated.
     * @return static A new instance of the called class
     */
    public static function new(array $array = [], array|bool $properties = []): static
    {
        // Backward compatibility: handle boolean for SmartStrings toggle
        if (is_bool($properties)) {
            match ($properties) {
                true  => self::logDeprecation("Passing `true` as the second argument to SmartArray::new(...) is deprecated. Use SmartArrayHtml::new(...) instead"),
                false => self::logDeprecation("Passing `false` as the second argument to SmartArray::new(...) is deprecated. Just use SmartArray::new(...)"),
            };
            $properties = ['useSmartStrings' => $properties];
        }

        return new static($array, $properties);
    }

    /**
     * Return values as raw PHP types for data processing.
     *
     * Returns the same object if already SmartArray (lazy conversion), otherwise creates a new one.
     *
     * @return SmartArray This object if already raw, or a new SmartArray instance
     */
    abstract public function asRaw(): SmartArray;

    /**
     * Return values as HTML-safe SmartString objects.
     *
     * Returns the same object if already SmartArrayHtml (lazy conversion), otherwise creates a new one.
     *
     * @return SmartArrayHtml This object if already HTML-safe, or a new SmartArrayHtml instance
     */
    abstract public function asHtml(): SmartArrayHtml;

    //endregion
    //region Value Access

    /**
     * Retrieves an element by key, or returns a default value or SmartNull if not found.
     *
     *     $value = $array->get('name');              // returns element or SmartNull
     *     $value = $array->get('name', 'fallback');  // returns element or 'fallback'
     *
     * @param int|string $key     The key to retrieve
     * @param mixed      $default Optional default value if key doesn't exist
     * @return static|SmartNull|SmartString|string|int|float|bool|null
     */
    public function get(int|string $key, mixed $default = null): static|SmartNull|SmartString|string|int|float|bool|null
    {
        // return default if key not found
        if (func_num_args() >= 2 && !$this->offsetExists($key)) {
            $isDefaultSmartObject = $default instanceof self || $default instanceof SmartString;
            return match (true) {
                is_scalar($default), is_null($default) => $this->useSmartStrings ? new SmartString($default) : $default,
                is_array($default)                     => new static($default, $this->getInternalProperties()),
                $isDefaultSmartObject                  => $default,
                default                                => throw new InvalidArgumentException("Unsupported default value type: " . get_debug_type($default)),
            };
        }

        // skip if empty
        if (empty($this->data)) {
            return $this->newSmartNull();
        }

        // Return via getElement (no deprecation warning - this is a preferred access method)
        if ($this->offsetExists($key)) {
            return $this->getElement($key);
        }

        // Show warning if key doesn't exist (only when no default provided)
        $this->warnIfMissing($key, 'offset');

        return $this->newSmartNull();
    }

    /**
     * Sets a value by key. Preferred over array assignment syntax.
     *
     * @param int|string $key The key to set
     * @param mixed $value The value to set
     * @return static Returns $this for method chaining
     */
    public function set(int|string $key, mixed $value): static
    {
        $this->setElement($key, $value);
        return $this;
    }

    /**
     * Get first element in array, or SmartNull if array is empty (to allow for further chaining).
     */
    public function first(): static|SmartString|SmartNull|string|int|float|bool|null
    {
        $key = array_key_first($this->data);
        return $key !== null ? $this->getElement($key) : $this->newSmartNull();
    }

    /**
     * Get last element in array, or SmartNull if array is empty (to allow for further chaining).
     */
    public function last(): static|SmartNull|SmartString|string|int|float|bool|null
    {
        $key = array_key_last($this->data);
        return $key !== null ? $this->getElement($key) : $this->newSmartNull();
    }

    /**
     * Get an element by its position in the array, ignoring keys.
     *
     * Uses zero-based indexing (0=first, 1=second) and negative indices (-1=last, -2=second-to-last).
     * Returns SmartNull if out of bounds.
     *
     *     $result = DB::query("SELECT MAX(`order`) FROM `uploads`");
     *     $max    = $result->first()->nth(0)->value(); // Get unaliased column by position
     */
    public function nth(int $index): static|SmartNull|SmartString|string|int|float|bool|null
    {
        $count = count($this->data);
        $index = ($index < 0) ? $count + $index : $index; // Convert negative indexes to positive
        $keys  = array_keys($this->data);

        if (array_key_exists($index, $keys)) {
            return $this->getElement($keys[$index]);
        }

        return $this->newSmartNull();
    }


    /**
     * Stores an element with automatic type conversion.
     * Scalars and nulls are stored as-is; arrays are converted to SmartArray instances.
     */
    private function setElement(int|string|null $key, mixed $value): void
    {
        // Store scalars and nulls as-is (encoded on access by getElement)
        if (is_scalar($value) || is_null($value)) {
            if ($key === null) {
                $this->data[] = $value;
            }
            else {
                $this->data[$key] = $value;
            }
            return;
        }

        // Convert nested arrays to SmartArrays (preserving the current class type)
        if (is_array($value)) {
            $value = new static($value, $this->getInternalProperties());
            if ($key === null) {
                $this->data[] = $value;
            }
            else {
                $this->data[$key] = $value;
            }
            return;
        }

        // Throw an exception for unsupported types or anything else
        $error = sprintf("%s: SmartArray doesn't support %s values. Key %s", __METHOD__, get_debug_type($value), $key);
        throw new InvalidArgumentException($error);
    }

    /**
     * Returns the element at the given key, optionally wrapped in SmartString.
     * Returns SmartNull with a warning if the key doesn't exist.
     */
    private function getElement(int|string $key): static|SmartNull|SmartString|string|int|float|bool|null
    {
        // Return value if key exists, or SmartNull if not found
        if ($this->offsetExists($key)) {
            $value = $this->data[$key];
            return $this->useSmartStrings && !$value instanceof self
                ? new SmartString($value)
                : $value;
        }

        // Show warning if key doesn't exist and array isn't empty
        $this->warnIfMissing($key, 'offset');

        return $this->newSmartNull();
    }

    /**
     * Check if a key exists in the array.
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * Converts Smart* objects to their original values while leaving other types unchanged.
     * Recursively unwraps arrays containing Smart* objects.
     *
     *     SmartArrayBase::getRawValue($smartString); // returns original string
     *     SmartArrayBase::getRawValue($smartArray);  // returns plain array
     *     SmartArrayBase::getRawValue('plain');       // returns 'plain' unchanged
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
     * Returns the number of elements in the array.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Check if array has no elements.
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Check if array has any elements.
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->data);
    }

    /**
     * Check if array contains a specific value (loose comparison).
     */
    public function contains(mixed $value): bool
    {
        return in_array(self::getRawValue($value), $this->toArray());
    }

    //endregion
    //region Sorting & Filtering

    /**
     * Returns a new SmartArray sorted by values, using PHP sort() function.
     * Only works on flat arrays (throws on nested).
     */
    public function sort(int $flags = SORT_REGULAR): static
    {
        $this->assertFlatArray();

        $sorted = $this->toArray();
        sort($sorted, $flags);
        return new static($sorted, $this->getInternalProperties());
    }

    /**
     * Returns a new SmartArray sorted ascending by the specified field.
     * Only works on nested arrays (throws on flat).
     */
    public function sortBy(string $field, int $type = SORT_REGULAR): static
    {
        $this->assertNestedArray();
        $this->warnIfMissing($field);

        // sort by key
        $sorted      = $this->toArray();
        $fieldValues = array_column($sorted, $field);
        array_multisort($fieldValues, SORT_ASC, $type, $sorted);

        return new static($sorted, $this->getInternalProperties());
    }

    /**
     * Returns a new SmartArray with duplicate values removed, keeping only the first
     * occurrence of each unique value, and preserving keys.
     * Only works on flat arrays (throws on nested).
     */
    public function unique(): static
    {
        $this->assertFlatArray();

        $unique = array_unique($this->toArray());
        return new static($unique, $this->getInternalProperties());
    }

    /**
     * Filters elements using a callback and returns a new SmartArray with the results.
     *
     * The callback receives raw values (arrays, strings, numbers) instead of SmartString or SmartArray
     * objects, and should return true to keep the element, false to remove it.
     * When called without a callback, removes all falsy values (empty strings, 0, null, false).
     *
     *     $active   = $users->filter(fn($row) => $row['status'] === 'active');
     *     $nonEmpty = $values->filter();
     *
     * @param callable|null $callback A function($value, $key) that returns true to keep, false to remove.
     * @return static A new SmartArray containing only the elements that passed the test.
     */
    public function filter(?callable $callback = null): static
    {
        $values = array_filter($this->toArray(), $callback, ARRAY_FILTER_USE_BOTH);
        return new static($values, $this->getInternalProperties());
    }

    /**
     * Returns a new SmartArray containing only elements where a field matches a value.
     * Only works on nested arrays (throws on flat).
     *
     * Uses loose comparison (==) to allow matching between different types (e.g., '1' == 1).
     * Chain multiple where() calls to filter by multiple fields.
     *
     *     $active   = $users->where('status', 'active');
     *     $admins   = $users->where('status', 'active')->where('role', 'admin');
     *     $featured = $products->where('featured', 1);
     *
     * @param array|string $field Field name to compare, or associative array of field=>value pairs (deprecated)
     * @param mixed        $value Value to match (supports SmartString, automatically unwrapped)
     * @return static A new SmartArray containing only matching elements
     */
    public function where(array|string $field, mixed $value = null): static
    {
        $this->assertNestedArray();

        // Two-argument syntax: where('field', value)
        if (is_string($field) && func_num_args() === 2) {
            $this->warnIfMissing($field);
            $value   = self::getRawValue($value);
            $matches = [];
            foreach ($this->toArray() as $key => $row) {
                if (is_array($row) && array_key_exists($field, $row) && $row[$field] == $value) {  // intentional loose comparison
                    $matches[$key] = $row;
                }
            }

            return new static($matches, $this->getInternalProperties());
        }

        // Deprecated: legacy array syntax, use chained ->where('field', value) calls instead
        $conditions = array_map([self::class, 'getRawValue'], $field);
        $whereCalls = array_map(fn($k, $v) => "->where('$k', " . (is_numeric($v) ? $v : "'$v'") . ")", array_keys($conditions), $conditions);
        self::logDeprecation("Replace ->where([...]) with " . implode('', $whereCalls));

        $result = $this;
        foreach ($conditions as $key => $value) {
            $result = $result->where($key, $value);
        }

        return $result;
    }

    /**
     * Returns a new SmartArray excluding elements where a field matches a value.
     * The inverse of where(). Only works on nested arrays (throws on flat).
     *
     * Uses loose comparison (==) to match where() behavior.
     *
     *     $otherPages = $pages->whereNot('num', $currentPage->num);
     *     $published  = $articles->whereNot('status', 'draft');
     *     $visible    = $records->whereNot('hidden', 1);
     *
     * @param string $field Field name to compare
     * @param mixed  $value Value to exclude
     * @return static A new SmartArray excluding matching elements
     */
    public function whereNot(string $field, mixed $value): static
    {
        $this->assertNestedArray();
        $this->warnIfMissing($field);
        $value   = self::getRawValue($value);
        $matches = [];
        foreach ($this->toArray() as $key => $row) {
            if (is_array($row) && (!array_key_exists($field, $row) || $row[$field] != $value)) {  // intentional loose comparison
                $matches[$key] = $row;
            }
        }

        return new static($matches, $this->getInternalProperties());
    }

    /**
     * Returns elements where a tab-separated list field contains the specified value.
     * Matches discrete values within tab-delimited fields (e.g., checkbox groups,
     * multi-select fields). Does not perform substring matching.
     *
     * Handles both delimited format ("\tmenu\tfooter\t") and plain single values ("menu").
     *
     *     $menuPages   = $pages->whereInList('show_on', 'menu');
     *     $footerPages = $pages->whereInList('show_on', 'footer');
     *
     * @param string $field Field name containing tab-separated values
     * @param mixed  $value Value to search for (exact match, not substring)
     * @return static A new SmartArray containing only matching elements
     *
     * @noinspection SpellCheckingInspection
     */
    public function whereInList(string $field, mixed $value): static
    {
        $this->assertNestedArray();
        $this->warnIfMissing($field);
        $value   = (string) self::getRawValue($value);
        $matches = [];
        foreach ($this->toArray() as $key => $row) {
            if (!isset($row[$field])) {
                continue;
            }
            if ($row[$field] == $value || (is_string($row[$field]) && str_contains($row[$field], "\t$value\t"))) {  // intentional loose comparison
                $matches[$key] = $row;
            }
        }

        return new static($matches, $this->getInternalProperties());
    }

    //endregion
    //region Array Transformation

    /**
     * Recursively converts SmartArray back to a standard PHP array with original values.
     *
     * - SmartArray objects are recursively converted to arrays
     * - Scalar values and nulls are returned as-is
     * - SmartNull objects are converted to null
     * - Unexpected types will throw an InvalidArgumentException
     *
     * @return array An array representation of the object's elements with original values.
     */
    public function toArray(): array
    {
        // Future options: We could add a default arg $smartStringsToValues = true to allow SmartStrings to be returned as objects
        $array = [];
        foreach ($this->data as $key => $value) {  // $this->data so getIterator doesn't convert to SmartStrings
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
     * Returns a new SmartArray containing the keys of this SmartArray.
     */
    public function keys(): static
    {
        $keys = array_keys($this->data);
        return new static($keys, $this->getInternalProperties());
    }

    /**
     * Returns a new SmartArray containing the values, re-indexed numerically.
     */
    public function values(): static
    {
        $values = array_values($this->toArray());
        return new static($values, $this->getInternalProperties());
    }


    /**
     * Creates a new SmartArray indexed by the specified field.
     *
     * This method transforms the current SmartArray (assumed to be a nested array of rows)
     * into a new SmartArray where each element is indexed by the value of the specified field.
     *
     * @param string $field The field name to index the rows by.
     *
     * @return static A new SmartArray indexed by the specified field.
     * @throws InvalidArgumentException If the SmartArray is not nested.
     *
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
    public function indexBy(string $field): static
    {
        $this->assertNestedArray();
        $this->warnIfMissing($field);

        // Index by field
        $values = array_column($this->toArray(), null, $field);
        return new static($values, $this->getInternalProperties());
    }

    /**
     * Creates a new SmartArray grouped by the specified field.
     *
     * This method transforms the current SmartArray (assumed to be a nested array of rows)
     * into a new SmartArray where each element is grouped by the value of the specified field.
     *
     * @param string $field The field name to group the rows by.
     *
     * @return static A new SmartArray grouped by the specified field.
     * @throws InvalidArgumentException If the SmartArray is not nested.
     *
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
    public function groupBy(string $field): static
    {
        $this->assertNestedArray();
        $this->warnIfMissing($field);

        $values = [];
        foreach ($this->toArray() as $row) {
            $key            = $row[$field] ?? null;
            $values[$key][] = $row;
        }

        return new static($values, $this->getInternalProperties());
    }

    /**
     * Extracts a single field from a nested SmartArray.
     *
     * This method retrieves the values of a specified key from all elements in the nested SmartArray,
     * returning them as a new SmartArray. It's particularly useful for extracting a specific field
     * from a collection of records.
     *
     * @param string|int  $valueField The key of the field to extract from each nested element.
     * @param string|null $keyField   Optional field to use as keys in the resulting array.
     * @return static A new SmartArray containing the extracted values.
     *
     * $users = new SmartArray([
     *     ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
     *     ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']
     * ]);
     * $userEmails = $users->pluck('email');                        // $userEmails is now a SmartArray: ['john@example.com', 'jane@example.com']
     * $csvEmails  = $users->pluck('email')->implode(', ')->value(); // $csvEmails is now a string: "john@example.com, jane@example.com"
     */
    public function pluck(string|int $valueField, ?string $keyField = null): static
    {
        $this->assertNestedArray();
        $this->warnIfMissing($valueField);

        $values = array_column($this->toArray(), $valueField, $keyField);
        return new static($values, $this->getInternalProperties());
    }

    /**
     * Extracts values at a specific position from each row in a nested SmartArray, ignoring key names.
     * Particularly useful for MySQL results where key names are unpredictable, like SHOW TABLES.
     *
     * @param int $index Zero-based position (supports negative indices: -1=last)
     * @return static A new SmartArray containing the extracted values.
     *
     * MySQL `SHOW TABLES LIKE 'cms_%'` returns:
     *
     * [
     *   ['Tables_in_yourDbName (cms_%)' => 'cms_accounts'],
     *   ['Tables_in_yourDbName (cms_%)' => 'cms_settings']
     *   ['Tables_in_yourDbName (cms_%)' => 'cms_pages'],
     * ]
     *
     * $tables = $resultSet->pluckNth(0);   // Position 0 (first value): Returns ["cms_accounts", "cms_settings", "cms_pages"]
     */
    public function pluckNth(int $index): static
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
        return new static($values, $this->getInternalProperties());
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
     * @return static
     */
    public function column(int|string|null $columnKey, int|string|null $indexKey = null): static
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
     * @return SmartString|string Returns string for SmartArray, SmartString for SmartArrayHtml.
     * @throws InvalidArgumentException If the SmartArray is nested.
     *
     *     $arr = SmartArray::new(['apple', 'banana', 'cherry']);
     *     $result = $arr->implode(', '); // Returns string: "apple, banana, cherry"
     *
     *     $arr = SmartArrayHtml::new(['apple', 'banana', 'cherry']);
     *     $result = $arr->implode(', '); // Returns SmartString: "apple, banana, cherry"
     */
    public function implode(string $separator = ''): SmartString|string
    {
        $this->assertFlatArray();

        $values = array_map('strval', $this->toArray());
        $value  = implode($separator, $values);

        return $this->useSmartStrings ? new SmartString($value) : $value;
    }

    /**
     * Applies sprintf formatting to each element. Always returns SmartArray (raw).
     *
     * **How it works:**
     * - **Encoding:** SmartArrayHtml encodes values/keys before insertion. SmartArray does not.
     * - **Return type:** Always returns SmartArray (raw), even when called on SmartArrayHtml.
     *   Pre-formatted HTML should not be re-encoded by subsequent operations like implode().
     * - **Don't call asHtml() on the result** - it will double-encode your output.
     * - **Placeholders:** `{value}` or `%1$s`, `{key}` or `%2$s`. All sprintf specifiers work (%d, %f, %05d, etc.).
     *
     *     // Wrap values in HTML (typical usage)
     *     $fruits->sprintf('<li>{value}</li>')->implode("\n");
     *
     *     // Select options using keys
     *     $countries->sprintf("<option value='{key}'>{value}</option>")->implode("\n");
     *
     *     // SmartArray vs SmartArrayHtml encoding
     *     $data = ["O'Brien", '<script>'];
     *     SmartArray::new($data)->sprintf('<td>{value}</td>')->implode();
     *     // <td>O'Brien</td><td><script></td>           (no encoding)
     *     SmartArrayHtml::new($data)->sprintf('<td>{value}</td>')->implode();
     *     // <td>O&apos;Brien</td><td>&lt;script&gt;</td> (HTML-encoded)
     *
     *     // sprintf specifiers work too
     *     SmartArray::new([7, 42, 185])->sprintf('%05d')->implode(', '); // 00007, 00042, 00185
     *
     * Notes: Aliases are case-sensitive (only lowercase `{value}` and `{key}`).
     * Only two parameters available (value and key). Flat arrays only.
     *
     * @param string $format sprintf format string (supports {value}/{key} aliases)
     * @return SmartArray Pre-formatted strings that won't be re-encoded on output
     * @throws InvalidArgumentException If called on a nested array
     */
    public function sprintf(string $format): SmartArray
    {
        $this->assertFlatArray();

        // Convert {value} and {key} aliases to sprintf positional format
        $format = str_replace(['{value}', '{key}'], ['%1$s', '%2$s'], $format);

        $newArray = [];
        foreach ($this as $key => $value) {
            $value      = $value instanceof SmartString ? $value->htmlEncode() : $value;
            $encodedKey = $this->useSmartStrings ? htmlspecialchars((string)$key, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8') : $key;
            $newArray[$key] = sprintf($format, $value, $encodedKey);
        }

        // Return SmartArray (raw) - sprintf output is pre-formatted and shouldn't be re-encoded
        $properties = ['useSmartStrings' => false] + $this->getInternalProperties();
        return new SmartArray($newArray, $properties);
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
     * @return static A new SmartArray containing the transformed elements.
     *
     *     $arr   = new SmartArray(['apple', 'banana', 'cherry']);
     *     $upper = $arr->map(fn(string $fruit) => strtoupper($fruit));
     *     // $upper is now a SmartArray: ['APPLE', 'BANANA', 'CHERRY']
     *
     *     $nested = new SmartArray([['a' => 1], ['a' => 2]]);
     *     $values = $nested->map(fn(array $item) => $item['a']);
     *     // $values is now a SmartArray: [1, 2]
     */
    public function map(callable $callback): static
    {
        $newArray  = [];
        $isClosure = $callback instanceof Closure;
        foreach ($this->toArray() as $key => $rawValue) {
            // For closures, pass both $value and $key, but not for non-Closure callbacks to avoid unexpected behavior, e.g., intval($value, $base) would misinterpret $key as $base
            $newArray[$key] = $isClosure ? $callback($rawValue, $key) : $callback($rawValue);
        }

        return new static($newArray, $this->getInternalProperties());
    }

    /**
     * Calls the given callback on each element, primarily for side effects.
     * Returns $this for chaining.
     *
     * For SmartArrayHtml: callback receives SmartString values (or nested SmartArrayHtml).
     * For SmartArray: callback receives raw PHP values (or nested SmartArray).
     *
     *     $users->each(function($user, $key) {
     *         echo "$user->num - $user->name\n";
     *     });
     *
     * If you need to transform or collect results, consider ->map() instead.
     *
     * @param Closure $callback A callback: fn($value, int|string $key): void
     * @return $this
     */
    public function each(Closure $callback): static
    {
        foreach (array_keys($this->data) as $key) {
            $smartValue = $this->getElement($key);
            $callback($smartValue, $key);
        }

        return $this;
    }

    /**
     * Merges the SmartArray with one or more arrays or SmartArrays.
     * Numeric keys are renumbered, string keys are overwritten by later values.
     *
     * @param array|SmartArrayBase ...$arrays Arrays to merge with
     * @return static Returns a new SmartArray with the merged results
     *
     * $arr1 = SmartArray::new(['a' => 1, 'b' => 2]);
     * $arr2 = ['b' => 3, 'c' => 4];
     * $arr3 = SmartArray::new(['d' => 5]);
     *
     * $result = $arr1->merge($arr2, $arr3);
     * // ['a' => 1, 'b' => 3, 'c' => 4, 'd' => 5]
     */
    public function merge(array|SmartArrayBase ...$arrays): static
    {
        $arrays = array_map([self::class, 'getRawValue'], $arrays); // convert SmartArrays to arrays
        $merged = array_merge($this->toArray(), ...$arrays);
        return new static($merged, $this->getInternalProperties());
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
            return $this->mysqli ?? [];
        }

        // return specific mysqli property
        $resultInfo = $this->mysqli;
        return $resultInfo[$property] ?? null;
    }

    /**
     * Lazy-load related data using the registered load handler.
     * Returns SmartNull if the array is empty.
     *
     *     $user->load('orders');   // SmartArray of related orders
     *
     * @param string $field The relationship field name to load.
     * @return static|SmartNull Loaded data as SmartArray, or SmartNull if array is empty.
     * @throws RuntimeException If no load handler is defined or field has invalid characters.
     * @throws InvalidArgumentException If field name is empty.
     */
    public function load(string $field): static|SmartNull
    {
        // return SmartNull if array is empty (or is SmartNull already)
        if (empty($this->data)) {
            return $this->newSmartNull();
        }

        // get load handler
        $loadHandler = $this->loadHandler;

        // error checking
        match (true) {
            !$loadHandler                        => throw new RuntimeException("No loadHandler property is defined"),
            !is_callable($loadHandler)           => throw new RuntimeException("Load handler is not callable"),
            empty($field)                        => throw new InvalidArgumentException("Field name is required for load() method."),
            (bool)preg_match('/[^\w-]/', $field) => throw new RuntimeException("Field name contains invalid characters: $field"),
            $this->isNested()                    => throw new RuntimeException("Cannot call load() on record set, only on a single row."),
            default                              => null,
        };

        // get handler output
        $result = $loadHandler($this, $field);
        if ($result === false) {
            throw new Error("Load handler not available for '$field'\n" . self::occurredInFile());
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
            'useSmartStrings' => $this->useSmartStrings, // persist smart strings setting
            'loadHandler'     => $this->loadHandler,     // persist load handler
            'mysqli'          => $mysqliProperties ?? [],
            //'root'          => // skipped, set by constructor to self
            //'isFirst'       => // skipped, instance defaults are accurate for root array
            //'isLast'        => // skipped, instance defaults are accurate for root array
            //'position'      => // skipped, instance defaults are accurate for root array
        ]);
    }

    /**
     * Set the load handler for lazy-loading nested arrays.
     *
     * @param callable $customLoadHandler Handler: fn(SmartArray $row, string $field): array|false
     * @noinspection PhpUnused
     */
    public function setLoadHandler(callable $customLoadHandler): void
    {
        $this->loadHandler = $customLoadHandler;
    }

    /**
     * Return the root SmartArray object for nested arrays, or the current object if not nested.
     */
    public function root(): self
    {
        return $this->root;
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
     * Displays diagnostic output: array contents, mysqli metadata, and object properties.
     *
     * @param int $debugLevel 0 for compact, 1+ for verbose with type info and object IDs
     */
    public function debug(int $debugLevel = 0): void
    {
        // show data header
        $className = static::class;
        $output    = match ($this->useSmartStrings) {
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
            $properties         = $this->getInternalProperties(); // gets public properties
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
            $arrayCopy    = is_array($var) ? $var : $var->data;
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
     */
    private static function xmpWrap($output): string
    {
        $output             = trim($output, "\n");
        $headersList        = implode("\n", headers_list());
        $hasContentType     = (bool)preg_match('|^\s*Content-Type:\s*|im', $headersList);                          // assume no content type will default to HTML
        $isTextHtml         = !$hasContentType || preg_match('|^\s*Content-Type:\s*text/html\b|im', $headersList); // match: text/html or ...;charset=utf-8
        $backtraceFunctions = array_map('strtolower', array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'function'));
        $wrapInXmp          = $isTextHtml && !in_array('showme', $backtraceFunctions, true);
        return $wrapInXmp ? "\n<xmp>\n$output\n</xmp>\n" : "\n$output\n";
    }

    /**
     * Customizes print_r() and var_dump() output for this object.
     * Comment out this method to see all internal properties while debugging.
     */
    public function __debugInfo(): array
    {
        // show help information for root array (but not for every child array)
        $output = [];
        if ($this === $this->root()) {
            // Call ->help() for usage examples and documentation, or ->debug() to view metadata
            $output["README:" . basename(static::class) . ":private"] = "Call \$obj->help() for documentation, or ->debug() to view metadata";
            $output["*useSmartStrings*:private"] = match ($this->useSmartStrings) {
                true  => "true, // Values are returned as SmartString objects on access\n",
                false => "false, // Values are returned **as-is** on access (no extra encoding)\n",
            };
        }

        // show array data
        $output += $this->data;
        return $output;
    }

    /**
     * Magic method for property access: $array->key
     *
     * This is the preferred way to access array elements.
     * For keys with special characters or numeric keys, use ->get('key') instead.
     */
    public function __get(string $name): static|SmartNull|SmartString|string|int|float|bool|null
    {
        // Access array element (preferred method - no deprecation warning)
        return $this->getElement($name);
    }

    /**
     * Magic method for property assignment: $array->key = $value
     *
     * This is the preferred way to set array elements.
     * For keys with special characters or numeric keys, use ->set('key', $value) instead.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setElement($name, $value);
    }

    /**
     * Magic method for isset($array->key) and empty($array->key)
     */
    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Magic method for unset($array->key)
     */
    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    //endregion
    //region Error Handling

    /**
     * Sends a 404 header and message if the array is empty, then exits.
     *
     * @param string|null $message The message to display when sending 404.
     * @return static Returns $this if not empty, exits with 404 if empty
     */
    public function or404(?string $message = null): static
    {
        if (!empty($this->data)) {
            return $this;
        }

        // Send 404 header and message
        http_response_code(404);
        header("Content-Type: text/html; charset=utf-8");
        $message ??= "The requested URL was not found on this server.";
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');

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
     * @return static Returns $this for method chaining if not empty, dies if empty
     */
    public function orDie(string $message): static
    {
        if (empty($this->data)) {
            $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');
            die($message);
        }
        return $this;
    }

    /**
     * Throws RuntimeException if the array is empty
     *
     * @param string $message Error message to show
     * @return static Returns $this for method chaining if not empty
     * @throws RuntimeException If array is empty
     */
    public function orThrow(string $message): static
    {
        if (empty($this->data)) {
            $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');
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
     * @return static Returns $this for method chaining if not empty, redirects if empty
     * @throws RuntimeException If headers have already been sent
     */
    public function orRedirect(string $url): static
    {
        // Check early so developers find out immediately, not only when count === 0
        if (headers_sent($file, $line)) {
            throw new RuntimeException("orRedirect(): headers already sent in $file on line $line");
        }

        if (empty($this->data)) {
            http_response_code(302);
            header("Location: $url");
            exit;
        }
        return $this;
    }

    /**
     * Assert that array has no nested arrays.
     *
     * @throws InvalidArgumentException If the array is nested.
     */
    private function assertFlatArray(): void
    {
        if (!empty($this->data) && $this->isNested()) {
            $function = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
            $error    = "$function(): Expected a flat array, but got a nested array";
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * Assert that array has at least one nested array in values.
     *
     * @throws InvalidArgumentException If the array is flat.
     */
    private function assertNestedArray(): void
    {
        if (!empty($this->data) && $this->isFlat()) {
            $function = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
            $error    = "$function(): Expected a nested array, but got a flat array";
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * Emits a PHP warning if $key is missing. Skips the check when the array is empty.
     * Skipped for 'argument' checks on mixed data (scalar config + array fields)
     * since there's no first row to check against.
     *
     * @param string|int $key         The key to check for
     * @param string     $warningType 'argument' for method args (default), 'offset' for key access
     */
    private function warnIfMissing(string|int $key, string $warningType = 'argument'): void
    {
        // For nested method args (where, sortBy, etc.) - check first row's keys
        // For property access (offset) - check this array's own keys
        $first  = $this->first();
        if ($warningType === 'argument' && !($first instanceof self)) {
            return; // Non-uniform data (e.g., schemas with scalar config + array fields)
        }
        $target = $first instanceof self ? $first : $this;
        if (empty($target->data) || $target->offsetExists($key)) {
            return;
        }
        $caller           = self::getExternalCaller();
        $keyOrEmptyQuotes = $key === "" ? "''" : $key; // Show empty quotes for empty string keys

        $warning = match ($warningType) {
            'offset'   => "$keyOrEmptyQuotes is undefined in {$caller['file']}:{$caller['line']}\n",
            'argument' => "{$caller['function']}(): '$key' doesn't exist\n",
            default    => throw new InvalidArgumentException("Invalid warning type '$warningType'"),
        };

        // Catch if user tried to call a method in a double-quoted string without braces
        if (is_string($key) && method_exists($this, $key)) { // Catch cases such as "Nums: $users->pluck('num')->implode(',')->value();" which are missing braces
            $warning .= "\nIn double-quoted strings, use \"\$var->property\" for properties, but wrap methods in braces like \"{\$var->method()}\"";
        }
        if ($warningType === 'argument') {
            $warning .= self::occurredInFile(true);
        }

        // Emulate PHP warning: output warning and trigger PHP warning (for logging)
        echo "\nWarning: $warning\n";                  // Output with echo so PHP doesn't add the filename and line number of this function on the end
        @trigger_error($warning, E_USER_WARNING);      // Trigger a PHP warning but hide output with @ so it will still get logged
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
        $warning .= "In double-quoted strings, use \"\$var->property\" for properties, but wrap methods in braces like \"{\$var->method()}\"\n\n";
        $warning .= "For more info: \$var->help()";

        // output warning
        echo "\nWarning: $warning\n\n";           // Output with echo so PHP doesn't add the filename and line number of this function on the end
        @trigger_error($warning, E_USER_WARNING); // Trigger a PHP warning but hide output with @ so it will still get logged
        return "";
    }

    //endregion
    //region Internal Methods

    /**
     * Return a new SmartNull object with internal properties from the current SmartArray.
     */
    public function newSmartNull(): SmartNull
    {
        return new SmartNull($this->getInternalProperties());
    }

    /**
     * Check if array doesn't contain any nested arrays.
     */
    private function isFlat(): bool
    {
        return !$this->isNested();
    }

    /**
     * Check if array contains ANY nested arrays.  Does not check if all values are arrays, only if any are.
     */
    private function isNested(): bool
    {
        foreach ($this->data as $value) {
            if ($value instanceof self) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns a generator that yields elements, wrapping scalars in SmartString when enabled.
     * Nested SmartArrays are yielded as-is (not wrapped).
     */
    public function getIterator(): Iterator
    {
        // Return an iterator that yields encoded values for each element
        foreach ($this->data as $key => $value) {
            yield $key => $this->useSmartStrings && !$value instanceof self
                ? new SmartString($value)
                : $value;
        }
    }

    /**
     * Returns serializable data for `json_encode()` via JsonSerializable.
     * Returns the raw internal array so nested SmartArrays serialize as plain arrays.
     *
     * @return array The internal data array.
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }


    //endregion
    //region Instance Properties

    /**
     * Internal properties for SmartArray behavior.
     * These are set on creation and passed to nested SmartArrays.
     */
    private bool $useSmartStrings = false;
    protected mixed $loadHandler = null;       // Handler for lazy-loading nested arrays
    protected array $mysqli = [];              // Metadata from last mysqli result
    private ?self $root = null;                // The root SmartArray

    /**
     * Check if SmartStrings mode is enabled.
     */
    public function usingSmartStrings(): bool
    {
        return $this->useSmartStrings;
    }

    /**
     * Returns an array of internal properties for passing to nested SmartArrays or type conversions.
     * Does NOT include useSmartStrings since child classes force their own values.
     */
    protected function getInternalProperties(): array
    {
        return [
            'loadHandler' => $this->loadHandler,
            'mysqli'      => $this->mysqli,
            'root'        => $this->root,
        ];
    }

    //endregion

}
