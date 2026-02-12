<?php
declare(strict_types=1);

namespace Itools\SmartArray;

use Closure;
use Error;
use InvalidArgumentException;
use Itools\SmartString\SmartString;

/**
 * Handles deprecated methods, legacy array access, and unknown method errors.
 *
 * This trait contains backwards compatibility shims that will be removed in a future version.
 * It handles three categories:
 *  - Deprecated method aliases (log warning, return correct value)
 *  - Deprecated position methods (log warning, will be removed)
 *  - Unknown method errors (suggest correct method or show help)
 *  - Deprecated array bracket syntax (log warning, use property syntax instead)
 */
trait DeprecationsTrait
{

    //region Deprecated Position Properties

    // Calculated during construction, accessed via deprecated __call() methods
    protected bool $isFirst  = false;
    protected bool $isLast   = false;
    private int $position = 0;

    //endregion
    //region Deprecated Method Handling

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

        // Deprecated method aliases: log warning and return proper value
        [$return, $deprecationError] = match ($methodLc) {  // use lowercase names below for comparison
            'getcolumn'                              => [null, "Replace ->$method() with ->pluck() or another method"],
            'exists'                                 => [$this->isNotEmpty(), "Replace ->$method() with ->isNotEmpty()"],
            'firstrow', 'getfirst'                   => [$this->first(), "Replace ->$method() with ->first()"],
            'getvalues'                              => [$this->values(), "Replace ->$method() with ->values()"],
            'item'                                   => [$this->get(...$args), "Replace ->$method() with ->get()"],
            'join'                                   => [$this->implode(...$args), "Replace ->$method() with ->implode()"],
            'raw'                                    => [$this->toArray(), "Replace ->$method() with ->toArray()"],
            'toraw'                                  => [$this->asRaw(), "Replace ->$method() with ->asRaw()"],
            'tohtml'                                 => [$this->asHtml(), "Replace ->$method() with ->asHtml()"],
            'withsmartstrings', 'enablesmartstrings' => [$this->asHtml(), "Replace ->$method() with ->asHtml() or use SmartArrayHtml::new()"],
            'nosmartstrings', 'disablesmartstrings'  => [$this->asRaw(), "Replace ->$method() with ->asRaw() or use SmartArray::new()"],
            default                                  => [null, null],
        };
        if ($deprecationError) {
            self::logDeprecation($deprecationError);
            return $return;
        }

        // Deprecated position methods: log warning and return value
        if (in_array($methodLc, ['isfirst', 'islast', 'position', 'ismultipleof'], true)) {
            self::logDeprecation("->$method() is deprecated and will be removed in a future version");
            return match ($methodLc) {
                'isfirst'  => $this->isFirst,
                'islast'   => $this->isLast,
                'position' => $this->position,
                'ismultipleof' => (function () use ($args): bool {
                    $value = $args[0] ?? throw new InvalidArgumentException("isMultipleOf() requires a value argument.");
                    if ($value <= 0) {
                        throw new InvalidArgumentException("Value must be greater than 0.");
                    }
                    return $this->position % $value === 0;
                })(),
            };
        }
        // Common aliases: throw error with suggestion.  These are used by other libraries or common LLM suggestions
        $methodAliases = [
            // value access
            'get'         => ['fetch', 'value'],
            'first'       => ['head'],
            'last'        => ['tail'],
            'nth'         => ['index', 'at'],

            // emptiness & search
            'isEmpty'     => ['empty'],
            'isNotEmpty'  => ['any', 'not_empty'],
            'contains'    => ['has', 'includes'],

            // sorting & filtering
            'sort'        => ['order', 'orderby'],
            'unique'      => ['distinct', 'uniq'],
            'filter'      => ['select'],
            'where'       => ['filter_by'],

            // array transforms
            'toArray'     => ['array'],
            'keys'        => ['keyset'],
            'values'      => ['vals', 'list'],
            'indexBy'     => ['keyby'],
            'groupBy'     => ['group'],
            'pluckNth'    => ['columnnth'],
            'implode'     => ['concat'],
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
        $className   = substr(strrchr(static::class, '\\'), 1) ?: static::class;
        throw new Error("Call to undefined method $className->$method(), $suggestion\n" . self::occurredInFile());
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
            'newss'    => [new SmartArrayHtml($args[0] ?? []), "Replace ::$method(...) with SmartArrayHtml::new(...)"],
            default    => [null, null],
        };
        if ($deprecationError) {
            self::logDeprecation($deprecationError);
            return $return;
        }

