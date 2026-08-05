<?php
declare(strict_types=1);

namespace Itools\SmartArray;

use Closure;
use Error;
use InvalidArgumentException;
use RuntimeException;
use Itools\SmartString\SmartString;
use JetBrains\PhpStorm\Deprecated;

/**
 * Old and retired method names, phased out in stages.
 *
 * Each deprecation sits at one stage of a five-stage ladder and moves down it in
 * later releases, per method, weighed by real-world usage. The stage names say
 * what a caller experiences:
 *
 *     Silent  - works; PHPStorm strikethrough and one-click rename via #[Deprecated],
 *               static analyzers via @deprecated, no runtime signal
 *     Logged  - works; logs E_USER_DEPRECATED with the caller's file:line
 *               (only error handlers see it, PHP's default display is suppressed)
 *     Visible - works; prints a "Deprecated:" notice to output, plus the log entry
 *     Fatal   - stops; __call() throws an Error naming the replacement and the caller
 *     Removed - stops; ordinary unknown-method error, "did you mean" hint at best
 *
 * Silent, Logged, and Visible methods are real declared methods in their stage's
 * region below: IDE-visible, type-checked, called directly with no __call()
 * overhead. Fatal and Removed methods no longer exist as methods, so calls land
 * in __call(), which throws for both. Removed needs no code of its own: a
 * removed method is an undefined name like any other, at best keeping a
 * "did you mean" entry. Moving a method down the ladder is a cut-paste to the
 * next region plus updating its runtime line (Silent: none, Logged:
 * logDeprecation(), Fatal: a match arm in __call).
 *
 * The deprecated $array['key'] bracket syntax follows the same ladder via
 * SmartArrayBase::$onOffsetAccess: 'log', 'notify', and 'throw' correspond to
 * Logged, Visible, and Fatal. The default is 'notify', which is why the
 * offsetGet/offsetSet/offsetUnset/offsetExists methods are in the Visible region.
 */
trait Deprecations
{

    //region Global Settings

    /**
     * Controls how deprecated `$array['key']` offset access is surfaced.
     *
     * Offset access (`[]` syntax) is deprecated in favor of property access:
     * `$array->key` for reads, `$array->key = $value` for writes, and brace
     * syntax (`$array->{'users.id'}`) for keys property syntax can't type. This setting
     * controls how the library signals that deprecation at runtime. It covers
     * reads, writes, and unset(); existence checks (offsetExists) are signal-free
     * because PHP also calls offsetGet() for `??` and empty(), which carries the
     * one notice. Property forms (`$array->key`, `isset($array->key)`) are always
     * signal-free, and so are bracket reads on SmartNull: missing-data chains like
     * $row->missing['a']['b'] would signal once per level for one call site.
     *
     *     'log'    - trigger_error(E_USER_DEPRECATED) only. Silent unless surfaced
     *                by PHP's error handling. Use for legacy codebases mid-migration.
     *     'notify' - Echo a visible "Deprecated:" notice + trigger_error(). Default.
     *                Developer sees the signal immediately, independent of error-handler
     *                configuration. Mirrors the pattern used by warnIfMissing().
     *     'throw'  - Throw a RuntimeException. Halts execution on any offset access.
     *                Use for new installs to enforce migration.
     *
     *     SmartArrayBase::$onOffsetAccess = 'log';    // quiet for legacy installs
     *     SmartArrayBase::$onOffsetAccess = 'throw';  // strict mode
     */
    public static string $onOffsetAccess = 'notify';

    //endregion
    //region Silent Aliases

    /**
     * Prints links to the online documentation.
     *
     * @deprecated Read the docs on GitHub instead - same content, easier to read:
     *             https://github.com/interactivetools-com/SmartArray#readme
     */
    #[Deprecated(reason: 'retired - read the docs on GitHub instead')]
    public function help(): void
    {
        $docs = <<<'__TEXT__'
            SmartArray docs:  https://github.com/interactivetools-com/SmartArray#readme
            Method reference: https://github.com/interactivetools-com/SmartArray/blob/main/docs/method-reference.md
            __TEXT__;

        echo self::xmpWrap("\n$docs\n\n");
    }

