<?php
/** @noinspection SenselessProxyMethodInspection */
declare(strict_types=1);

namespace Itools\SmartArray;

use RuntimeException, InvalidArgumentException;
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
 * PhpStorm 2025.3.1: Repeated @implements needed - union types in Iterator generics don't work reliably for foreach inference
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

    /**
     * Retrieves an element from the SmartArray, or a SmartNull if not found, providing an alternative to $array[$key] or $array->key syntax.
     * If the key doesn't exist, a SmartNull object is returned to allow further chaining.
     */
    public function get(int|string $key, mixed $default = null): static|SmartNull|string|int|float|bool|null
    {
        // Must use func_num_args() check here and call parent appropriately,
        // because parent uses func_num_args() to detect if default was provided
        if (func_num_args() >= 2) {
            return parent::get($key, $default);
        }
        return parent::get($key);
    }

    /**
     * Get first element in array, or SmartNull if array is empty (to allow for further chaining).
     */
    public function first(): static|SmartNull|string|int|float|bool|null
    {
        return parent::first();
    }

    /**
     * Get last element in array, or SmartNull if array is empty (to allow for further chaining).
     */
    public function last(): static|SmartNull|string|int|float|bool|null
    {
        return parent::last();
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
    public function nth(int $index): static|SmartNull|string|int|float|bool|null
    {
        return parent::nth($index);
    }

    //endregion
    //region Position & Layout

    /**
     * Splits the SmartArray into smaller SmartArrays of a specified size.
     *
     * This method divides the current SmartArray into multiple SmartArrays, each containing
     * at most the specified number of elements. The last chunk may contain fewer elements
     * if the original SmartArray's count is not divisible by the chunk size.
     *
     * @param int $size The size of each chunk. Must be greater than 0.
     * @return SmartArray A new SmartArray containing SmartArrays of the specified size.
     * @example
     * $arr = new SmartArray([1, 2, 3, 4, 5, 6, 7]);
     * $chunks = $arr->chunk(3); // $chunks is now a SmartArray containing:
     * [
     *     SmartArray([1, 2, 3]),
     *     SmartArray([4, 5, 6]),
     *     SmartArray([7])
     * ]
     */
    public function chunk(int $size): static
    {
        return parent::chunk($size);
    }

    //endregion
    //region Sorting & Filtering

    /**
     * Returns a new array sorted by values, using PHP sort() function.
     */
    public function sort(int $flags = SORT_REGULAR): static
    {
        return parent::sort($flags);
    }

    /**
     * Returns a new SmartArray sorted by the specified column, using PHP array_multisort().
     */
    public function sortBy(string $column, int $type = SORT_REGULAR): static
    {
        return parent::sortBy($column, $type);
    }

    /**
     * Returns a new array with duplicate values removed, keeping only the first
     * occurrence of each unique value, and preserving keys.
     */
    public function unique(): static
    {
        return parent::unique();
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
     * @return SmartArray A new SmartArray containing only the elements that passed the callback test.
     */
    public function filter(?callable $callback = null): static
    {
        return parent::filter($callback);
    }

    /**
     * Returns a new SmartArray containing only the array elements where all conditions match.
     * Elements that are not arrays are automatically skipped.
     *
     * Uses loose comparison (==) to allow matching between different types (e.g., '1' == 1).
     *
     * @param array|string $conditions
     * @param mixed|null $value
     * @return SmartArray A new SmartArray containing only matching elements
     */
    public function where(array|string $conditions, mixed $value = null): static
    {
        return parent::where($conditions, $value);
    }

    //endregion
    //region Array Transformation

    /**
     * Returns a new array of keys
     */
    public function keys(): static
    {
        return parent::keys();
    }

    /**
     * Returns a new array of values
     */
    public function values(): static
    {
        return parent::values();
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
    public function indexBy(string $column): static
    {
        return parent::indexBy($column);
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
    public function groupBy(string $column): static
    {
        return parent::groupBy($column);
    }

    /**
     * Extracts a single column from a nested SmartArray.
     *
     * This method retrieves the values of a specified key from all elements in the nested SmartArray,
     * returning them as a new SmartArray. It's particularly useful for extracting a specific field
     * from a collection of records.
     *
     * @param string|int $valueColumn The key of the column to extract from each nested element.
     * @param string|null $keyColumn
     * @return SmartArray A new SmartArray containing the extracted values.
     * @example
     * $users = new SmartArray([
     *     ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
     *     ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']
     * ]);
     * $userEmails = $users->pluck('email');                        // $userEmails is now a SmartArray: ['john@example.com', 'jane@example.com']
     * $csvEmails  = $users->pluck('email')->implode(', ')->value(); // $csvEmails is now a string: "john@example.com, jane@example.com"
     */
    public function pluck(string|int $valueColumn, ?string $keyColumn = null): static
    {
        return parent::pluck($valueColumn, $keyColumn);
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
    public function pluckNth(int $index): static
    {
        return parent::pluckNth($index);
    }

    /**
     * Extract a column of values, optionally indexed by another column.
     * Mirrors PHP's array_column() - accepts int keys for numeric-indexed arrays.
     *
     * @param int|string|null $columnKey Column to extract (null = entire rows via indexBy)
     * @param int|string|null $indexKey  Column to use as array keys
     * @return SmartArray
     */
    public function column(int|string|null $columnKey, int|string|null $indexKey = null): static
    {
        return parent::column($columnKey, $indexKey);
    }

    /**
     * Joins the elements of the SmartArray into a single string with a specified separator.
     *
     * This method works on flat SmartArrays only. For SmartString elements,
     * their original values are used in the resulting string.
     *
     * @param string $separator The string to use as a separator between elements.
     *
     * @return string The resulting string after joining all elements (raw value, not SmartString).
     * @throws InvalidArgumentException If the SmartArray is nested.
     *
     * @example
     * $arr = SmartArray::new(['apple', 'banana', 'cherry']);
     * $result = $arr->implode(', '); // Returns string: "apple, banana, cherry"
     */
    public function implode(string $separator = ''): string
    {
        return parent::implode($separator);
    }

    /**
     * Applies sprintf formatting to each element and returns SmartArray.
     *
     * Supports two placeholder styles:
     * - Standard sprintf: `%s` (value), `%1$s` (value), `%2$s` (key)
     * - Named aliases: `{value}` and `{key}` (converted to sprintf format internally)
     *
     * Values pass through as-is (no HTML encoding).
     *
     * Example:
     *
     *     $row = SmartArray::new(['apple', 'banana']);
     *     $row->sprintf("<li>%s</li>")->implode();
     *     // Output: <li>apple</li><li>banana</li>
     *
     *     $options = SmartArray::new(['us' => 'United States', 'ca' => 'Canada']);
     *     $options->sprintf("<option value='{key}'>{value}</option>")->implode();
     *     // Output: <option value='us'>United States</option><option value='ca'>Canada</option>
     *
     * @param string $format The sprintf format string (also supports {value}/{key} aliases)
     * @return SmartArray Pre-formatted strings
     */
    public function sprintf(string $format): SmartArray
    {
        return parent::sprintf($format);
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
     * @return SmartArray A new SmartArray containing the transformed elements.
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
    public function map(callable $callback): static
    {
        return parent::map($callback);
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
     * @return SmartArray A new SmartArray containing the transformed elements.
     *
     * @example
     *  $arr = new SmartArray(['hello', 'world'], true); // with SmartStrings
     *  $exclaimed = $arr->smartMap(fn($str, $k) => $str->upper()->append('!'));
     *  // $exclaimed -> ['HELLO!', 'WORLD!']
     */
    public function smartMap(closure $callback): static
    {
        return parent::smartMap($callback);
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
    public function each(Closure $callback): static
    {
        return parent::each($callback);
    }

    /**
     * Merges the SmartArray with one or more arrays or SmartArrays.
     * Numeric keys are renumbered, string keys are overwritten by later values.
     *
     * @param array|SmartArrayBase ...$arrays Arrays to merge with
     * @return SmartArray Returns a new SmartArray with the merged results
     *
     * @example
     * $arr1 = SmartArray::new(['a' => 1, 'b' => 2]);
     * $arr2 = ['b' => 3, 'c' => 4];
     * $arr3 = SmartArray::new(['d' => 5]);
     *
     * $result = $arr1->merge($arr2, $arr3);
     * // ['a' => 1, 'b' => 3, 'c' => 4, 'd' => 5]
     */
    public function merge(array|SmartArrayBase ...$arrays): static
    {
        return parent::merge(...$arrays);
    }

    //endregion
    //region Database Operations

    /**
     * Returns SmartArray or throws an exception load() handler is not available for column
     * @throws RuntimeException
     */
    public function load(string $column): static|SmartNull
    {
        return parent::load($column);
    }

    //endregion
    //region Debugging and Help

    //endregion
    //region Error Handling

    /**
     * Sends a 404 header and message if the array is empty, then exits.
     *
     * @param string|null $message The message to display when sending 404.
     * @return SmartArray Returns self if not empty, exits with 404 if empty
     */
    public function or404(?string $message = null): static
    {
        return parent::or404($message);
    }

    /**
     * Dies with a message if the array is empty
     *
     * @param string $message Error message to show
     * @return SmartArray Returns $this for method chaining if not empty, dies if empty
     */
    public function orDie(string $message): static
    {
        return parent::orDie($message);
    }

    /**
     * Throws Exception if the array is empty
     *
     * @param string $message Error message to show
     * @return SmartArray Returns $this for method chaining if not empty
     * @throws RuntimeException If array is empty
     */
    public function orThrow(string $message): static
    {
        return parent::orThrow($message);
    }

    /**
     * Redirects to a URL if the array is empty
     *
     * Uses a simple Location header redirect (HTTP 302 Temporary Redirect).
     * If headers have already been sent, this method will throw an exception.
     *
     * @param string $url The URL to redirect to if array is empty
     * @return SmartArray Returns $this for method chaining if not empty, redirects if empty
     * @throws RuntimeException If headers have already been sent
     */
    public function orRedirect(string $url): static
    {
        return parent::orRedirect($url);
    }

    //endregion
    //region Deprecated Array Access

    /**
     * Retrieves a value from the SmartArray using array syntax.
     *
     * @deprecated Use ->property or ->get('key') instead of $array['key']
     */
    public function offsetGet(mixed $offset, ?bool $useSmartStrings = null): static|SmartNull|string|int|float|bool|null
    {
        return parent::offsetGet($offset, $useSmartStrings);
    }

    //endregion
}