        // throw unknown method exception
        // PHP Default Error: Fatal error: Uncaught Error: Call to undefined method class::method() in /path/file.php:123
        $className = substr(strrchr(static::class, '\\'), 1) ?: static::class;
        throw new Error("Call to undefined method $className::$method(), call ->help() for available methods.\n" . self::occurredInFile());
    }

    //endregion
    //region Deprecated Methods

    /**
     * Applies a callback to each element as Smart objects (SmartString or SmartArray).
     *
     * @deprecated Use ->map() instead, which receives raw PHP values
     * @param Closure $callback A closure with signature: fn($smartValue, $key) => mixed
     * @return self A new SmartArray containing the transformed elements.
     */
    public function smartMap(Closure $callback): self
    {
        self::logDeprecation("->smartMap() is deprecated, use ->map() instead");
        $newArray        = [];
        $useSmartStrings = $this->useSmartStrings;
        foreach (array_keys($this->getArrayCopy()) as $key) {
            $smartValue     = $this->getElement($key, $useSmartStrings);
            $newArray[$key] = $callback($smartValue, $key);
        }
        return new static($newArray, $this->getInternalProperties());
    }

    /**
     * Splits the array into chunks of the given size.
     *
     * @deprecated Will be removed in a future version
     * @param int $size The size of each chunk
     * @return static A new SmartArray of chunked arrays
     */
    public function chunk(int $size): static
    {
        self::logDeprecation("->chunk() is deprecated and will be removed in a future version");
        if ($size <= 0) {
            throw new InvalidArgumentException("Chunk size must be greater than 0.");
        }
        return new static(array_chunk($this->toArray(), $size), $this->getInternalProperties());
    }

    //endregion
    //region Deprecated Array Access

    /**
     * Sets a value in the SmartArray using array syntax.
     *
     * @deprecated Use ->set('key', $value) or ->key = $value instead of $array['key'] = $value
     *
     * Note: If you add a key after the array is created the position properties will not be updated.
     * If needed you can recreate the array like this: $newArray = SmartArray::new($oldArray->toArray());
     *
     * @param mixed $offset The key to set. If null, the value is appended to the array.
     * @param mixed $value The value to set. Will be converted to SmartString or SmartArray as appropriate.
     *
     * @throws InvalidArgumentException If an unsupported value type is provided.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->triggerArrayAccessDeprecation($offset, 'set');
        $this->setElement($offset, $value);
    }

    /**
     * Retrieves a value from the SmartArray using array syntax.
     *
     * @deprecated Use ->property or ->get('key') instead of $array['key']
     * @noinspection SpellCheckingInspection // ignore lowercase method names in match block
     */
    public function offsetGet(mixed $offset, ?bool $useSmartStrings = null): static|SmartNull|SmartString|string|int|float|bool|null
    {
        $this->triggerArrayAccessDeprecation($offset, 'get');
        return $this->getElement($offset, $useSmartStrings);
    }

    /**
     * Remove a key from the array.
     *
     * @deprecated Use transformation methods instead of modifying arrays in place
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->triggerArrayAccessDeprecation($offset, 'unset');
        unset($this->data[$offset]);
    }

    /**
     * Log a deprecation notice for array access syntax.
     */
    private function triggerArrayAccessDeprecation(mixed $key, string $operation = 'get'): void
    {
        $keyStr          = is_string($key) ? "'$key'" : (string) $key;
        $isValidPropName = is_string($key) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key);

        // Suggest the preferred access method
        $suggestion = match ($operation) {
            'set' => match (true) {
                is_int($key)     => "->set($key, \$value)",
                $isValidPropName => "->$key = \$value",
                default          => "->set('$key', \$value) or ->{'$key'} = \$value",
            },
            'unset' => match (true) {
                is_int($key)     => '->{' . $key . '}',
                $isValidPropName => "->$key",
                default          => "->{'$key'}",
            },
            default => match (true) {
                is_int($key)     => "->get($key)",
                $isValidPropName => "->$key",
                default          => "->get('$key')",
            },
        };

        self::logDeprecation("Replace [$keyStr] with $suggestion");
    }

    //endregion

}