    /**
     * Extracts a single field from each row, optionally keyed by another field.
     *
     * @deprecated Use ->column() - same arguments and behavior, matches PHP's array_column()
     */
    #[Deprecated(reason: 'renamed to column()', replacement: '%class%->column()')]
    public function pluck(string|int $valueField, ?string $keyField = null): static
    {
        $this->assertNestedArray(); // assert here so the error names pluck(), not column()
        return $this->column($valueField, $keyField);
    }

    /**
     * Get an element by its position in the array, ignoring keys.
     *
     * @deprecated Use ->at() - same behavior, new name
     */
    #[Deprecated(reason: 'renamed to at()', replacement: '%class%->at()')]
    public function nth(int $index): static|SmartNull|SmartString|string|int|float|bool|null
    {
        return $this->at($index);
    }

    /**
     * Extracts the column at a specific position from each row, ignoring key names.
     *
     * @deprecated Use ->columnAt() - same behavior, new name
     */
    #[Deprecated(reason: 'renamed to columnAt()', replacement: '%class%->columnAt()')]
    public function pluckNth(int $index): static
    {
        $this->assertNestedArray(); // assert here so the error names pluckNth(), not columnAt()
        return $this->columnAt($index);
    }

    /**
     * Calls the given callback on each element, primarily for side effects.
     * Returns $this for chaining.
     *
     * For SmartArrayHtml: callback receives SmartString values (or nested SmartArrayHtml).
     * For SmartArray: callback receives raw PHP values (or nested SmartArray).
     *
     * @deprecated Use a foreach loop - same behavior, plain PHP:
     *
     *     // old
     *     $users->each(fn($user) => sendReminder($user));
     *
     *     // new
     *     foreach ($users as $user) {
     *         sendReminder($user);
     *     }
     *
     * @param Closure $callback A callback: fn($value, int|string $key): void
     * @return $this
     */
    #[Deprecated(reason: 'retired - use a foreach loop instead')]
    public function each(Closure $callback): static
    {
        foreach (array_keys($this->data) as $key) {
            $smartValue = $this->getElement($key);
            $callback($smartValue, $key);
        }

        return $this;
    }

    /**
     * Applies sprintf formatting to each element, with {value} and {key} aliases
     * for %1$s and %2$s. SmartArrayHtml HTML-encodes values and keys before
     * formatting; SmartArray does not. Always returns SmartArray (raw) so the
     * pre-formatted strings aren't re-encoded by later operations.
     *
     * @deprecated Use ->map() with an inline format string:
     *
     *     // SmartArray (raw, no encoding), old and new:
     *     $list->sprintf('<li>{value}</li>')->implode("\n");
     *     $list->map(fn($v) => "<li>$v</li>")->implode("\n");
     *
     *     // SmartArrayHtml (HTML-encoded), old and new. asRaw() matters here:
     *     // without it, implode() returns a SmartString that would re-encode
     *     // the HTML tags at output.
     *     $row->sprintf('<td>{value}</td>')->implode("\n");
     *     $row->asRaw()->map(fn($v) => "<td>" . htmlspecialchars((string)$v) . "</td>")->implode("\n"); // or h() in CMS Builder
     *
     * @param string $format sprintf format string (supports {value}/{key} aliases)
     * @return SmartArray Pre-formatted strings that won't be re-encoded on output
     * @throws InvalidArgumentException If called on a nested array
     */
    #[Deprecated(reason: 'retired - use ->map() with an inline format string')]
    public function sprintf(string $format): SmartArray
    {
        $this->assertFlatArray();

        // Convert {value} and {key} aliases to sprintf positional format
        $format = str_replace(['{value}', '{key}'], ['%1$s', '%2$s'], $format);

        $newArray = [];
        foreach ($this as $key => $value) {
            $value      = $value instanceof SmartString ? $value->htmlEncode() : $value;
            $encodedKey = $this->useSmartStrings ? self::htmlEncode((string)$key) : $key;
            $newArray[$key] = sprintf($format, $value, $encodedKey);
        }

        // Return SmartArray (raw) - sprintf output is pre-formatted and shouldn't be re-encoded
        $properties = ['useSmartStrings' => false] + $this->getInternalProperties();
        return new SmartArray($newArray, $properties);
    }

