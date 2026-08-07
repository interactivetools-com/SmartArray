<?php
/** @noinspection PhpLoopCanBeConvertedToArrayFilterInspection - foreach is faster than array_filter with closure */
declare(strict_types=1);
namespace Itools\SmartArray;

use stdClass;
use Throwable, Error, InvalidArgumentException, RuntimeException;
use ArrayAccess, ArrayIterator, IteratorAggregate, Iterator, Countable, JsonSerializable, Closure, ReflectionFunction;
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
    use SharedHelpers;
    use Deprecations;

    //region Internal Storage

    /**
     * Internal array storage (replaces ArrayObject's internal storage)
     */
    private array $data = [];

    /**
     * True while every value is a child SmartArray (or the array is empty), so
     * getIterator() can skip SmartString wrapping and iterate the fast way.
     * Storing a scalar or null sets this false, and nothing sets it back (an
     * unset can leave it stale-false, which only means the slower,
     * always-correct wrapping path).
     */
    private bool $rowsOnly = true;

    /**
     * True once any child SmartArray has been stored, so toArray() can hand back
     * internal data as-is (copy-on-write) when there are no children to convert.
     * Never cleared on unset: a stale true only means the slower, always-correct
     * element-by-element rebuild.
     */
    private bool $hasRows = false;

    /**
     * The original rows fromDatabaseRows() was given, so toArray() can return them
     * without rebuilding. Nearly free to keep: the child rows share the same value
     * storage via copy-on-write. Any write to this array or one of its rows clears
     * it (see setElement()), so a stale copy can never be returned.
     */
    private ?array $sourceRows = null;

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
     * @param array $array      The input array to convert into a SmartArray.
     * @param array $properties An associative array of internal properties.
     *                          (Legacy boolean arguments are handled by the SmartArray/SmartArrayHtml constructors.)
     *
     * @noinspection UnusedConstructorDependenciesInspection
     */
    public function __construct(array $array = [], array $properties = [])
    {
        // Set internal properties from the known keys (a fixed list is faster than
        // property_exists() per key, and internal storage like $data stays private)
        $this->useSmartStrings = $properties['useSmartStrings'] ?? $this->useSmartStrings;
        $this->loadHandler     = $properties['loadHandler']     ?? null;
        $this->mysqli          = $properties['mysqli']          ?? [];
        $this->root            = $properties['root']            ?? $this;
        $this->position        = $properties['position']        ?? 0;
        $this->isFirst         = $properties['isFirst']         ?? false;
        $this->isLast          = $properties['isLast']          ?? false;

        // Add elements and set position metadata on child SmartArrays
        $count         = count($array);
        $position      = 0;
        $childTemplate = null;
        foreach ($array as $key => $value) {
            $position++;

            // Fast path: scalars and nulls, the bulk of real data (encoded on access by getElement)
            if (is_scalar($value) || $value === null) {
                $this->data[$key] = $value;
                $this->rowsOnly   = false;
                continue;
            }

            // Nested arrays become child rows. The template child is built once and cloned
            // per row (cheaper than running the constructor), and all-scalar rows - typical
            // database rows - assign their data wholesale: a copy-on-write array assignment
            // instead of a per-field loop.
            if (is_array($value)) {
                $childTemplate ??= new static([], $this->getInternalProperties());

                $allScalar = true;
                foreach ($value as $fieldValue) {
                    if (!is_scalar($fieldValue) && $fieldValue !== null) {
                        $allScalar = false;
                        break;
                    }
                }
                if ($allScalar) {
                    $child           = clone $childTemplate;
                    $child->data     = $value;
                    $child->rowsOnly = $value === [];
                }
                else {
                    $child = new static($value, $this->getInternalProperties());
                }
                $child->position   = $position;
                $child->isFirst    = $position === 1;
                $child->isLast     = $position === $count;
                $this->data[$key]  = $child;
                $this->hasRows     = true;
                continue;
            }

            // Rare: Smart values unwrap, unsupported types throw
            $this->setElement($key, $value);
            $element = $this->data[$key];
            if ($element instanceof self) {
                $element->position = $position;
                $element->isFirst  = $position === 1;
                $element->isLast   = $position === $count;
            }
        }
    }

    /**
     * Builds a result set from trusted database rows: a list of flat arrays with
     * scalar or null values, the shape mysqli's fetch_all(MYSQLI_ASSOC) returns.
     * ZenDB calls this for query results; call it yourself only when rows have
     * exactly that shape - nested arrays or objects inside a row are not converted
     * here, use the constructor for those.
     *
     * Skips the per-field type scan the constructor needs on unknown input, and
     * keeps the original rows so toArray() can return them without rebuilding.
     *
     *     $resultSet = SmartArrayHtml::fromDatabaseRows($result->fetch_all(MYSQLI_ASSOC));
     *     $resultSet = SmartArray::fromDatabaseRows($rows, ['loadHandler' => $handler]);
     *
     * @internal ZenDB result-set plumbing; interface may change between releases
     * @param array $rows       List of flat arrays with scalar/null values
     * @param array $properties Internal properties, same keys as the constructor
     */
    public static function fromDatabaseRows(array $rows, array $properties = []): static
    {
        $resultSet = new static([], $properties);
        $count     = count($rows);
        if ($count === 0) {
            return $resultSet;
        }

        // Same template-clone row building as the constructor, minus the per-field scan
        $childTemplate = new static([], $resultSet->getInternalProperties());
        $position      = 0;
        foreach ($rows as $key => $row) {
            $position++;
            $child                 = clone $childTemplate;
            $child->data           = $row;
            $child->rowsOnly       = $row === [];
            $child->position       = $position;
            $child->isFirst        = $position === 1;
            $child->isLast         = $position === $count;
            $resultSet->data[$key] = $child;
        }
        $resultSet->hasRows    = true;
        $resultSet->sourceRows = $rows;
        return $resultSet;
    }

    /**
     * Return values as raw PHP types for data processing.
     *
     * Returns the same object if already SmartArray, otherwise creates a new one.
     * Builds a new object and modifies nothing else: the original keeps working
     * unchanged, and root() on the new object still returns the result set it
     * came from, in that result set's original class.
     *
     * @return SmartArray This object if already raw, or a new SmartArray instance
     */
    abstract public function asRaw(): SmartArray;

    /**
     * Return values as HTML-safe SmartString objects.
     *
     * Returns the same object if already SmartArrayHtml, otherwise creates a new one.
     * Builds a new object and modifies nothing else: the original keeps working
     * unchanged, and root() on the new object still returns the result set it
     * came from, in that result set's original class.
     *
     * @return SmartArrayHtml This object if already HTML-safe, or a new SmartArrayHtml instance
     */
    abstract public function asHtml(): SmartArrayHtml;

    //endregion
    //region Value Access

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
     * Returns SmartNull if out of bounds, or if the index is a missing key (SmartNull) or a
     * non-numeric value - a bad position in means a missing value out, so chains survive.
     * Use $array->key for access by key; at() is by position.
     *
     *     $result = DB::query("SELECT MAX(`order`) FROM `uploads`");
     *     $max    = $result->first()->at(0); // Get unaliased column by position
     */
    public function at(int|SmartString|SmartNull $index): static|SmartNull|SmartString|string|int|float|bool|null
    {
        // Unwrap Smart indexes so positions read from another array work directly (MySQL returns
        // numeric strings). Missing keys (SmartNull) and non-numeric values stay missing.
        if ($index instanceof SmartNull) {
            return $this->newSmartNull();
        }
        if ($index instanceof SmartString) {
            if (!is_numeric($index->value())) {
                return $this->newSmartNull();
            }
            $index = (int) $index->value();
        }

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
     * Smart values are unwrapped first, so `$a->key = $b->key` works in any mode.
     */
    private function setElement(int|string|null $key, mixed $value): void
    {
        // A write makes kept source rows stale: this array's own copy, and its result
        // set's when this is a row ($this->root is the result set on rows, $this on
        // top-level arrays)
        $this->sourceRows       = null;
        $this->root->sourceRows = null;

        // Unwrap Smart values (SmartString, SmartArray, SmartNull) to their raw
        // equivalents; nested arrays then convert to this array's mode below.
        // The is_object() gate keeps the common scalar case to one cheap check.
        if (is_object($value) && ($value instanceof SmartBase || $value instanceof SmartString)) {
            $value = self::getRawValue($value);
        }

        // Store scalars and nulls as-is (encoded on access by getElement)
        if (is_scalar($value) || is_null($value)) {
            if ($key === null) {
                $this->data[] = $value;
            }
            else {
                $this->data[$key] = $value;
            }
            $this->rowsOnly = false;
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
            $this->hasRows = true;
            return;
        }

        // Throw an exception for unsupported types or anything else
        $error = sprintf("SmartArray doesn't support %s values. Key %s", get_debug_type($value), $key);
        throw new InvalidArgumentException($error);
    }

    /**
     * Returns the element at the given key, optionally wrapped in SmartString.
     * Returns SmartNull with a warning if the key doesn't exist.
     */
    private function getElement(int|string $key): static|SmartNull|SmartString|string|int|float|bool|null
    {
        // Return value if key exists, or SmartNull if not found
        if (array_key_exists($key, $this->data)) {
            $value = $this->data[$key];
            return $this->useSmartStrings && !$value instanceof self
                ? new SmartString($value)
                : $value;
        }

        // Key doesn't exist: warn if this is a result-set row (see warnIfMissing)
        $this->warnIfMissing($key, isOffset: true);

        return $this->newSmartNull();
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
     * Check if array contains a specific value (loose == comparison).
     *
     * Loose comparison means types don't need to match: contains('1') matches
     * 1 and true, and contains(null) matches '' and false. For strict matching
     * use in_array($value, $arr->toArray(), true).
     */
    public function contains(mixed $value): bool
    {
        return in_array(self::getRawValue($value), $this->toArray());
    }

    //endregion
    //region Sorting & Filtering

    /**
     * Returns a new SmartArray sorted ascending by values, using PHP sort() function.
     * Only works on flat arrays (throws on nested).
     *
     * $flags sets how values compare, not the direction - sorting is always ascending:
     * - SORT_REGULAR       - default, PHP's normal comparison rules (numbers before strings)
     * - SORT_NUMERIC       - compare as numbers
     * - SORT_STRING        - compare as strings
     * - SORT_NATURAL       - natural order for embedded numbers: "item10" sorts after "item9"
     * - SORT_LOCALE_STRING - compare as strings using the current locale
     * - SORT_FLAG_CASE     - case-insensitive, combined with a string flag: SORT_NATURAL|SORT_FLAG_CASE
     * - SORT_ASC/SORT_DESC - throws: direction constants, not comparison flags
     */
    public function sort(int $flags = SORT_REGULAR): static
    {
        $this->assertFlatArray();
        if ($flags === SORT_ASC || $flags === SORT_DESC) {
            throw new InvalidArgumentException("sort(): sorting is always ascending, SORT_ASC/SORT_DESC are directions, not comparison flags. For descending, sort in SQL with ORDER BY ... DESC");
        }

        $sorted = $this->toArray();
        sort($sorted, $flags);
        return new static($sorted, $this->getInternalProperties());
    }

    /**
     * Returns a new SmartArray sorted ascending by the specified field.
     * Only works on nested arrays (throws on flat).
     *
     * Rows missing the field sort first: the missing value counts as null for
     * ordering only (like MySQL ORDER BY), and rows are returned unchanged.
     *
     * Numeric row keys are re-indexed; string keys are preserved
     * (array_multisort() default behavior).
     *
     * $flags sets how field values compare, not the direction - sorting is always ascending:
     * - SORT_REGULAR       - default, PHP's normal comparison rules (numbers before strings)
     * - SORT_NUMERIC       - compare as numbers
     * - SORT_STRING        - compare as strings
     * - SORT_NATURAL       - natural order for embedded numbers: "item10" sorts after "item9"
     * - SORT_LOCALE_STRING - compare as strings using the current locale
     * - SORT_FLAG_CASE     - case-insensitive, combined with a string flag: SORT_NATURAL|SORT_FLAG_CASE
     * - SORT_ASC/SORT_DESC - throws: direction constants, not comparison flags
     */
    public function sortBy(string $field, int $flags = SORT_REGULAR): static
    {
        $this->assertNestedArray();
        if ($flags === SORT_ASC || $flags === SORT_DESC) {
            throw new InvalidArgumentException("sortBy(): sorting is always ascending, SORT_ASC/SORT_DESC are directions, not comparison flags. For descending, sort in SQL with ORDER BY ... DESC");
        }
        $this->warnIfMissing($field);

        // sort by field value, treating missing fields as null (?? also covers non-array rows in mixed data)
        $sorted      = $this->toArray();
        $fieldValues = array_map(fn($row) => $row[$field] ?? null, $sorted);
        array_multisort($fieldValues, SORT_ASC, $flags, $sorted);

        return new static($sorted, $this->getInternalProperties());
    }

    /**
     * Returns a new SmartArray with duplicate values removed, keeping only the first
     * occurrence of each unique value, and preserving keys.
     * Only works on flat arrays (throws on nested).
     *
     * Values are compared as strings (array_unique() default): 1, '1', and true
     * count as duplicates; '', false, and null count as duplicates of each other.
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
     * Keys are preserved, like PHP's array_filter(), so a filtered list json_encodes as an
     * object ({"0":...,"2":...}) - chain ->values() first to reindex and get a JSON array.
     *
     *     $active   = $users->filter(fn($row) => $row['status'] === 'active');
     *     $nonEmpty = $values->filter();
     *     $json     = json_encode($values->filter()->values());  // reindex for a JSON array
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
     * With just a field name, keeps rows where that field is non-empty
     * (PHP empty() rule: NULL, false, 0, "0", "", and missing fields are empty).
     *
     *     $active   = $users->where('status', 'active');
     *     $admins   = $users->where('status', 'active')->where('role', 'admin');
     *     $featured = $products->where('featured');
     *
     * @param array|string $field Field name to compare, or associative array of field=>value pairs (deprecated)
     * @param mixed        $value Value to match (supports SmartString, automatically unwrapped)
     * @return static A new SmartArray containing only matching elements
     */
    public function where(array|string $field, mixed $value = null): static
    {
        $this->assertNestedArray();

        // Single-argument syntax: where('field') keeps rows where the field is non-empty
        if (is_string($field) && func_num_args() === 1) {
            $this->warnIfMissing($field);
            $matches = [];
            foreach ($this->toArray() as $key => $row) {
                if (is_array($row) && !empty($row[$field])) {
                    $matches[$key] = $row;
                }
            }

            return new static($matches, $this->getInternalProperties());
        }

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
        foreach ($conditions as $key => $listValue) {
            if (is_int($key)) { // a list like where(['featured']) has no field names to match on
                $hint = is_string($listValue) ? " Did you mean ->where('$listValue') to match rows where '$listValue' is non-empty?" : "";
                throw new InvalidArgumentException("where(): the array form takes ['field' => value] pairs, list given.$hint");
            }
        }
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
     * With just a field name, keeps rows where that field is empty
     * (PHP empty() rule: NULL, false, 0, "0", "", and missing fields are empty).
     *
     *     $otherPages = $pages->whereNot('num', $currentPage->num);
     *     $published  = $articles->whereNot('status', 'draft');
     *     $unfeatured = $products->whereNot('featured');
     *
     * @param string $field Field name to compare
     * @param mixed  $value Value to exclude
     * @return static A new SmartArray excluding matching elements
     */
    public function whereNot(string $field, mixed $value = null): static
    {
        $this->assertNestedArray();
        $this->warnIfMissing($field);

        // Single-argument syntax: whereNot('field') keeps rows where the field is empty
        if (func_num_args() === 1) {
            $matches = [];
            foreach ($this->toArray() as $key => $row) {
                if (is_array($row) && empty($row[$field])) {
                    $matches[$key] = $row;
                }
            }

            return new static($matches, $this->getInternalProperties());
        }

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
        $value = self::getRawValue($value);
        if (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException("whereInList(): expected a single value to match, got " . get_debug_type($value));
        }
        $value   = (string) $value;
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
     * Nested SmartArrays convert to arrays; scalars and nulls return as-is.
     * No other type can appear.
     *
     * @return array An array representation of the object's elements with original values.
     */
    public function toArray(): array
    {
        // fromDatabaseRows() result sets hand back their original rows: O(1), and
        // any write to the set or a row clears the copy, so it's never stale
        if ($this->sourceRows !== null) {
            return $this->sourceRows;
        }

        // Flat arrays (no child rows) hand back internal data as-is: PHP arrays are
        // copy-on-write, so this is O(1) and callers can't affect internal storage
        if (!$this->hasRows) {
            return $this->data;
        }

        // Future options: We could add a default arg $smartStringsToValues = true to allow SmartStrings to be returned as objects
        $array = [];
        foreach ($this->data as $key => $value) {  // $this->data so getIterator doesn't convert to SmartStrings
            $array[$key] = $value instanceof self ? $value->toArray() : $value;
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
     * Rows with a null or missing field value index under '' (PHP's array-key
     * form of null). Duplicate values: last row wins.
     *
     * Float values keep full precision by keying as strings ('19.99'). Integers
     * and integer-like strings key as ints (PHP array-key rules), booleans as
     * 1/0.
     *
     *     $users = new SmartArray([
     *         ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'city' => 'New York'],
     *         ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'city' => 'New York'],
     *         ['id' => 3, 'name' => 'Mike', 'email' => 'mike@example.com', 'city' => 'Vancouver'],
     *     ]);
     *
     *     // Single row per key, no duplicates
     *     $emailToUser = $users->indexBy('email'); // Result:
     *     [
     *         'john@example.com' => ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'city' => 'New York'],
     *         'jane@example.com' => ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'city' => 'New York'],
     *         'mike@example.com' => ['id' => 3, 'name' => 'Mike', 'email' => 'mike@example.com', 'city' => 'Vancouver'],
     *     ]
     *
     *     // Single row per key, duplicates overwrite
     *     $emailToUser = $users->indexBy('city'); // Result:
     *     [
     *         'New York'  => ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'city' => 'New York'],
     *         'Vancouver' => ['id' => 3, 'name' => 'Mike', 'email' => 'mike@example.com', 'city' => 'Vancouver']
     *     ]
     *
     * @param string $field The field name to index the rows by.
     * @return static A new SmartArray indexed by the specified field.
     * @throws InvalidArgumentException If the SmartArray is not nested.
     */
    public function indexBy(string $field): static
    {
        $this->assertNestedArray();
        $this->warnIfMissing($field);

        // Index by field; rows with a null or missing value index under '' (duplicates: last wins)
        $values = [];
        foreach ($this->toArray() as $row) {
            if (!is_array($row)) {
                continue; // scalar rows have no fields to index by
            }
            $key          = $row[$field] ?? '';
            $key          = is_bool($key) ? (int)$key : (string)$key; // string cast keeps float precision; ints re-key as ints, bools as 1/0
            $values[$key] = $row;
        }

        return new static($values, $this->getInternalProperties());
    }

    /**
     * Creates a new SmartArray grouped by the specified field.
     *
     * This method transforms the current SmartArray (assumed to be a nested array of rows)
     * into a new SmartArray where each element is grouped by the value of the specified field.
     *
     * Rows with a null or missing field value group under '' (PHP's array-key
     * form of null), like SQL GROUP BY keeps a NULL group. No rows are dropped.
     *
     * Float values keep full precision by keying as strings ('19.99'). Integers
     * and integer-like strings key as ints (PHP array-key rules), booleans as
     * 1/0.
     *
     *     $users = new SmartArray([
     *         ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'city' => 'New York'],
     *         ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'city' => 'New York'],
     *         ['id' => 3, 'name' => 'Mike', 'email' => 'mike@example.com', 'city' => 'Vancouver'],
     *     ]);
     *
     *     // Multiple rows per key
     *     $cityToUsers = $users->groupBy('city'); // Result:
     *     [
     *         'New York' => [
     *             ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'city' => 'New York'],
     *             ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com', 'city' => 'New York'],
     *         ],
     *         'Vancouver' => [
     *             ['id' => 3, 'name' => 'Mike', 'email' => 'mike@example.com', 'city' => 'Vancouver'],
     *         ],
     *     ]
     *
     * @param string $field The field name to group the rows by.
     * @return static A new SmartArray grouped by the specified field.
     * @throws InvalidArgumentException If the SmartArray is not nested.
     */
    public function groupBy(string $field): static
    {
        $this->assertNestedArray();
        $this->warnIfMissing($field);

        $values = [];
        foreach ($this->toArray() as $row) {
            if (!is_array($row)) {
                continue; // scalar rows have no fields to group by
            }
            $key            = $row[$field] ?? '';
            $key            = is_bool($key) ? (int)$key : (string)$key; // string cast keeps float precision; ints re-key as ints, bools as 1/0
            $values[$key][] = $row;
        }

        return new static($values, $this->getInternalProperties());
    }

    /**
     * Extracts the column at a specific position from each row, ignoring key names.
     * Particularly useful for MySQL results where key names are unpredictable, like SHOW TABLES.
     * Use column() for extraction by key; columnAt() is by position.
     *
     * MySQL `SHOW TABLES LIKE 'cms_%'` returns:
     *
     *     [
     *         ['Tables_in_yourDbName (cms_%)' => 'cms_accounts'],
     *         ['Tables_in_yourDbName (cms_%)' => 'cms_settings'],
     *         ['Tables_in_yourDbName (cms_%)' => 'cms_pages'],
     *     ]
     *
     *     $tables = $resultSet->columnAt(0);   // Position 0 (first column): Returns ["cms_accounts", "cms_settings", "cms_pages"]
     *
     * @param int $index Zero-based position (supports negative indices: -1=last)
     * @return static A new SmartArray containing the extracted values.
     */
    public function columnAt(int $index): static
    {
        $this->assertNestedArray();

        $values = [];
        foreach ($this->toArray() as $row) {
            if (!is_array($row)) {
                continue; // scalar rows have no columns to extract
            }
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
     * The whole-rows shape column(null, $indexKey) follows indexBy() key rules, not
     * array_column()'s: rows missing the index field key under '' (last wins) instead
     * of getting auto-numbered keys that look like real field values, and float
     * values keep full precision as string keys ('19.99') instead of truncating.
     *
     *     $users = new SmartArray([
     *         ['id' => 10, 'name' => 'John', 'email' => 'john@example.com'],
     *         ['id' => 20, 'name' => 'Jane', 'email' => 'jane@example.com'],
     *     ]);
     *     $users->column('name');       // ['John', 'Jane']
     *     $users->column('name', 'id'); // [10 => 'John', 20 => 'Jane']
     *     $users->column(null, 'id');   // whole rows keyed by id, same as ->indexBy('id')
     *     $users->column(null);         // whole rows renumbered 0..n, like array_column($rows, null)
     *
     * @param int|string|null $columnKey Column to extract (null = entire rows, keyed by $indexKey)
     * @param int|string|null $indexKey  Column to use as array keys
     * @return static
     */
    public function column(int|string|null $columnKey, int|string|null $indexKey = null): static
    {
        $this->assertNestedArray(); // assert here so the error names column(), not indexBy()

        if ($columnKey === null && $indexKey !== null) {
            return $this->indexBy((string)$indexKey);
        }


        if ($columnKey !== null) {
            $this->warnIfMissing($columnKey);
        }

        $values = array_column($this->toArray(), $columnKey, $indexKey);
        return new static($values, $this->getInternalProperties());
    }

    /**
     * Joins the elements of the SmartArray into a single string with a specified separator.
     *
     * This method works on flat SmartArrays only.
     *
     *     $arr = SmartArray::new(['apple', 'banana', 'cherry']);
     *     $result = $arr->implode(', '); // Returns string: "apple, banana, cherry"
     *
     *     $arr = SmartArrayHtml::new(['apple', 'banana', 'cherry']);
     *     $result = $arr->implode(', '); // Returns SmartString: "apple, banana, cherry"
     *
     * @param string $separator The string to use as a separator between elements.
     * @return SmartString|string Returns string for SmartArray, SmartString for SmartArrayHtml.
     * @throws InvalidArgumentException If the SmartArray is nested.
     */
    public function implode(string $separator = ''): SmartString|string
    {
        $this->assertFlatArray();

        $values = array_map('strval', $this->toArray());
        $value  = implode($separator, $values);

        return $this->useSmartStrings ? new SmartString($value) : $value;
    }

    /**
     * Applies a callback to each element *as raw PHP values* (i.e., unwrapped scalars/arrays)
     * and returns a new SmartArray with the results. Preserves array keys.
     *
     * Callback arguments: Closures receive ($value, $key) unless they wrap a PHP
     * built-in; every other form receives just ($value). Built-ins get one argument
     * because extra arguments break them: strtoupper() throws ArgumentCountError,
     * intval() would read $key as its $base parameter.
     *
     * Built-ins that require a string throw TypeError on null or numeric elements;
     * chain ->map('strval') first to convert.
     *
     *     $rows->map(strtoupper(...));           // ($value) only - internal function
     *     $rows->map('strtoupper');              // ($value) only - internal function
     *     $rows->map(fn($v) => ucfirst($v));     // ($value, $key) - unused args are ignored
     *     $rows->map(fn($v, $k) => "$k: $v");    // ($value, $key)
     *     $rows->map(slugify(...));              // ($value, $key) - user code ignores extras
     *
     *     $arr   = new SmartArray(['apple', 'banana', 'cherry']);
     *     $upper = $arr->map(fn(string $fruit) => strtoupper($fruit));
     *     // $upper is now a SmartArray: ['APPLE', 'BANANA', 'CHERRY']
     *
     *     $nested = new SmartArray([['a' => 1], ['a' => 2]]);
     *     $values = $nested->map(fn(array $item) => $item['a']);
     *     // $values is now a SmartArray: [1, 2]
     *
     * @param callable $callback A function/callable to transform each element.
     * @return static A new SmartArray containing the transformed elements.
     */
    public function map(callable $callback): static
    {
        // Pass ($value, $key) only to Closures that don't wrap built-ins: built-ins throw on
        // extra args (strtoupper) or misread $key as a real parameter (intval's $base)
        $passKey = $callback instanceof Closure && !(new ReflectionFunction($callback))->isInternal();

        $newArray = [];
        foreach ($this->toArray() as $key => $rawValue) {
            $newArray[$key] = $passKey ? $callback($rawValue, $key) : $callback($rawValue);
        }

        return new static($newArray, $this->getInternalProperties());
    }

    /**
     * Merges the SmartArray with one or more arrays or SmartArrays.
     * Numeric keys are renumbered, string keys are overwritten by later values.
     *
     *     $arr1 = SmartArray::new(['a' => 1, 'b' => 2]);
     *     $arr2 = ['b' => 3, 'c' => 4];
     *     $arr3 = SmartArray::new(['d' => 5]);
     *
     *     $result = $arr1->merge($arr2, $arr3);
     *     // ['a' => 1, 'b' => 3, 'c' => 4, 'd' => 5]
     *
     * @param array|SmartArrayBase|SmartNull ...$arrays Arrays to merge with (SmartNull merges as empty)
     * @return static Returns a new SmartArray with the merged results
     */
    public function merge(array|SmartArrayBase|SmartNull ...$arrays): static
    {
        // Convert SmartArrays to arrays; SmartNull (missing key) merges as empty
        $arrays = array_map(static fn($array) => self::getRawValue($array) ?? [], $arrays);
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
     * @throws RuntimeException If no load handler is set or called on a record set.
     * @throws InvalidArgumentException If the field name is empty or invalid.
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
            !$loadHandler                        => throw new RuntimeException("load(): no load handler is set. Handlers are normally provided by the database layer (ZenDB); arrays created directly don't have one."),
            !is_callable($loadHandler)           => throw new RuntimeException("Load handler is not callable"),
            $field === ''                        => throw new InvalidArgumentException("Field name is required for load() method."),
            (bool)preg_match('/[^\w-]/', $field) => throw new InvalidArgumentException("Field name contains invalid characters: $field"),
            $this->isNested()                    => throw new RuntimeException("Cannot call load() on record set, only on a single row."),
            default                              => null,
        };

        // get handler output
        $result = $loadHandler($this, $field);
        if ($result === false) {
            throw new Error("Load handler doesn't support field '$field'\n" . self::occurredInFile());
        }

        // output error checking
        if (!is_array($result) || count($result) !== 2) {
            throw new Error("Load handler must return [rows, mysqliProperties] or false, got " . get_debug_type($result));
        }
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
     * Return the root SmartArray object for nested arrays, or the current object if not nested.
     *
     * Exists for load handlers, which use it to batch-cache values across sibling
     * rows (e.g., $row->root()->column($field) to collect every row's foreign keys
     * in one query). Not needed in templates.
     *
     * Mode conversions never change the root: asRaw() and asHtml() build a new
     * object and modify nothing else, so root() always returns the result set in
     * the class it was created as. Convert an HTML row to raw and root() is still
     * the SmartArrayHtml result set it came from.
     *
     * @internal
     */
    public function root(): self
    {
        return $this->root;
    }

    //endregion
    //region Debugging and Help

    /**
     * Displays diagnostic output: array contents, mysqli metadata, and object properties.
     *
     * If a load handler is set, calls it for every key to annotate loadable
     * relations - with a database-backed handler this can run one query per key.
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
            $rootShort          = self::stripNamespace(get_debug_type($properties['root']));
            $properties['root'] = get_debug_type($properties['root']) . " #" . spl_object_id($properties['root']);
            $propertiesOutput   = self::prettyPrintR($properties, $debugLevel, 0, "Object Properties");
            $propertiesOutput   = preg_replace("/^(\s+'root'\s+=> ).*?(\d+).*?$/m", "$1$rootShort #$2", $propertiesOutput); // format root property as: SmartArrayHtml #123
            $output             .= $propertiesOutput; // regex runs on the properties block only so a data row keyed 'root' prints untouched
        }

        $output .= "\n";
        echo self::xmpWrap($output);
    }

    private static function prettyPrintR(mixed $var, int $debugLevel = 0, int $depth = 0, string $keyPrefix = '', string $loadComment = ""): array|string|null
    {
        $indent        = $depth ? '    ' : '';
        $commentOffset = $debugLevel > 0 ? 81 - (strlen($indent) * $depth) : 0;

        // get var type
        $debugType = self::stripNamespace(get_debug_type($var));
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

                // add load comment for keys the handler resolves; without a handler (or for
                // int keys, which load() doesn't take) there is nothing to probe
                $loadComment = "";
                if ($var instanceof self && $var->loadHandler && is_string($key)) {
                    $loadResult = false;
                    try {
                        $loadResult = $var->load($key);
                    } catch (Throwable) {
                        // ignore errors
                    }
                    if ($loadResult !== false && !$loadResult instanceof SmartNull) {
                        $loadComment = " // ->load('$key') for more";
                    }
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
        } else {
            // Anything else prints as its type, e.g. the loadHandler Closure in the
            // debug(1) properties block - debug output describes, it never throws
            $output = str_pad("$keyPrefix$debugType,", $commentOffset) . "$comment\n";
        }

        // Indent each line
        return preg_replace("/^/m", $indent, $output);
    }

    /**
     * Shows just the element data in print_r() and var_dump() output, hiding internal
     * properties. The class name PHP prints on every dumped object identifies the mode
     * (SmartArray vs SmartArrayHtml); use ->debug() for exact types and metadata.
     * Comment out this method to see all internal properties while debugging.
     */
    public function __debugInfo(): array
    {
        return $this->data;
    }

    /**
     * Magic method for property access: $array->key
     *
     * This is the preferred way to access array elements.
     * For keys with special characters or numeric keys, use ->{'key'} instead.
     */
    public function __get(string $name): static|SmartNull|SmartString|string|int|float|bool|null
    {
        // Speed: this is the library's hottest path ($row->column in templates), so the
        // usual getElement() -> offsetExists() call chain is inlined here as one lookup.
        // - same behavior as getElement(), which all other accessors still use
        // - 6-54% faster across PHP 8.1-8.5 on all 5 platforms
        // - full results: .github/scripts/speed-results.md (test: arr-get)

        // Look up the value; ?? handles missing keys, array_key_exists catches stored nulls
        $value = $this->data[$name] ?? null;
        if ($value !== null || array_key_exists($name, $this->data)) {
            // Wrap in SmartString unless it's a nested SmartArray (the only object type setElement() stores)
            return $this->useSmartStrings && !is_object($value)
                ? new SmartString($value)
                : $value;
        }

        // Key doesn't exist: warn if this is a result-set row (see warnIfMissing)
        $this->warnIfMissing($name, isOffset: true);
        return $this->newSmartNull();
    }

    /**
     * Magic method for property assignment: $array->key = $value
     *
     * This is the preferred way to set array elements.
     * For keys with special characters or numeric keys, use ->{'key'} = $value instead.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setElement($name, $value);
    }

    /**
     * Magic method for isset($array->key), empty($array->key), and $array->key ?? $default.
     *
     * Stored nulls read as missing, matching plain PHP arrays, so ?? fallbacks fire on
     * them. Direct access still returns the stored null; use ->keys()->contains('key')
     * to ask whether the key itself exists.
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Magic method for unset($array->key)
     */
    public function __unset(string $name): void
    {
        $this->sourceRows       = null;   // same staleness rule as setElement()
        $this->root->sourceRows = null;
        unset($this->data[$name]);
    }

    //endregion
    //region Error Handling

    /**
     * Sends a 404 header and message if the array is empty, then exits with status 1
     * so shell scripts and cron jobs see the failure.
     *
     * @param string|null $text Plain-text message; HTML-encoded automatically before output. Defaults to "The requested URL was not found on this server."
     * @return static Returns $this if not empty, exits with 404 if empty
     */
    public function or404(?string $text = null): static
    {
        if (!empty($this->data)) {
            return $this;
        }

        // Send 404 header and message
        http_response_code(404);
        header("Content-Type: text/html; charset=utf-8");
        $text ??= "The requested URL was not found on this server.";
        $text = self::htmlEncode($text);

        echo <<<__HTML__
            <!DOCTYPE html>
            <html>
            <head>
                <title>Not Found</title>
            </head>
            <body>
                <h1>Not Found</h1>
                <p>$text</p>
            </body>
            </html>
            __HTML__;
        exit(1);
    }

    /**
     * Prints a message and exits with status 1 if the array is empty, so shell
     * scripts and cron jobs see the failure.
     *
     * SECURITY: The message is intentionally HTML-encoded: it goes straight to the browser, and
     * messages often interpolate user input (e.g. ->orDie("No results for '$keyword'")). The only cost
     * is encoded entities in CLI output, which is cosmetic.
     *
     * @param string $text Plain-text message; HTML-encoded automatically before output.
     * @return static Returns $this for method chaining if not empty, exits if empty
     */
    public function orDie(string $text): static
    {
        if (empty($this->data)) {
            echo self::htmlEncode($text); // SECURITY: intentional encode, do not remove (see docblock)
            exit(1);
        }
        return $this;
    }

    /**
     * Throws RuntimeException if the array is empty
     *
     * SECURITY: The message is intentionally HTML-encoded: exception handlers often echo messages into
     * a page, and messages often interpolate user input (e.g. ->orThrow("No results for '$keyword'")).
     * Encoding at throw time keeps every handler safe. Handlers that want plain text (CLI, logs) can
     * decode with:
     *
     *     htmlspecialchars_decode($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5)
     *
     * Pass those exact flags - the ENT_HTML401 default doesn't decode the &apos; this encoding produces.
     *
     * @param string $text Plain-text message; HTML-encoded automatically before output.
     * @return static Returns $this for method chaining if not empty
     * @throws RuntimeException If array is empty
     */
    public function orThrow(string $text): static
    {
        if (empty($this->data)) {
            $text = self::htmlEncode($text); // SECURITY: intentional encode, do not remove (see docblock)
            throw new RuntimeException($text);
        }
        return $this;
    }

    /**
     * Redirects to a URL if the array is empty
     *
     * Uses a simple Location header redirect (HTTP 302 Temporary Redirect).
     * If headers have already been sent, throws immediately - even when the
     * array is not empty - so a misplaced call fails on every request, not
     * just when a result happens to be empty.
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
     * Key access only warns on rows inside a parent collection: row keys are column
     * names, so a miss there is almost always a typo. Everywhere else (lookup maps
     * from indexBy()/column(), standalone arrays) keys are data, a miss is a normal
     * no-match, and the access renders blank silently.
     * Skipped for method-argument checks on mixed data (scalar config + array fields)
     * since there's no first row to check against.
     *
     * @param string|int $key      The key to check for
     * @param bool       $isOffset True for key access ($array->key), false for method args (where, sortBy, etc.)
     */
    private function warnIfMissing(string|int $key, bool $isOffset = false): void
    {
        // Key access: only rows inside a parent collection warn (position is 1-based
        // on rows, 0 on top-level and derived collections)
        if ($isOffset && $this->position === 0) {
            return;
        }

        // For property access (offset) - check this array's own keys.
        // For nested method args (where, sortBy, etc.) - check the first row's keys.
        $target = $this;
        if (!$isOffset) {
            $first = $this->first();
            if (!($first instanceof self)) {
                return; // Non-uniform data (e.g., schemas with scalar config + array fields)
            }
            $target = $first;
        }
        if (empty($target->data) || array_key_exists($key, $target->data)) {
            return;
        }
        $caller = self::getExternalCaller();

        // SECURITY: the key can be user input (e.g. ->{$_GET['sort']}) and the warning echoes
        // into the page, so encode it. The trigger_error() copy gets the same encoded key.
        $keyDisplay       = is_string($key) ? self::htmlEncode($key) : $key;
        $keyOrEmptyQuotes = $keyDisplay === "" ? "''" : $keyDisplay; // Show empty quotes for empty string keys

        $warning = $isOffset
            ? "$keyOrEmptyQuotes is undefined in {$caller['file']}:{$caller['line']}\n"
            : "{$caller['function']}(): '$keyDisplay' doesn't exist\n";

        // Catch if user tried to call a method in a double-quoted string without braces
        if (is_string($key) && method_exists($this, $key)) { // Catch cases such as "Nums: $users->pluck('num')->implode(',')->value();" which are missing braces
            $warning .= "\nIn double-quoted strings, use \"\$var->property\" for properties, but wrap methods in braces like \"{\$var->method()}\"\n";
        }
        if (!$isOffset) {
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
     * @return string Always an empty string.
     */
    public function __toString(): string
    {
        $caller       = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0];
        $inFileOnLine = sprintf("in %s on line %s", $caller['file'], $caller['line']);

        // output warning and trigger PHP warning (for logging)
        // PHP Error: Fatal error: Uncaught Error: Object of class Itools\SmartArray\SmartArray could not be converted to string in C:\path\file.php:27
        $className = self::stripNamespace(static::class);
        $warning   = "Can't convert $className to string $inFileOnLine.\n\n";
        $warning .= "In double-quoted strings, use \"\$var->property\" for properties, but wrap methods in braces like \"{\$var->method()}\"\n\n";
        $warning .= 'See SmartArray docs for more info';

        // output warning
        echo "\nWarning: $warning\n\n";           // Output with echo so PHP doesn't add the filename and line number of this function on the end
        @trigger_error($warning, E_USER_WARNING); // Trigger a PHP warning but hide output with @ so it will still get logged
        return "";
    }

    //endregion
    //region Internal Methods

    /**
     * Return a new SmartNull object with internal properties from the current SmartArray.
     *
     * `useSmartStrings` is added explicitly because `getInternalProperties()` omits it.
     * The omission is deliberate: SmartArray/SmartArrayHtml constructors throw if passed
     * a mismatched `useSmartStrings`, which is what makes cross-type construction
     * (`->asHtml()` / `->asRaw()`) safe. SmartNull doesn't enforce a type, so it needs
     * the flag to pick the right SmartArray class when `__call` delegates a method.
     * Without it, a SmartNull born from a SmartArrayHtml would silently fall back to a
     * plain SmartArray, dropping the HTML-encoded return type.
     */
    protected function newSmartNull(): SmartNull
    {
        return new SmartNull([...$this->getInternalProperties(), 'useSmartStrings' => $this->useSmartStrings]);
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
     * Returns an iterator over the elements, wrapping scalars in SmartString when enabled.
     * Nested SmartArrays are yielded as-is (not wrapped).
     */
    public function getIterator(): Iterator
    {
        // ArrayIterator iterates 1.2-1.3x faster than the wrapping generator below,
        // and nothing needs wrapping in raw mode or when every value is a row
        if (!$this->useSmartStrings || $this->rowsOnly) {
            return new ArrayIterator($this->data);
        }
        return $this->wrappingIterator();
    }

    /**
     * Yields elements with scalars and nulls wrapped in SmartString (HTML mode only).
     */
    private function wrappingIterator(): Iterator
    {
        foreach ($this->data as $key => $value) {
            yield $key => $value instanceof self ? $value : new SmartString($value);
        }
    }

    /**
     * Returns serializable data for `json_encode()` via JsonSerializable.
     * Returns the raw internal array so nested SmartArrays serialize as plain arrays.
     *
     * Values are raw, not HTML-encoded, even for SmartArrayHtml: JSON is a data
     * format, and HTML encoding applies only when values are output as HTML.
     *
     * Substitutes malformed UTF-8 in keys and values with � (U+FFFD) so json_encode($smartArray)
     * returns valid JSON instead of false. Nested SmartArrays scrub themselves when json_encode()
     * descends into them.
     *
     * @return array The internal data array.
     */
    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this->data as $key => $value) {
            if (is_string($key) && preg_match('//u', $key) !== 1) { // isMalformed: ~5x faster than mb_check_encoding()
                $key = json_decode(json_encode($key, JSON_INVALID_UTF8_SUBSTITUTE)); // json_encode's own U+FFFD substitution
            }
            if (is_string($value) && preg_match('//u', $value) !== 1) {
                $value = json_decode(json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE));
            }
            $data[$key] = $value;
        }
        return $data;
    }

    /**
     * HTML-encode text for output in warnings, notices, and guard messages.
     * ENT_DISALLOWED substitutes code points HTML5 forbids (C1 controls, noncharacters)
     * with � so they can't hide in page source.
     *
     * Same flags as SmartString::HTML_ENCODE_FLAGS - keep in sync.
     */
    private static function htmlEncode(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');
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
     * Returns an array of internal properties for passing to nested SmartArrays or type conversions.
     * Does NOT include useSmartStrings since child classes force their own values.
     *
     * asRaw()/asHtml() pass withPosition: true - the result is the same row in a
     * different mode, not a new derived array, so it keeps its place in the result set.
     */
    protected function getInternalProperties(bool $withPosition = false): array
    {
        $properties = [
            'loadHandler' => $this->loadHandler,
            'mysqli'      => $this->mysqli,
            'root'        => $this->root,
        ];
        if ($withPosition) {
            $properties += [
                'position' => $this->position,
                'isFirst'  => $this->isFirst,
                'isLast'   => $this->isLast,
            ];
        }
        return $properties;
    }

    //endregion

}