    /**
     * Returns the element at $key, same as $array->key, with an optional
     * default for missing keys.
     *
     * The default replaces missing keys only, never stored nulls. Smart keys
     * and defaults unwrap first; defaults then wrap for this array's mode the
     * same as a stored value would (arrays become same-mode SmartArrays).
     *
     * get('') is the only way to read an empty-string key: ->{''} is a PHP
     * fatal error.
     *
     * @deprecated Use property access: ->key, or ->{'users.id'} for keys property syntax
     *             can't type. For a missing-key default use ->key ?? $default.
     *
     * @param int|string|SmartString|SmartNull $key The key to retrieve; Smart values unwrap first
     * @param mixed $default Returned when $key doesn't exist; treated like a stored value
     * @return static|SmartNull|SmartString|string|int|float|bool|null
     */
    #[Deprecated(reason: "use property access ->key or ->{'key'}, with ?? for defaults")]
    public function get(int|string|SmartString|SmartNull $key, mixed $default = null): static|SmartNull|SmartString|string|int|float|bool|null
    {
        // Unwrap Smart keys, then coerce like PHP array keys: null reads key '', bool/float truncate to int
        if ($key instanceof SmartString || $key instanceof SmartNull) {
            $key = $key->value();
            $key = match (true) {
                is_int($key), is_string($key) => $key,
                is_null($key)                 => '',
                default                       => (int) $key,
            };
        }

        // return default if key not found
        if (func_num_args() >= 2 && !array_key_exists($key, $this->data)) {
            // Defaults act like stored values: Smart defaults (SmartString,
            // SmartArray, SmartNull) unwrap to raw equivalents, then everything
            // wraps for this array's mode the same as a stored value would
            if ($default instanceof SmartBase || $default instanceof SmartString) {
                $default = self::getRawValue($default);
            }
            return match (true) {
                is_scalar($default), is_null($default) => $this->useSmartStrings ? new SmartString($default) : $default,
                is_array($default)                     => new static($default, $this->getInternalProperties()),
                default                                => throw new InvalidArgumentException("Unsupported default value type: " . get_debug_type($default)),
            };
        }

        // skip if empty
        if (empty($this->data)) {
            return $this->newSmartNull();
        }

        // Return via getElement (no deprecation warning - Silent stage)
        if (array_key_exists($key, $this->data)) {
            return $this->getElement($key);
        }

        // Show warning if key doesn't exist (only when no default provided)
        $this->warnIfMissing($key, isOffset: true);

        return $this->newSmartNull();
    }

    /**
     * Sets a value by key, same as $array->key = $value. Returns $this for
     * chaining.
     *
     * Smart values are unwrapped on storage: a SmartString stores its raw
     * value, a SmartArray stores as a child array of this array's mode, and
     * a SmartNull stores as null.
     *
     * set('') is the only way to write an empty-string key: ->{''} = $value
     * is a PHP fatal error.
     *
     * @deprecated Use property assignment: ->key = $value, or ->{'users.id'} = $value for keys property syntax can't type
     *
     * @param int|string $key The key to set
     * @param mixed $value The value to set
     * @return static Returns $this for method chaining
     */
    #[Deprecated(reason: "use property assignment ->key = \$value or ->{'key'} = \$value")]
    public function set(int|string $key, mixed $value): static
    {
        $this->setElement($key, $value);
        return $this;
    }

    //endregion
    //region Logged Aliases

    /**
     * @deprecated Use asRaw() - same behavior, new name
     */
    #[Deprecated(reason: 'renamed to asRaw()', replacement: '%class%->asRaw()')]
    public function toRaw(): SmartArray
    {
        self::logDeprecation("Replace ->toRaw() with ->asRaw()");
        return $this->asRaw();
    }

    /**
     * @deprecated Use asHtml() - same behavior, new name
     */
    #[Deprecated(reason: 'renamed to asHtml()', replacement: '%class%->asHtml()')]
    public function toHtml(): SmartArrayHtml
    {
        self::logDeprecation("Replace ->toHtml() with ->asHtml()");
        return $this->asHtml();
    }

    /**
     * @deprecated Use asHtml() or create with SmartArrayHtml::new()
     */
    #[Deprecated(reason: 'renamed to asHtml()', replacement: '%class%->asHtml()')]
    public function withSmartStrings(): SmartArrayHtml
    {
        self::logDeprecation("Replace ->withSmartStrings() with ->asHtml() or use SmartArrayHtml::new()");
        return $this->asHtml();
    }

    /**
     * @deprecated Use asHtml() or create with SmartArrayHtml::new()
     */
    #[Deprecated(reason: 'renamed to asHtml()', replacement: '%class%->asHtml()')]
    public function enableSmartStrings(): SmartArrayHtml
    {
        self::logDeprecation("Replace ->enableSmartStrings() with ->asHtml() or use SmartArrayHtml::new()");
        return $this->asHtml();
    }

    /**
     * @deprecated Use asRaw() or create with SmartArray::new()
     */
    #[Deprecated(reason: 'renamed to asRaw()', replacement: '%class%->asRaw()')]
    public function noSmartStrings(): SmartArray
    {
        self::logDeprecation("Replace ->noSmartStrings() with ->asRaw() or use SmartArray::new()");
        return $this->asRaw();
    }

    /**
     * @deprecated Use asRaw() or create with SmartArray::new()
     */
    #[Deprecated(reason: 'renamed to asRaw()', replacement: '%class%->asRaw()')]
    public function disableSmartStrings(): SmartArray
    {
        self::logDeprecation("Replace ->disableSmartStrings() with ->asRaw() or use SmartArray::new()");
        return $this->asRaw();
    }

    /**
     * True when position() is a multiple of $value, e.g. every 3rd row.
     *
     * @deprecated Retired - use ->position() % $value === 0
     */
    #[Deprecated(reason: 'retired - use ->position() % $value === 0')]
    public function isMultipleOf(int $value): bool
    {
        self::logDeprecation("->isMultipleOf() is deprecated and will be removed in a future version");
        if ($value <= 0) {
            throw new InvalidArgumentException("Value must be greater than 0.");
        }
        return $this->position() % $value === 0;
    }

    /**
     * Applies a callback to each element as Smart objects (SmartString or SmartArray).
     *
     * @deprecated Use ->map() instead, which receives raw PHP values
     * @param Closure $callback A closure with signature: fn($smartValue, $key) => mixed
     * @return static A new SmartArray containing the transformed elements.
     */
    #[Deprecated(reason: 'use ->map() instead, which receives raw PHP values')]
    public function smartMap(Closure $callback): static
    {
        self::logDeprecation("->smartMap() is deprecated, use ->map() instead");
        $newArray = [];
        foreach (array_keys($this->data) as $key) {
            $smartValue     = $this->getElement($key);
            $newArray[$key] = $callback($smartValue, $key);
        }
        return new static($newArray, $this->getInternalProperties());
    }

    /**
     * Splits the array into chunks of the given size.
     *
     * @deprecated Retired, will be removed in a future version
     * @param int $size The size of each chunk
     * @return static A new SmartArray of chunked arrays
     */
    #[Deprecated(reason: 'retired, will be removed in a future version')]
    public function chunk(int $size): static
    {
        self::logDeprecation("->chunk() is deprecated and will be removed in a future version");
        if ($size <= 0) {
            throw new InvalidArgumentException("Chunk size must be greater than 0.");
        }
        return new static(array_chunk($this->toArray(), $size), $this->getInternalProperties());
    }

    //endregion
    //region Visible Notices

    // $array['key'] syntax: stage is configurable via SmartArrayBase::$onOffsetAccess ('notify' default)

    /**
     * Sets a value in the SmartArray using array syntax.
     *
     * Note: If you add a key after the array is created the position properties will not be updated.
     * If needed you can recreate the array like this: $newArray = SmartArray::new($oldArray->toArray());
     *
     * @deprecated Use ->key = $value or ->{'key'} = $value instead of $array['key'] = $value
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
     * @deprecated Use ->property or ->{'key'} instead of $array['key']
     */
    public function offsetGet(mixed $offset): static|SmartNull|SmartString|string|int|float|bool|null
    {
        $offset ??= ''; // PHP array semantics: $arr[null] reads key ''
        $this->triggerArrayAccessDeprecation($offset, 'get');
        return $this->getElement($offset);
    }

    /**
     * Check if a key exists, for isset($array['key']) and empty($array['key']).
     * Stored nulls read as missing, same as __isset() and plain PHP arrays.
     *
     * @deprecated Use isset($array->key) instead of isset($array['key'])
     */
    public function offsetExists(mixed $offset): bool
    {
        // No notice here: PHP calls offsetExists() then offsetGet() for `??` and empty(),
        // and offsetGet() already notifies, so one here would print every message twice.
        // A bare isset() with no read stays silent; any access that reads data notifies.
        return isset($this->data[$offset]);
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
     * Surface a deprecation notice for array access syntax, dispatched per $onOffsetAccess mode.
     *
     * @see SmartArrayBase::$onOffsetAccess
     */
    private function triggerArrayAccessDeprecation(mixed $key, string $operation = 'get'): void
    {
        // SECURITY: the key can be user input (e.g. $arr[$_GET['sort']]) and 'notify' mode echoes
        // the message into the page, so encode it. $key is display-only from here on; the actual
        // data access already happened with the original key.
        if (is_string($key)) {
            $key = self::htmlEncode($key);
        }
        $keyStr          = is_string($key) ? "'$key'" : (string) $key;
        $isValidPropName = is_string($key) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key);

        // Suggest the preferred access method. A null key only reaches 'set' via
        // the append syntax `$arr[] = $value`; for 'unset'/'get'/'exists' it indicates
        // a programmer error that PHP itself treats as an empty-string key.
        $suggestion = match ($operation) {
            'set' => match (true) {
                is_null($key)    => 'an explicit key: ->key = $value',
                is_int($key)     => '->{' . $key . '} = $value',
                $isValidPropName => "->$key = \$value",
                $key === ''      => "->set('', \$value)", // ->{''} = $value is a fatal "Cannot access empty property"
                default          => "->{'$key'} = \$value",
            },
            default => match (true) {
                is_int($key)                 => '->{' . $key . '}',
                $isValidPropName             => "->$key",
                $key === '' || $key === null => "->get('')", // PHP treats a null/'' key as key ''; ->{''} is a fatal "Cannot access empty property"
                default                      => "->{'$key'}",
            },
        };

        $caller  = self::getExternalCaller();
        $message = "Replace [$keyStr] with $suggestion in {$caller['file']}:{$caller['line']}.";

        // Dispatch per $onOffsetAccess mode. 'throw' and invalid values exit via exception;
        // 'notify' prints then falls through to trigger_error; 'log' is trigger_error only.
        match (SmartArrayBase::$onOffsetAccess) {
            'log'    => null,
            'notify' => print "\nDeprecated: $message\n",
            'throw'  => throw new RuntimeException($message),
            default  => throw new InvalidArgumentException(
                "Invalid SmartArrayBase::\$onOffsetAccess value: '" . SmartArrayBase::$onOffsetAccess . "'. Expected 'log', 'notify', or 'throw'.",
            ),
        };

        @trigger_error($message, E_USER_DEPRECATED);
    }

    //endregion
    //region Fatal & Undefined

    /**
     * Fatal-stage deprecations: the method no longer exists, so the call lands
     * here and throws with the exact replacement named. Names that were never
     * methods get an unknown-method error, with a "did you mean" hint when the
     * name is a removed method or a common alias from other libraries.
     *
     * @noinspection SpellCheckingInspection // lowercase method names in match arms
     */
    public function __call(string $method, array $args): mixed
    {
        $fatalError = match (strtolower($method)) {
            //   'oldname' => "Replace ->$method() with ->newName()",
            default => null,
        };
        if ($fatalError) {
            throw new Error("$fatalError\n" . self::occurredInFile());
        }

        // PHP Default Error: Fatal error: Uncaught Error: Call to undefined method class::method() in /path/file.php:123
        $suggestion = self::didYouMean($method) ?? "see the SmartArray docs for available methods.";
        $className  = self::stripNamespace(static::class);
        throw new Error("Call to undefined method $className->$method(), $suggestion\n" . self::occurredInFile());
    }

    /**
     * Static calls to undefined methods get the same styled error as instance calls.
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        // PHP Default Error: Fatal error: Uncaught Error: Call to undefined method class::method() in /path/file.php:123
        $className = self::stripNamespace(static::class);
        throw new Error("Call to undefined method $className::$method(), see the SmartArray docs for available methods.\n" . self::occurredInFile());
    }

    /**
     * Returns a "did you mean ->method()?" hint for removed methods and common
     * names from other libraries or LLM suggestions, or null if nothing matches.
     *
     * @noinspection SpellCheckingInspection // lowercase method names in alias lists
     */
    private static function didYouMean(string $method): ?string
    {
        $methodLc = strtolower($method);

        // Names whose replacement isn't a method: point at the current syntax, not a deprecated method
        $syntaxHint = match ($methodLc) {
            'fetch', 'value', 'item'     => "did you mean property access, ->key or ->{'key'}?",
            'foreach', 'iterate', 'walk' => "did you mean a foreach loop?",
            default                      => null,
        };
        if ($syntaxHint) {
            return $syntaxHint;
        }

        $methodAliases = [
            // value access
            'first'       => ['head', 'find', 'firstrow', 'getfirst'],
            'last'        => ['tail', 'end'],
            'at'          => ['index'],

            // emptiness & search
            'count'       => ['length', 'size'],
            'isEmpty'     => ['empty'],
            'isNotEmpty'  => ['any', 'not_empty', 'notempty', 'hasvalue', 'exists'],
            'contains'    => ['has', 'includes', 'in', 'some'],

            // sorting & filtering
            'sort'        => ['order', 'sorted'],
            'sortBy'      => ['orderby', 'sortbycolumn'],
            'unique'      => ['distinct', 'uniq', 'dedupe'],
            'filter'      => ['select', 'keep'],
            'where'       => ['filter_by', 'findwhere', 'match'],

            // array transforms
            'toArray'     => ['array', 'all', 'unwrap', 'raw'],
            'keys'        => ['keyset'],
            'values'      => ['vals', 'list', 'getvalues'],
            'indexBy'     => ['keyby'],
            'groupBy'     => ['group', 'categorize'],
            'column'      => ['extract', 'pick', 'getcolumn'],
            'columnAt'    => ['columnnth', 'nthcolumn'],
            'implode'     => ['concat', 'join'],
            'map'         => ['transform', 'apply', 'collect'],
            'merge'       => ['append', 'union', 'combine', 'extend'],

            // conversion
            'asHtml'      => ['encode', 'safe', 'escape'],
            'asRaw'       => ['decode'],

            // utilities
            'debug'       => ['dump', 'inspect', 'dd'],
        ];

        foreach ($methodAliases as $correctMethod => $aliases) {
            if (in_array($methodLc, $aliases, true)) {
                return "did you mean ->$correctMethod()?";
            }
        }
        return null;
    }

    //endregion

}
