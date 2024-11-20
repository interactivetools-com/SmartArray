<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException, JsonException;
use ArrayObject;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartNull;
use Itools\SmartString\SmartString;

use PHPUnit\Framework\TestCase;

class SmartArrayTest extends TestCase
{
    #region internal test functions

    public function getTestRecords(): array
    {
        return [
            [
                'html'    => "<img src='' alt='\"'>",
                'int'     => 7,
                'float'   => 5.7,
                'string'  => '&nbsp;',
                'bool'    => true,
                'null'    => null,
                'isFirst' => 'C',  // intentionally named after internal private property to detect any conflicts
            ],
            [
                'html'    => '<p>"It\'s"</p>',
                'int'     => 0,
                'float'   => 1.23,
                'string'  => '"green"',
                'bool'    => false,
                'null'    => null,
                'isFirst' => 'Q',
            ],
            [
                'html'    => "<hr class='line'>",
                'int'     => 1,
                'float'   => -16.7,
                'string'  => '<blue>',
                'bool'    => false,
                'null'    => null,
                'isFirst' => 'K',
            ],
        ];
    }

    public function getTestRecord(): array
    {
        return $this->getTestRecords()[1];
    }

    /**
     * Check the internal object structure checking for incorrect values or types
     */
    private function verifyObjectStructure(SmartArray $obj, string $path = '$obj', ?SmartArray $actualRoot = null): void
    {
        $weAreRoot = $path === '$obj'; // e.g., depth is 0, not path set
        $arrayCopy = $obj->getArrayCopy();

        // Check for allowed types: SmartArray, scalar, or null
        foreach ($arrayCopy as $element) {
            if (!$element instanceof SmartArray && !is_scalar($element) && !is_null($element)) {
                throw new InvalidArgumentException("Invalid type at $path : " . get_debug_type($obj) . ". Must be SmartArray, scalar, or null.");
            }
        }

        // ArrayObject flags are correct
        $flags = $obj->getFlags();
        $flagsText = match ($flags) {
            0                                                        => 'NONE',
            ArrayObject::STD_PROP_LIST                               => 'STD_PROP_LIST',
            ArrayObject::ARRAY_AS_PROPS                              => 'ARRAY_AS_PROPS',
            ArrayObject::STD_PROP_LIST | ArrayObject::ARRAY_AS_PROPS => 'STD_PROP_LIST|ARRAY_AS_PROPS',
            default                                                  => "Unknown flags: $flags",
        };
        if ($flagsText !== 'ARRAY_AS_PROPS') {
            throw new InvalidArgumentException("Invalid flags at $path. Expected ARRAY_AS_PROPS, got $flagsText instead.");
        }

        // Check position properties
        $position = 0;
        $firstKey = array_key_first($arrayCopy);
        $lastKey  = array_key_last($arrayCopy);
        foreach ($arrayCopy as $key => $el) {
            $position++;

            // skip non-SmartArray elements
            if (!$el instanceof SmartArray) {
                continue;
            }

            $isFirst = $key === $firstKey;
            $isLast  = $key === $lastKey;
            $error   = match (true) {
                $el->position() !== $position => "position: expected $position, got {$el->position()}",
                $el->isFirst() !== $isFirst   => "isFirst: expected " . var_export($isFirst, true) . ", got " . var_export($el->isFirst(), true) . " instead.",
                $el->isLast() !== $isLast     => "isFirst: expected " . var_export($isLast, true) . ", got " . var_export($el->isLast(), true) . " instead.",
                default                       => null,
            };
            if ($error) {
                throw new InvalidArgumentException("$path->$key property $error");
            }
        }

        // Check for correct root property
        $rootProperty = $obj->root();
        if ($weAreRoot && $rootProperty !== $obj) {
            throw new InvalidArgumentException("\$rootArray->root() should reference self, got " .get_debug_type($rootProperty). " instead.");
        }

        if (!$weAreRoot && $rootProperty !== $actualRoot) { // child
            $expected = basename(get_debug_type($actualRoot)) . " #" . spl_object_id($actualRoot);
            $actual   = !is_object($rootProperty) ? $rootProperty : basename(get_debug_type($rootProperty)) . " #" . spl_object_id($rootProperty);
            throw new InvalidArgumentException("Invalid property at $path->"."root(). Expected $expected, got $actual instead.");
        }

        // Check metadata
        if (!$weAreRoot && (array) $obj->metadata() !== (array) $actualRoot->metadata()) {
            throw new InvalidArgumentException("Invalid metadata at $path. Expected " . var_export($actualRoot->metadata(), true) . ", got " . var_export($obj->metadata(), true) . " instead.");
        }

        // Check useSmartArrays
        if (!$weAreRoot && $obj->getProperty('useSmartStrings') !== $actualRoot->getProperty('useSmartStrings')) {
            throw new InvalidArgumentException("Invalid useSmartStrings at $path. Expected " . var_export($actualRoot->getProperty('useSmartStrings'), true) . ", got " . var_export($obj->getProperty('useSmartStrings'), true) . " instead.");
        }

        // Recurse over child SmartArrays
        $actualRoot ??= $obj;
        foreach ($arrayCopy as $key => $element) {
            if ($element instanceof SmartArray) {
                $this->verifyObjectStructure($element, "$path->$key", $actualRoot);
            }
        }
    }

    /**
     * Like $smartArray->toArray() uses foreach (getIterator()) to convert all scalars and nulls to SmartStrings
     * Then converts strings to their html encoded value.
     *
     * We use this to compare expected output with what SmartArray::newSS() should return.
     */
    public static function toArrayResolveSS(mixed $obj): array
    {
        // Use getIterator to convert everything to SmartStrings
        $array = [];
        foreach ($obj->getIterator() as $key => $value) {  // getIterator|foreach converts everything to SmartStrings when ::newSS() is used
            $array[$key] = self::normalizeSS($value);
        }

        return $array;

    }

    /**
     * Html encode all strings in an array structure
     * We use this to encode our test data to match what SmartArray::newSS() should return
     */
    public static function recursiveHtmlEncode(mixed $var): mixed {

        // Error checking
        if (is_object($var)) {
            throw new InvalidArgumentException("Unexpected object type: " . get_debug_type($var));
        }

        // Recurse over nested arrays
        if (is_array($var)) {
            foreach ($var as $key => $value) {
                $var[$key] = self::recursiveHtmlEncode($value);
            }
            return $var;
        }

        // encode values
        if (is_string($var)) {
            $var = htmlspecialchars($var, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        return $var;
    }

    /**
     * Return raw value from any element returned by SmartArray::new() - includes scalar|null but not SmartStrings
     *
     * @param $var
     * @return array|bool|float|int|string|null
     */
    public static function normalizeRaw($var): float|array|bool|int|string|null
    {
        return match (true) {
            is_scalar($var), is_null($var) => $var,
            $var instanceof SmartArray     => $var->toArray(),
            $var instanceof SmartNull      => null,
            default                        => __FUNCTION__ . "() Unexpected value type: " . get_debug_type($var),
        };
    }

    /**
     * Return strings html encoded, and everything else as raw value from any element
     * returned by SmartArray::newSS() - includes SmartStrings but not scalar|null
     *
     * @param $var
     * @return array|bool|float|int|string|null
     */
    public static function normalizeSS($var): float|array|bool|int|string|null
    {
        $isSmartString       = $var instanceof SmartString;
        $isSmartStringString = $var instanceof SmartString && is_string($var->value());
        return match (true) {
            $isSmartStringString       => $var->__toString(),            // Call __toString() on SmartString strings
            $isSmartString             => $var->value(),                 // Just show other values
            $var instanceof SmartArray => self::toArrayResolveSS($var),
            $var instanceof SmartNull  => null,
            default                    => __FUNCTION__ . "() Unexpected value type: " . get_debug_type($var),
        };
    }

    #endregion
    #region new SmartArray()

    /**
     * @dataProvider constructorProvider
     */
    public function testConstructor($inputArray): void
    {
        $obj = new SmartArray($inputArray);
        $this->verifyObjectStructure($obj);
        $this->assertSame(
            expected: $inputArray,
            actual  : $obj->toArray(),
        );
    }

    /**
     * @dataProvider constructorProvider
     */
    public function testNew($inputArray): void
    {
        $obj = SmartArray::new($inputArray);
        $this->verifyObjectStructure($obj);
        $this->assertSame(
            expected: $inputArray,
            actual  : $obj->toArray(),
        );
    }

    /**
     * @dataProvider constructorProvider
     */
    public function testNewSS($inputArray): void
    {
        $obj = SmartArray::newSS($inputArray);
        $this->verifyObjectStructure($obj);
        $this->assertSame(
            expected: self::toArrayResolveSS($obj),
            actual  : self::recursiveHtmlEncode($obj->toArray()),
        );
    }

    public function constructorProvider(): array
    {
        return [
            'empty array'                => [[]],
            'flat array with primitives' => [[1, 'string', true, null, 3.14]],
            'array with HTML'            => [[
                'name' => 'John <b> Doe',
                'age'  => 30,
            ]],
            'nested array'               => [[
                'user'   => [
                    'name'  => 'Jane " Doe',
                    'email' => 'jane@example.com',
                ],
                'active' => true,
            ]],
            'deeply nested'              => [[
                'level1' => [
                    'level2' => [
                        'level3' => [
                            'value' => '<deep> value',
                        ],
                    ],
                ],
            ]],
            'mixed types'                => [[
                'number'      => 42,
                'string'      => 'hello',
                'smartString' => '"world"',
                'array'       => [1, 2, 3],
                'smartArray'  => [4, 5, 6],
            ],
            'using test records'         => $this->getTestRecords(),
        ]];
    }

#endregion
#region Value Access

    public function testGetDefaults(): void
    {
        $expected = "Bob";
        $default  = "Unknown Name";

        // Test defaults are ignored when key exists
        $smartArrays = [
            SmartArray::new(['name' => $expected, 'city' => 'Springfield']),
            SmartArray::newSS(['name' => $expected, 'city' => 'Springfield']),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value; // convert SmartString to string
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertSame($expected, $actual);
        }

        // Test defaults are ignored when key exists but is null
        $smartArrays = [
            SmartArray::new(['name' => null, 'city' => 'Springfield']),
            SmartArray::newSS(['name' => null, 'city' => 'Springfield']),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value; // convert SmartString to string
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertNull($actual);
        }

        // Test defaults are used when key doesn't exist
        $smartArrays = [
            SmartArray::new(['name2' => $expected, 'city' => 'Springfield']),
            SmartArray::newSS(['name2' => $expected, 'city' => 'Springfield']),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value; // convert SmartString to string
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertSame($default, $actual);
        }

        // Test defaults are used when array is empty
        $smartArrays = [
            SmartArray::new(),
            SmartArray::newSS(),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $value   = $smartArray->get('name', $default);
            $warning = ob_get_clean();
            $actual  = $value instanceof SmartString ? $value->value() : $value; // convert SmartString to string
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
            $this->assertSame($default, $actual);
        }

    }

    /**
     * @dataProvider getProvider
     */
    public function testGet($initialData, $key, $expected): void
    {
        // Set up the initial data
        $array   = SmartArray::new($initialData);
        $arraySS = SmartArray::newSS($initialData);

        // Perform the get operation
        ob_start(); // capture any warnings output by SmartArray::get()
        $result   = $array->get($key);
        $resultSS = $arraySS->get($key);
        ob_end_clean(); // discard any warnings output by SmartArray::get()

        // Compare Raw
        $this->assertSame(
            expected: $expected,
            actual  : self::normalizeRaw($result),
        );

        // Compare SS
        $this->assertSame(
            expected: self::recursiveHtmlEncode($expected),
            actual  : self::normalizeSS($resultSS)
        );
    }

    public function getProvider(): array
    {
        return [
            'Get existing key'                                => [
                'initialData' => ['name' => 'Alice', 'age' => 30],
                'key'         => 'name',
                'expected'    => 'Alice',
            ],
            'Get non-existing key'                            => [
                'initialData' => ['name' => 'Alice'],
                'key'         => 'email',
                'expected'    => null,
            ],
            'Get existing key with value null'                => [
                'initialData' => ['name' => 'Alice', 'nickname' => null],
                'key'         => 'nickname',
                'expected'    => null,
            ],
            'Get nested array value'                          => [
                'initialData' => ['user' => ['id' => 1, 'name' => 'Alice']],
                'key'         => 'user',
                'expected'    => ['id' => 1, 'name' => 'Alice'],
            ],
            'Get non-existing key from empty array'           => [
                'initialData' => [],
                'key'         => 'key',
                'expected'    => null,
            ],
            'Get existing key with boolean false'             => [
                'initialData' => ['isActive' => false],
                'key'         => 'isActive',
                'expected'    => false,
            ],
            'Get existing key with value zero'                => [
                'initialData' => ['count' => 0],
                'key'         => 'count',
                'expected'    => 0,
            ],
            'Get existing key with empty string'              => [
                'initialData' => ['title' => ''],
                'key'         => 'title',
                'expected'    => '',
            ],
            'Get existing key with integer key'               => [
                'initialData' => [0 => 'zero', 1 => 'one'],
                'key'         => 1,
                'expected'    => 'one',
            ],
            'Get non-existing key with integer key'           => [
                'initialData' => [0 => 'zero', 1 => 'one'],
                'key'         => 2,
                'expected'    => null,
            ],
            'Get existing key with numeric string key'        => [
                'initialData' => ['123' => 'numeric key'],
                'key'         => '123',
                'expected'    => 'numeric key',
            ],
            'Get existing key with special characters in key' => [
                'initialData' => ['key-with-dash' => 'special key'],
                'key'         => 'key-with-dash',
                'expected'    => 'special key',
            ],
            'Get existing key with empty string key'          => [
                'initialData' => ['' => 'empty key'],
                'key'         => '',
                'expected'    => 'empty key',
            ],
            'Get existing key where value is array'           => [
                'initialData' => ['data' => ['item1', 'item2']],
                'key'         => 'data',
                'expected'    => ['item1', 'item2'],
            ],
        ];
    }

    /**
     * @dataProvider firstProvider
     */
    public function testFirst($initialData, $expected): void
    {
        // Set up the initial data
        $array   = SmartArray::new($initialData);
        $arraySS = SmartArray::newSS($initialData);

        // Compare Raw
        $result   = $array->first();
        $this->assertSame(
            expected: $expected,
            actual  : self::normalizeRaw($result),
        );

        // Compare SS
        $resultSS = $arraySS->first();
        $this->assertSame(
            expected: self::recursiveHtmlEncode($expected),
            actual  : self::normalizeSS($resultSS)
        );
    }

    public function firstProvider(): array
    {
        return [
            'Get first element from flat array'                     => [
                'initialData' => ['first', 'second', 'third'],
                'expected'    => 'first',
            ],
            'Get first element from associative array'              => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'expected'    => 'alpha',
            ],
            'Get first element from single-element array'           => [
                'initialData' => ['only'],
                'expected'    => 'only',
            ],
            'Get first element from empty array'                    => [
                'initialData' => [],
                'expected'    => null,
            ],
            'Get first element from nested array'                   => [
                'initialData' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'expected'    => ['id' => 1, 'name' => 'Alice'],
            ],
            'Get first element from array with mixed keys'          => [
                'initialData' => [10 => 'ten', 'twenty' => 'twenty', 30 => 'thirty'],
                'expected'    => 'ten',
            ],
            'Get first element from multi-dimensional array'        => [
                'initialData' => [
                    'numbers' => [1, 2, 3],
                    'letters' => ['a', 'b', 'c'],
                    'symbols' => ['!', '@', '#'],
                ],
                'expected'    => [1, 2, 3],
            ],
            'Get first element when first element is null'          => [
                'initialData' => [null, 'second', 'third'],
                'expected'    => null,
            ],
            'Get first element when array contains only null'       => [
                'initialData' => [null],
                'expected'    => null,
            ],
            'Get first element from array with duplicate values'    => [
                'initialData' => ['first value', 'value', 'value'],
                'expected'    => 'first value',
            ],
            'Get first element from array with numeric string keys' => [
                'initialData' => ['0' => 'zero', '1' => 'one', '2' => 'two'],
                'expected'    => 'zero',
            ],
            'Get first element from array with boolean values'      => [
                'initialData' => [true, false, true],
                'expected'    => true,
            ],
        ];
    }

    /**
     * @dataProvider lastProvider
     */
    public function testLast($initialData, $expected): void
    {
        // Set up the initial data
        $array   = SmartArray::new($initialData);
        $arraySS = SmartArray::newSS($initialData);

        // Compare Raw
        $result   = $array->last();
        $this->assertSame(
            expected: $expected,
            actual  : self::normalizeRaw($result),
        );

        // Compare SS
        $resultSS = $arraySS->last();
        $this->assertSame(
            expected: self::recursiveHtmlEncode($expected),
            actual  : self::normalizeSS($resultSS)
        );
    }

    public function lastProvider(): array
    {
        return [
            'Get last element from flat array'                     => [
                'initialData' => ['first', 'second', 'third'],
                'expected'    => 'third',
            ],
            'Get last element from associative array'              => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'expected'    => 'gamma',
            ],
            'Get last element from single-element array'           => [
                'initialData' => ['only'],
                'expected'    => 'only',
            ],
            'Get last element from empty array'                    => [
                'initialData' => [],
                'expected'    => null,
            ],
            'Get last element from nested array'                   => [
                'initialData' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'expected'    => ['id' => 3, 'name' => 'Charlie'],
            ],
            'Get last element from array with mixed keys'          => [
                'initialData' => [10 => 'ten', 'twenty' => 'twenty', 30 => 'thirty'],
                'expected'    => 'thirty',
            ],
            'Get last element from multi-dimensional array'        => [
                'initialData' => [
                    'numbers' => [1, 2, 3],
                    'letters' => ['a', 'b', 'c'],
                    'symbols' => ['!', '@', '#'],
                ],
                'expected'    => ['!', '@', '#'],
            ],
            'Get last element when last element is null'           => [
                'initialData' => ['first', 'second', null],
                'expected'    => null,
            ],
            'Get last element when array contains only null'       => [
                'initialData' => [null],
                'expected'    => null,
            ],
            'Get last element from array with duplicate values'    => [
                'initialData' => ['value', 'value', 'last value'],
                'expected'    => 'last value',
            ],
            'Get last element from array with numeric string keys' => [
                'initialData' => ['0' => 'zero', '1' => 'one', '2' => 'two'],
                'expected'    => 'two',
            ],
            'Get last element from array with boolean values'      => [
                'initialData' => [true, false, true],
                'expected'    => true,
            ],
        ];
    }

    /**
     * @dataProvider nthProvider
     */
    public function testNth($initialData, $index, $expected): void
    {
        // Set up the initial data
        $array   = SmartArray::new($initialData);
        $arraySS = SmartArray::newSS($initialData);

        // Compare Raw
        $result   = $array->nth($index);
        $this->assertSame(
            expected: $expected,
            actual  : self::normalizeRaw($result),
        );

        // Compare SS
        $resultSS = $arraySS->nth($index);
        $this->assertSame(
            expected: self::recursiveHtmlEncode($expected),
            actual  : self::normalizeSS($resultSS)
        );
    }

    public function nthProvider(): array
    {
        return [
            'Get first element (index 0) from flat array'                    => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => 0,
                'expected'    => 'first',
            ],
            'Get second element (index 1) from flat array'                   => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => 1,
                'expected'    => 'second',
            ],
            'Get last element using negative index -1'                       => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => -1,
                'expected'    => 'third',
            ],
            'Get second-to-last element using negative index -2'             => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => -2,
                'expected'    => 'second',
            ],
            'Get element with index out of bounds (positive index)'          => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => 5,
                'expected'    => null,
            ],
            'Get element with index out of bounds (negative index)'          => [
                'initialData' => ['first', 'second', 'third'],
                'index'       => -5,
                'expected'    => null,
            ],
            'Get nth element from associative array'                         => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'index'       => 1,
                'expected'    => 'beta',
            ],
            'Get first element from associative array'                       => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'index'       => 0,
                'expected'    => 'alpha',
            ],
            'Get last element from associative array using negative index'   => [
                'initialData' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma'],
                'index'       => -1,
                'expected'    => 'gamma',
            ],
            'Get nth element from nested array'                              => [
                'initialData' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'index'       => 2,
                'expected'    => ['id' => 3, 'name' => 'Charlie'],
            ],
            'Get element from empty array'                                   => [
                'initialData' => [],
                'index'       => 0,
                'expected'    => null,
            ],
            'Get element with index 0 from single-element array'             => [
                'initialData' => ['only'],
                'index'       => 0,
                'expected'    => 'only',
            ],
            'Get element with negative index -1 from single-element array'   => [
                'initialData' => ['only'],
                'index'       => -1,
                'expected'    => 'only',
            ],
            'Get element with index out of bounds from single-element array' => [
                'initialData' => ['only'],
                'index'       => 1,
                'expected'    => null,
            ],
            'Get nth element from mixed keys array'                          => [
                'initialData' => [10 => 'ten', 'twenty' => 'twenty', 30 => 'thirty'],
                'index'       => 1,
                'expected'    => 'twenty',
            ],
            'Get element from multi-dimensional array'                       => [
                'initialData' => [
                    'numbers' => [1, 2, 3],
                    'letters' => ['a', 'b', 'c'],
                    'symbols' => ['!', '@', '#'],
                ],
                'index'       => 1,
                'expected'    => ['a', 'b', 'c'],
            ],
            'Get element with out of order indexed array'                    => [
                'initialData' => [2 => 'first', 4 => 'second', 6 => 'third'],
                'index'       => 1,
                'expected'    => 'second',
            ],
            'Get element with index zero'                                    => [
                'initialData' => ['zero', 'one', 'two'],
                'index'       => 0,
                'expected'    => 'zero',
            ],
        ];
    }

    public function testOffsetGetWarnings(): void
    {
        // Test empty arrays don't produce warnings for undefined keys (since we can't use existing array keys to know if key is valid)
        $smartArrays = [
            SmartArray::new(),
            SmartArray::newSS(),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $smartArray->offsetGet('nonExistentKey');
            $warning = ob_get_clean();
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
        }

        // Test non-empty arrays produce warnings for undefined keys
        $smartArrays = [
            SmartArray::new(['name' => 'Alice', 'city' => 'Wonderland']),
            SmartArray::newSS(['name' => 'Alice', 'city' => 'Wonderland']),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $smartArray->offsetGet('nonExistentKey');
            $warning = ob_get_clean();
            $this->assertStringContainsString("Warning:", $warning, "Expected warning output, got: $warning");
        }

        // Test valid keys in non-empty arrays do not produce warnings
        $smartArrays = [
            SmartArray::new(['name' => 'Alice', 'city' => 'Wonderland']),
            SmartArray::newSS(['name' => 'Alice', 'city' => 'Wonderland']),
        ];
        foreach ($smartArrays as $smartArray) {
            ob_start();
            $smartArray->offsetGet('name');
            $warning = ob_get_clean();
            $this->assertEmpty($warning, "Unexpected warning output: $warning");
        }
    }

    /**
     * @dataProvider offsetGetProvider
     */
    public function testOffsetGet($initialData, $get, $expected): void
    {
        // Set up the initial data
        $array   = SmartArray::new($initialData);
        $arraySS = SmartArray::newSS($initialData);

        // Perform the get operation using the provided closure
        ob_start(); // capture any warnings output by SmartArray::offsetGet()
        $result   = $get($array);
        $resultSS = $get($arraySS);
        ob_end_clean(); // discard any warnings output by SmartArray::offsetGet()

        // Compare Raw
        $this->assertSame(
            expected: $expected,
            actual  : self::normalizeRaw($result),
        );

        // Compare SS
        $this->assertSame(
            expected: self::recursiveHtmlEncode($expected),
            actual  : self::normalizeSS($resultSS)
        );
    }

    public function offsetGetProvider(): array
    {
        return [
            'Get scalar value with array syntax'                     => [
                'initialData' => ['name' => 'Alice'],
                'get'         => fn($array) => $array['name'],
                'expected'    => 'Alice',
            ],
            'Get scalar value with property syntax'                  => [
                'initialData' => ['age' => 30],
                'get'         => fn($array) => $array->age,
                'expected'    => 30,
            ],
            'Get array value with array syntax'                      => [
                'initialData' => ['address' => ['city' => 'New York', 'zip' => '10001']],
                'get'         => fn($array) => $array['address'],
                'expected'    => ['city' => 'New York', 'zip' => '10001'],
            ],
            'Get array value with property syntax'                   => [
                'initialData' => ['contact' => ['email' => 'alice@example.com', 'phone' => '123-456-7890']],
                'get'         => fn($array) => $array->contact,
                'expected'    => ['email' => 'alice@example.com', 'phone' => '123-456-7890'],
            ],
            'Get value with integer key'                             => [
                'initialData' => [42 => 'answer'],
                'get'         => fn($array) => $array[42],
                'expected'    => 'answer',
            ],
            'Get value using numeric string key'                     => [
                'initialData' => ['123' => 'numeric string key'],
                'get'         => fn($array) => $array['123'],
                'expected'    => 'numeric string key',
            ],
            'Get value using special characters in key'              => [
                'initialData' => ['key-with-dash' => 'special key'],
                'get'         => fn($array) => $array['key-with-dash'],
                'expected'    => 'special key',
            ],
            'Get value with empty string key'                        => [
                'initialData' => ['' => 'empty key'],
                'get'         => fn($array) => $array[''],
                'expected'    => 'empty key',
            ],
            'Get nested SmartArray value'                            => [
                'initialData' => ['nested' => ['a' => 1, 'b' => 2]],
                'get'         => fn($array) => $array->nested,
                'expected'    => ['a' => 1, 'b' => 2],
            ],
            'Get value from nested SmartArray using array syntax'    => [
                'initialData' => ['nested' => ['key' => 'value']],
                'get'         => fn($array) => $array['nested']['key'],
                'expected'    => 'value',
            ],
            'Get value from nested SmartArray using property syntax' => [
                'initialData' => ['nested' => ['key' => 'value']],
                'get'         => fn($array) => $array->nested->key,
                'expected'    => 'value',
            ],
            'Get first element using numeric index'                  => [
                'initialData' => ['first', 'second', 'third'],
                'get'         => fn($array) => $array[0],
                'expected'    => 'first',
            ],
            'Get last element using calculated index'                => [
                'initialData' => ['first', 'second', 'third'],
                'get'         => fn($array) => $array[count($array) - 1],
                'expected'    => 'third',
            ],
            'Get non-existent key'                                   => [
                'initialData' => ['name' => 'Alice'],
                'get'         => fn($array) => $array->age,
                'expected'    => null,
            ],
            'Get null value'                                         => [
                'initialData' => ['nothing' => null],
                'get'         => fn($array) => $array->nothing,
                'expected'    => null,
            ],
            'Get boolean value'                                      => [
                'initialData' => ['isActive' => true],
                'get'         => fn($array) => $array->isActive,
                'expected'    => true,
            ],
            'Get float value'                                        => [
                'initialData' => ['pi' => 3.14],
                'get'         => fn($array) => $array->pi,
                'expected'    => 3.14,
            ],
        ];
    }

    /**
     * @dataProvider offsetSetProvider
     */
    public function testOffsetSet($initialData, $set, $expected): void
    {
        // Without SmartStrings
        $array = new SmartArray($initialData);
        $set($array); // Perform the operation using the provided closure
        $actual = $array->toArray();
        $this->assertEquals($expected, $actual);

        // With SmartStrings
        $array = SmartArray::newSS($initialData);
        $set($array); // Perform the operation using the provided closure
        $actual = self::toArrayResolveSS($array);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @noinspection PhpArrayUsedOnlyForWriteInspection
     * @noinspection OnlyWritesOnParameterInspection
     * @noinspection PhpArrayWriteIsNotUsedInspection
     */
    public function offsetSetProvider(): array
    {
        return [
            'Set scalar value with array syntax'        => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array['name'] = 'Alice';
                },
                'expected'    => [
                    'name' => 'Alice',
                ],
            ],
            'Set scalar value with property syntax'     => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array->age = 30;
                },
                'expected'    => [
                    'age' => 30,
                ],
            ],
            'Set array value with array syntax'         => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array['address'] = ['city' => 'New York', 'zip' => '10001'];
                },
                'expected'    => [
                    'address' => ['city' => 'New York', 'zip' => '10001'],
                ],
            ],
            'Set array value with property syntax'      => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array->contact = ['email' => 'alice@example.com', 'phone' => '123-456-7890'];
                },
                'expected'    => [
                    'contact' => ['email' => 'alice@example.com', 'phone' => '123-456-7890'],
                ],
            ],
            'Set null key (append) with scalar value'   => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array[] = 'appended value';
                },
                'expected'    => [
                    'appended value',
                ],
            ],
            'Set null key (append) with array value'    => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array[] = ['item1', 'item2'];
                },
                'expected'    => [
                    ['item1', 'item2'],
                ],
            ],
            'Overwrite existing key'                    => [
                'initialData' => ['key1' => 'initial'],
                'set'         => fn($array) => $array['key1'] = 'overwritten',
                'expected'    => [
                    'key1' => 'overwritten',
                ],
            ],
            'Set integer key'                           => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array[42] = 'answer';
                },
                'expected'    => [
                    42 => 'answer',
                ],
            ],
            'Set float value'                           => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array->pi = 3.14;
                },
                'expected'    => [
                    'pi' => 3.14,
                ],
            ],
            'Set boolean value'                         => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array->isActive = true;
                },
                'expected'    => [
                    'isActive' => true,
                ],
            ],
            'Set null value'                            => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array->nothing = null;
                },
                'expected'    => [
                    'nothing' => null,
                ],
            ],
            'Set value using numeric string key'        => [
                'initialData' => [],
                'set'         => fn($array) => $array['123'] = 'numeric string key',
                'expected'    => [
                    '123' => 'numeric string key',
                ],
            ],
            'Set value using special characters in key' => [
                'initialData' => [],
                'set'         => function ($array) {
                    $array['key-with-dash'] = 'special key';
                },
                'expected'    => [
                    'key-with-dash' => 'special key',
                ],
            ],
            'Set value with empty string key'           => [
                'initialData' => [],
                'set'         => fn($array) => $array[''] = 'empty key',
                'expected'    => [
                    '' => 'empty key',
                ],
            ],
            'Set value with overwriting different type' => [
                'initialData' => ['data' => ['initial' => 'array']],
                'set'         => function ($array) {
                    $array->data = 'now a string';
                },
                'expected'    => [
                    'data' => 'now a string',
                ],
            ],
        ];
    }

#endregion
#region Array Information

    /**
     * @dataProvider countProvider
     */
    public function testCountMethod($input, int $expected): void
    {
        foreach (['new', 'newSS'] as $method) {
            $object = SmartArray::$method($input);
            $this->assertSame($expected, $object->count());
        }
    }

    /**
     * @dataProvider countProvider
     * @noinspection PhpUnitTestsInspection
     */
    public function testCountFunction($input, int $expected): void
    {
        foreach (['new', 'newSS'] as $method) {
            $object = SmartArray::$method($input);
            $this->assertSame($expected, count($object));
            }
    }

    public function countProvider(): array
    {
        return [
            // Test empty arrays
            "empty array"            => [[], 0],
            "empty nested array"     => [[[]], 1], // The outer array has one element (an empty array)

            // Test non-empty arrays
            "single element array"   => [[1], 1],
            "nested array"           => [[[1, 2]], 1],
            "flat array"             => [[1, 2, 3], 3],

            // Test arrays with various types
            "mixed value array"      => [["Hello", 123, null], 3],

            // Test with test records
            "test nested array data" => [$this->getTestRecords(), 3],
            "test flat array data"   => [$this->getTestRecord(), 7],
            "test empty array data"  => [[], 0],
        ];
    }

    /**
     * @dataProvider isEmptyProvider
     */
    public function testIsEmpty($input, bool $expected): void
    {
        foreach (['new', 'newSS'] as $method) {
            $object = SmartArray::$method($input);
            match ($expected) {
                true  => $this->assertTrue($object->isEmpty()),
                false => $this->assertFalse($object->isEmpty()),
            };
        }
    }

    /**
     * @dataProvider isEmptyProvider
     */
    public function testIsNotEmpty($input, bool $expected): void
    {
        foreach (['new', 'newSS'] as $method) {
            $object = SmartArray::$method($input);
            match (!$expected) {
                true  => $this->assertTrue($object->isNotEmpty()),
                false => $this->assertFalse($object->isNotEmpty()),
            };
        }
    }

    public function isEmptyProvider(): array
    {
        return [
            // Test empty arrays
            "empty array"            => [[], true],
            "empty nested array"     => [[[]], false], // Nested empty array is not considered empty

            // Test non-empty arrays
            "single element array"   => [[1], false],
            "nested array"           => [[[1, 2]], false],
            "flat array"             => [[1, 2, 3], false],

            // Test arrays with various types
            "mixed value array"      => [["Hello", 123, null], false],

            // Test with test records
            "test nested array data" => [$this->getTestRecords(), false],
            "test flat array data"   => [$this->getTestRecord(), false],
            "test empty array data"  => [[], true],
        ];
    }

    /**
     * @dataProvider isListProvider
     */
    public function testIsList(array $input, bool $expected): void
    {
        foreach (['new', 'newSS'] as $newMethod) {
            $smartArray = SmartArray::$newMethod($input);
            $keysCSV    = implode(',', array_keys($smartArray->getArrayCopy()));
            $varExport  = var_export($smartArray->toArray(), true);
            $this->assertSame($expected, $smartArray->isList(), "Expected " . var_export($expected, true) . " with keys: $keysCSV\n$varExport");
        }
    }

    public function isListProvider(): array
    {
        return [
            // Sequential numeric arrays (lists)
            'empty array'              => [[], true],
            'sequential numbers'       => [[1, 2, 3], true],
            'sequential strings'       => [['a', 'b', 'c'], true],
            'sequential mixed'         => [[1, 'b', null], true],

            // Non-sequential arrays
            'non-sequential keys'      => [[1 => 'a', 0 => 'b'], false],
            'string keys'             => [['a' => 1, 'b' => 2], false],
            'mixed keys'              => [['a' => 1, 0 => 2], false],
            'gaps in numeric keys'    => [[0 => 'a', 2 => 'b'], false],

            // Nested arrays
            'nested sequential'        => [[1, [2, 3], 4], true],
            'nested non-sequential'    => [['a' => [1, 2], 'b' => 3], false],
        ];
    }

    /**
     * @dataProvider isFlatProvider
     */
    public function testIsFlat(array $input, bool $expected): void
    {
        foreach (['new', 'newSS'] as $newMethod) {
            $smartArray = SmartArray::$newMethod($input);
            $varExport  = var_export($smartArray->toArray(), true);
            $this->assertSame($expected, $smartArray->isFlat(), "Expected " . var_export($expected, true) . " with structure:\n$varExport");
        }
    }

    public function isFlatProvider(): array
    {
        return [
            'empty array' => [
                [],
                true
            ],
            'flat numeric array' => [
                [1, 2, 3],
                true
            ],
            'flat string array' => [
                ['a', 'b', 'c'],
                true
            ],
            'flat associative array' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                true
            ],
            'flat mixed types' => [
                ['a', 2, null, true, 1.5],
                true
            ],
            'nested numeric array' => [
                [1, [2, 3], 4],
                false
            ],
            'nested associative array' => [
                ['a' => ['b' => 2], 'c' => 3],
                false
            ],
            'multiple nested arrays' => [
                ['a' => [1, 2], 'b' => [3, 4]],
                false
            ],
            'deeply nested array' => [
                [1, [2, [3, 4]], 5],
                false
            ],
            'empty nested array' => [
                [1, [], 3],
                false
            ],
            'array at start' => [
                [[1], 2, 3],
                false
            ],
            'array at end' => [
                [1, 2, [3]],
                false
            ],
            'only nested array' => [
                [[1, 2, 3]],
                false
            ]
        ];
    }

    /**
     * @dataProvider isFlatProvider
     */
    public function testIsNested(array $input, bool $expected): void
    {
        $expected   = !$expected; // The expected value is the opposite of our isFlatProvider

        foreach (['new', 'newSS'] as $newMethod) {
            $smartArray = SmartArray::$newMethod($input);
            $varExport  = var_export($smartArray->toArray(), true);
            $this->assertSame($expected, $smartArray->isNested(), "Expected " . var_export($expected, true) . " with structure:\n$varExport");
        }
    }

    /**
     * @dataProvider metadataProvider
     */
    public function testMetadata($input, $metadata, $operation, $expected): void
    {
        // Create initial SmartArray with metadata
        $smartArray = new SmartArray($input, $metadata);

        // Perform operation if specified
        if ($operation) {
            $smartArray = $operation($smartArray);
        }

        // Verify metadata
        $actualMetadata = (array) $smartArray->metadata();
        $this->assertEquals($expected, $actualMetadata);

        // Test nested arrays also have same metadata
        foreach ($smartArray as $value) {
            if ($value instanceof SmartArray) {
                $this->assertEquals($expected, (array) $value->metadata());
            }
        }
    }

    public function metadataProvider(): array
    {
        $baseMetadata = ['database' => 'test_db', 'table' => 'users'];

        return [
            'basic metadata' => [
                'input' => ['name' => 'John', 'age' => 30],
                'metadata' => $baseMetadata,
                'operation' => null,
                'expected' => $baseMetadata
            ],

            'nested array inheritance' => [
                'input' => [
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane']
                ],
                'metadata' => $baseMetadata,
                'operation' => null,
                'expected' => $baseMetadata
            ],

            'metadata preserved after map' => [
                'input' => ['a' => 1, 'b' => 2],
                'metadata' => $baseMetadata,
                'operation' => fn($arr) => $arr->map(fn($x) => $x * 2),
                'expected' => $baseMetadata
            ],

            'metadata preserved after filter' => [
                'input' => ['a' => 1, 'b' => 2, 'c' => 3],
                'metadata' => $baseMetadata,
                'operation' => fn($arr) => $arr->filter(fn($x) => $x > 1),
                'expected' => $baseMetadata
            ],

            'metadata with complex transformations' => [
                'input' => [
                    ['id' => 1, 'score' => 10],
                    ['id' => 2, 'score' => 20],
                    ['id' => 3, 'score' => 30]
                ],
                'metadata' => $baseMetadata,
                'operation' => fn($arr) => $arr
                    ->map(fn($x) => ['id' => $x['id'], 'doubled_score' => $x['score'] * 2])
                    ->filter(fn($x) => $x['doubled_score'] > 30)
                    ->groupBy('id'),
                'expected' => $baseMetadata
            ],

            'empty array with metadata' => [
                'input' => [],
                'metadata' => $baseMetadata,
                'operation' => null,
                'expected' => $baseMetadata
            ],

            'metadata with stdClass' => [
                'input' => ['name' => 'John'],
                'metadata' => (object) $baseMetadata,
                'operation' => null,
                'expected' => $baseMetadata
            ],

            'complex nested structure' => [
                'input' => [
                    'users' => [
                        ['id' => 1, 'details' => ['age' => 25]],
                        ['id' => 2, 'details' => ['age' => 30]]
                    ]
                ],
                'metadata' => $baseMetadata,
                'operation' => null,
                'expected' => $baseMetadata
            ]
        ];
    }

    /**
     * @dataProvider rootProvider
     */
    public function testRoot($input, $operation): void
    {
        $rootArray = new SmartArray($input);
        $testArray = $operation ? $operation($rootArray) : $rootArray;

        // test root array references self
        $this->assertSame($rootArray, $rootArray->root(), "Root array ->root() should reference self");

        // test transformed array references root
        ob_start();
        $rootArray->debug();
        $rootData = ob_get_clean();

        ob_start();
        $testArray->debug();
        $testData = ob_get_clean();

        $this->assertSame($rootArray, $testArray->root(), "Nested array should reference original root\nORIGINAL: $rootData\nMODIFIED: $testData");
    }

    public function rootProvider(): array
    {
        $nestedInput = [
            ['id' => 1, 'name' => 'John', 'city' => 'NYC', 'score' => 85],
            ['id' => 2, 'name' => 'Jane', 'city' => 'LA', 'score' => 92],
            ['id' => 3, 'name' => 'Bob', 'city' => 'NYC', 'score' => 78],
            ['id' => 4, 'name' => 'Alice', 'city' => 'LA', 'score' => 95]
        ];

        return [
            'root array has no root' => [
                'input' => ['a' => 1, 'b' => 2],
                'operation' => null
            ],

            'first() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->first()
            ],

            'last() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->last()
            ],

            'nth() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->nth(2)
            ],

            'map() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->map(fn($x) => ['id' => $x['id'], 'grade' => $x['score'] >= 90 ? 'A' : 'B'])
            ],

            'filter() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->filter(fn($x) => $x['score'] >= 90)
            ],

            'where() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->where(['city' => 'NYC'])
            ],

            'sort() maintains root' => [
                'input' => [5, 2, 8, 1, 9],
                'operation' => fn($arr) => $arr->sort()
            ],

            'sortBy() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->sortBy('score')
            ],

            'unique() maintains root' => [
                'input' => [1, 2, 2, 3, 3, 4],
                'operation' => fn($arr) => $arr->unique()
            ],

            'keys() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->keys()
            ],

            'values() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->values()
            ],

            'pluck() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->pluck('name')
            ],

            'indexBy() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->indexBy('id')
            ],

            'groupBy() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->groupBy('city')->NYC->first()
            ],

            'chunk() maintains root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr->chunk(2)
            ],

            'chained transformations maintain root' => [
                'input' => $nestedInput,
                'operation' => fn($arr) => $arr
                    ->filter(fn($x) => $x['score'] >= 80)
                    ->map(fn($x) => ['name' => $x['name'], 'grade' => $x['score'] >= 90 ? 'A' : 'B'])
                    ->sortBy('name')
                    ->groupBy('grade')
                    ->first()
            ],

            'empty array has no root' => [
                'input' => [],
                'operation' => null
            ]
        ];
    }

#endregion
#region Position & Layout

    /**
     * This test manually loops through the elements of the SmartArray and checks the isFirst() and isLast() methods match the expected values (if they exist).
     *
     * @dataProvider isFirstAndIsLastProvider
     */
    public function testIsFirstAndIsLast($input): void
    {
        $smartArray = new SmartArray($input);

        // Check isFirst() and isLast() for each element
        $smartArrayData = $smartArray->getArrayCopy();
        $assertionMade  = false;
        foreach ($smartArray as $key => $value) {
            // skip if the element is not a nested SmartArray
            if (!$value instanceof SmartArray) {
                continue;
            }

            // Determine if the element is the first or last element
            $isFirstExpected = $key === array_key_first($smartArrayData);
            $isLastExpected  = $key === array_key_last($smartArrayData);
            $this->assertSame($isFirstExpected, $value->isFirst(), "Element at position $key: 'isFirst()' mismatch.");
            $this->assertSame($isLastExpected, $value->isLast(), "Element at position $key: 'isLast()' mismatch." . print_r($value, true));

            $assertionMade = true;
        }

        // If no assertions were made in the loop, make an assertion here to avoid "This test did not perform any assertions" warning
        if (!$assertionMade) {
            $this->assertTrue(true, "No elements with isFirst/isLast methods to test.");
        }
    }

    public function isFirstAndIsLastProvider(): array
    {
        return [
            'empty array'                             => [
                [],
            ],
            'single element array'                    => [
                [$this->getTestRecord()],
            ],
            'multiple elements array'                 => [
                $this->getTestRecords(),
            ],
            'non-sequential integer keys'             => [
                [
                    20 => ['data' => 'second'],
                    10 => ['data' => 'first'],
                    30 => ['data' => 'third'],
                ],
            ],
            'associative array with string keys'      => [
                [
                    'first'  => ['data' => 'first'],
                    'middle' => ['data' => 'middle'],
                    'last'   => ['data' => 'last'],
                ],
            ],
            'mixed elements'                          => [
                [
                    'a' => ['data' => 'first'],
                    'b' => 'string value',
                    'c' => 123,
                    'd' => null,
                    'e' => ['data' => 'last'],
                ],
            ],
            'mixed elements 2'                        => [
                [
                    'a' => ['data' => 'first'],
                    'b' => 'string value',
                    'c' => 123,
                    'e' => ['data' => 'last'],
                    'd' => null,
                ],
            ],
            'elements without isFirst/isLast methods' => [
                [
                    'x' => 'string value',
                    'y' => 123,
                    'z' => null,
                ],
            ],
            'nested arrays'                           => [
                [
                    'nested' => $this->getTestRecords(),
                    'single' => [$this->getTestRecord()],
                ],
            ],
        ];
    }

    /**
     * @dataProvider positionProvider
     */
    public function testPosition($initialData, $expectedPositions): void
    {
        // Set up the initial data
        $array = new SmartArray($initialData);

        $actualPositions = [];
        foreach ($array as $element) {
            if ($element instanceof SmartArray) {
                $actualPositions[] = $element->position();
            }
        }

        // Assert that the actual positions match the expected positions
        $this->assertEquals($expectedPositions, $actualPositions);
    }

    public function positionProvider(): array
    {
        return [
            'Nested SmartArrays'                 => [
                'initialData'       => [
                    'first'  => ['id' => 1, 'name' => 'Alice'],
                    'second' => ['id' => 2, 'name' => 'Bob'],
                    'third'  => ['id' => 3, 'name' => 'Charlie'],
                ],
                'expectedPositions' => [1, 2, 3],
            ],
            'Only some elements are SmartArrays' => [
                'initialData'       => [
                    'group1' => ['member1' => 'Alice', 'member2' => 'Bob'],
                    'group2' => 'Not a SmartArray',
                    'group3' => ['member3' => 'Charlie'],
                ],
                'expectedPositions' => [1, 3],
            ],
            'Empty array'                        => [
                'initialData'       => [],
                'expectedPositions' => [],
            ],
            'Single nested SmartArray'           => [
                'initialData'       => [
                    'only' => ['id' => 1, 'name' => 'Single'],
                ],
                'expectedPositions' => [1],
            ],
            'Mixed element types'                => [
                'initialData'       => [
                    'nestedArray'  => ['key' => 'value'],
                    'stringValue'  => 'Just a string',
                    'intValue'     => 42,
                    'nullValue'    => null,
                    'anotherArray' => ['foo' => 'bar'],
                ],
                'expectedPositions' => [1, 5],
            ],
        ];
    }

    /**
     * @dataProvider isMultipleOfProvider
     */
    public function testIsMultipleOf($initialData, $number, $expectedResults): void
    {
        // Set up the initial data
        $array = new SmartArray($initialData);

        $actualResults = [];

        // Iterate over the elements in the SmartArray
        foreach ($array as $element) {
            // Check if the element is a SmartArray
            if ($element instanceof SmartArray) {
                // Get whether the element's position is a multiple of the given number
                $isMultiple      = $element->isMultipleOf($number);
                $actualResults[] = $isMultiple;
            } else {
                $actualResults[] = null; // for testing show ignored elements as null for easier comparison
            }
        }

        // Assert that the actual results match the expected results
        $this->assertEquals($expectedResults, $actualResults);
    }

    public function isMultipleOfProvider(): array
    {
        return [
            'Nested SmartArrays with number 2'       => [
                'initialData'     => [
                    ['id' => 1, 'name' => 'Alice'],   // Position 1
                    ['id' => 2, 'name' => 'Bob'],     // Position 2
                    ['id' => 3, 'name' => 'Charlie'], // Position 3
                    ['id' => 4, 'name' => 'Dave'],    // Position 4
                ],
                'number'          => 2,
                'expectedResults' => [false, true, false, true],
            ],
            'Mixed elements with number 3'           => [
                'initialData'     => [
                    ['item' => 'A'],      // Position 1
                    'Not a SmartArray',   // Position 2 (ignored)
                    ['item' => 'B'],      // Position 3
                    ['item' => 'C'],      // Position 4
                    ['item' => 'D'],      // Position 5
                    ['item' => 'E'],      // Position 6
                    ['item' => 'F'],      // Position 7
                    ['item' => 'G'],      // Position 8
                ],
                'number'          => 3,
                'expectedResults' => [false, null, true, false, false, true, false, false],
            ],
            'Empty array'                            => [
                'initialData'     => [],
                'number'          => 2,
                'expectedResults' => [],
            ],
            'Single nested SmartArray with number 1' => [
                'initialData'     => [
                    ['id' => 1, 'name' => 'Single'], // Position 1
                ],
                'number'          => 1,
                'expectedResults' => [true],
            ],
            'Non-sequential positions with number 2' => [
                'initialData'     => [
                    ['group' => 'A'],  // Position 1
                    ['group' => 'B'],  // Position 2
                    ['group' => 'C'],  // Position 3
                ],
                'number'          => 2,
                'expectedResults' => [false, true, false],
            ],
        ];
    }

    /**
     * @dataProvider chunkProvider
     */
    public function testChunk($input, $size, $expected, $shouldThrowException = false, $expectedExceptionMessage = ''): void
    {
        $smartArray = new SmartArray($input);

        // test for exceptions
        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
            $smartArray->chunk($size);
            return;
        }

        // get actual output
        $actual = $smartArray->chunk($size)->toArray();

        // compare
        $this->assertEquals($expected, $actual, "Chunked SmartArray does not match expected output.");
    }

    public function chunkProvider(): array
    {
        return [
            'empty array'                  => [
                'input'                => [],
                'size'                 => 3,
                'expected'             => [],
                'shouldThrowException' => false,
            ],
            'size greater than array size' => [
                'input'                => [1, 2, 3, 4, 5],
                'size'                 => 10,
                'expected'             => [[1, 2, 3, 4, 5]],
                'shouldThrowException' => false,
            ],
            'size less than array size'    => [
                'input'                => [1, 2, 3, 4, 5],
                'size'                 => 2,
                'expected'             => [[1, 2], [3, 4], [5]],
                'shouldThrowException' => false,
            ],
            'size equal to array size'     => [
                'input'                => [1, 2, 3, 4, 5],
                'size'                 => 5,
                'expected'             => [[1, 2, 3, 4, 5]],
                'shouldThrowException' => false,
            ],
            'size is one'                  => [
                'input'                => [1, 2, 3, 4, 5],
                'size'                 => 1,
                'expected'             => [[1], [2], [3], [4], [5]],
                'shouldThrowException' => false,
            ],
            'negative size'                => [
                'input'                    => [1, 2, 3],
                'size'                     => -2,
                'expected'                 => [],
                'shouldThrowException'     => true,
                'expectedExceptionMessage' => "Chunk size must be greater than 0.",
            ],
            'zero size'                    => [
                'input'                    => [1, 2, 3],
                'size'                     => 0,
                'expected'                 => [],
                'shouldThrowException'     => true,
                'expectedExceptionMessage' => "Chunk size must be greater than 0.",
            ],
            'nested arrays'                => [
                'input'                => [[1, 2], [3, 4], [5, 6]],
                'size'                 => 2,
                'expected'             => [
                    [[1, 2], [3, 4]],
                    [[5, 6]],
                ],
                'shouldThrowException' => false,
            ],
            'non-integer elements'         => [
                'input'                => ['a', 'b', 'c', 'd', 'e'],
                'size'                 => 2,
                'expected'             => [['a', 'b'], ['c', 'd'], ['e']],
                'shouldThrowException' => false,
            ],
            'large size'                   => [
                'input'                => range(1, 100),
                'size'                 => 15,
                'expected'             => array_chunk(range(1, 100), 15),
                'shouldThrowException' => false,
            ],
            'user array chunks'            => [
                'input'                => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                    ['id' => 4, 'name' => 'David'],
                    ['id' => 5, 'name' => 'Eve'],
                ],
                'size'                 => 2,
                'expected'             => [
                    [
                        ['id' => 1, 'name' => 'Alice'],
                        ['id' => 2, 'name' => 'Bob'],
                    ],
                    [
                        ['id' => 3, 'name' => 'Charlie'],
                        ['id' => 4, 'name' => 'David'],
                    ],
                    [
                        ['id' => 5, 'name' => 'Eve'],
                    ],
                ],
                'shouldThrowException' => false,
            ],
        ];
    }

#endregion
#region Sorting & Filtering

    /**
     * @dataProvider sortProvider
     */
    public function testSort($input, $flags, $expected, $shouldThrowException = false): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a flat array, but got a nested array");
            $smartArray->sort($flags);
            return;
        }

        $sorted = $smartArray->sort($flags);

        // Verify sort worked correctly
        $this->assertEquals($expected, $sorted->toArray(), "Sorted array does not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public function sortProvider(): array
    {
        return [
            'empty array' => [
                'input' => [],
                'flags' => SORT_REGULAR,
                'expected' => [],
            ],
            'numeric array' => [
                'input' => [3, 1, 4, 1, 5, 9, 2, 6, 5],
                'flags' => SORT_NUMERIC,
                'expected' => [1, 1, 2, 3, 4, 5, 5, 6, 9],
            ],
            'string array' => [
                'input' => ['banana', 'apple', 'Cherry', 'date', 'Apple'],
                'flags' => SORT_STRING,
                'expected' => ['Apple', 'Cherry', 'apple', 'banana', 'date'],
            ],
            'case-insensitive sort' => [
                'input' => ['banana', 'apple', 'Cherry', 'date', 'Apple'],
                'flags' => SORT_STRING | SORT_FLAG_CASE,
                'expected' => ['apple', 'Apple', 'banana', 'Cherry', 'date'],
            ],
            'mixed types array' => [
                'input' => ['10', 20, '5', 15, '25'],
                'flags' => SORT_REGULAR,
                'expected' => ['5', '10', 15, 20, '25'],
            ],
            'array with null values' => [
                'input' => [3, null, 1, null, 2],
                'flags' => SORT_REGULAR,
                'expected' => [null, null, 1, 2, 3],
            ],
            'nested array throws exception' => [
                'input' => [[1, 2], [3, 4]],
                'flags' => SORT_REGULAR,
                'expected' => [],
                'shouldThrowException' => true,
            ],
        ];
    }

    /**
     * @dataProvider sortByProvider
     */
    public function testSortBy(array $input, string $column, int $type = SORT_REGULAR, array $expected = [], bool $shouldThrowException = false): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a nested array, but got a flat array");
            $smartArray->sortBy($column, $type);
            return;
        }

        // Start output buffering to capture any warnings about missing columns
        ob_start();
        $sorted = $smartArray->sortBy($column, $type);
        ob_end_clean();

        // Verify sort worked correctly
        $this->assertEquals($expected, $sorted->toArray(), "Sorted array does not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public function sortByProvider(): array
    {
        return [
            'sort by string column' => [
                'input' => [
                    ['name' => 'Charlie', 'age' => 30],
                    ['name' => 'Alice', 'age' => 25],
                    ['name' => 'Bob', 'age' => 35],
                ],
                'column' => 'name',
                'type' => SORT_STRING,
                'expected' => [
                    ['name' => 'Alice', 'age' => 25],
                    ['name' => 'Bob', 'age' => 35],
                    ['name' => 'Charlie', 'age' => 30],
                ],
            ],
            'sort by numeric column' => [
                'input' => [
                    ['id' => 3, 'value' => 'c'],
                    ['id' => 1, 'value' => 'a'],
                    ['id' => 2, 'value' => 'b'],
                ],
                'column' => 'id',
                'type' => SORT_NUMERIC,
                'expected' => [
                    ['id' => 1, 'value' => 'a'],
                    ['id' => 2, 'value' => 'b'],
                    ['id' => 3, 'value' => 'c'],
                ],
            ],
            'empty array' => [
                'input' => [],
                'column' => 'name',
                'type'  => 0,  // e.g., default SORT_REGULAR
                'expected' => [],
            ],
            'flat array throws exception' => [
                'input' => [1, 2, 3],
                'column' => 'any',
                'type'  => 0,  // e.g., default SORT_REGULAR
                'expected' => [],
                'shouldThrowException' => true,
            ],
        ];
    }

    /**
     * @dataProvider uniqueProvider
     */
    public function testUnique($input, $expected, $shouldThrowException = false): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a flat array, but got a nested array");
            $smartArray->unique();
            return;
        }

        $unique = $smartArray->unique();

        // Verify unique worked correctly
        $this->assertEquals($expected, $unique->toArray(), "Array with duplicates removed does not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public function uniqueProvider(): array
    {
        return [
            'empty array' => [
                'input' => [],
                'expected' => [],
            ],
            'array with numeric duplicates' => [
                'input' => [1, 2, 2, 3, 3, 3, 4],
                'expected' => [0 => 1, 1 => 2, 3 => 3, 6 => 4],
            ],
            'array with string duplicates' => [
                'input' => ['apple', 'banana', 'apple', 'cherry', 'banana'],
                'expected' => [0 => 'apple', 1 => 'banana', 3 => 'cherry'],
            ],
            'array with mixed type duplicates' => [
                'input' => [1, '1', '2', 2, true, 1, '1', false],
                'expected' => [0 => 1, 2 => '2', 7 => false],
            ],
            'array with null values' => [
                'input' => [null, 1, null, 2, null],
                'expected' => [0 => null, 1 => 1, 3 => 2],
            ],
            'preserves keys' => [
                'input' => ['a' => 1, 'b' => 2, 'c' => 2, 'd' => 1],
                'expected' => ['a' => 1, 'b' => 2],
            ],
            'nested array throws exception' => [
                'input' => [[1, 2], [1, 2], [3, 4]],
                'expected' => [],
                'shouldThrowException' => true,
            ],
        ];
    }

    /**
     * @dataProvider filterProvider
     */
    public function testFilter($input, $callback, $expected): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        $filtered = $callback ? $smartArray->filter($callback) : $smartArray->filter();

        // Verify filter worked correctly
        $this->assertEquals($expected, $filtered->toArray(), "Filtered array does not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    /**
     * @return array[]
     * @noinspection SpellCheckingInspection // ignore test strings
     */
    public function filterProvider(): array
    {
        return [
            'empty array' => [
                'input' => [],
                'callback' => null,
                'expected' => [],
            ],
            'filter without callback' => [
                'input' => [1, 0, true, false, '', null, 'hello', [], '0'],
                'callback' => null,
                'expected' => [0 => 1, 2 => true, 6 => 'hello'], // PHP's default behavior keeps truthy values
            ],
            'filter numbers greater than 5' => [
                'input' => [1, 3, 5, 7, 9, 2, 4, 6, 8],
                'callback' => fn($value) => $value > 5,
                'expected' => [3 => 7, 4 => 9, 7 => 6, 8 => 8],
            ],
            'filter strings by length' => [
                'input' => ['a', 'abc', 'abcd', 'ab', 'abcde'],
                'callback' => fn($value) => strlen($value) > 3,
                'expected' => [2 => 'abcd', 4 => 'abcde'],
            ],
            'filter with key access' => [
                'input' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
                'callback' => fn($value, $key) => $key === 'a' || $value > 3,
                'expected' => ['a' => 1, 'd' => 4],
            ],
            'filter nested arrays' => [
                'input' => [
                    ['id' => 1, 'data' => []],
                    ['id' => 2, 'data' => ['a']],
                    ['id' => 3, 'data' => ['b', 'c']],
                ],
                'callback' => fn($row) => count($row['data']) > 0,
                'expected' => [
                    1 => ['id' => 2, 'data' => ['a']],
                    2 => ['id' => 3, 'data' => ['b', 'c']],
                ],
            ],
            'filter with type checking' => [
                'input' => [1, '2', 3, '4', 5],
                'callback' => fn($value) => is_string($value),
                'expected' => [1 => '2', 3 => '4'],
            ],
            'filter with complex condition' => [
                'input' => [
                    'test1' => ['value' => 10, 'active' => true],
                    'test2' => ['value' => 20, 'active' => false],
                    'test3' => ['value' => 30, 'active' => true],
                ],
                'callback' => fn($item) => $item['active'] && $item['value'] > 15,
                'expected' => ['test3' => ['value' => 30, 'active' => true]],
            ],
        ];
    }

    /**
     * @dataProvider whereProvider
     */
    public function testWhere($input, $conditions, $expected): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        $filtered = $smartArray->where($conditions);

        // Verify where clause worked correctly
        $this->assertEquals($expected, $filtered->toArray(), "Filtered array does not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    public function whereProvider(): array
    {
        return [
            'empty array' => [
                'input' => [],
                'conditions' => ['status' => 'active'],
                'expected' => [],
            ],
            'single condition' => [
                'input' => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2, 'status' => 'inactive'],
                    ['id' => 3, 'status' => 'active'],
                ],
                'conditions' => ['status' => 'active'],
                'expected' => [
                    0 => ['id' => 1, 'status' => 'active'],
                    2 => ['id' => 3, 'status' => 'active'],
                ],
            ],
            'multiple conditions' => [
                'input' => [
                    ['id' => 1, 'status' => 'active', 'type' => 'user'],
                    ['id' => 2, 'status' => 'active', 'type' => 'admin'],
                    ['id' => 3, 'status' => 'inactive', 'type' => 'user'],
                ],
                'conditions' => ['status' => 'active', 'type' => 'user'],
                'expected' => [
                    0 => ['id' => 1, 'status' => 'active', 'type' => 'user'],
                ],
            ],
            'non-matching conditions' => [
                'input' => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2, 'status' => 'inactive'],
                ],
                'conditions' => ['status' => 'pending'],
                'expected' => [],
            ],
            'condition with null value' => [
                'input' => [
                    ['id' => 1, 'parent_id' => null],
                    ['id' => 2, 'parent_id' => 1],
                    ['id' => 3, 'parent_id' => null],
                ],
                'conditions' => ['parent_id' => null],
                'expected' => [
                    0 => ['id' => 1, 'parent_id' => null],
                    2 => ['id' => 3, 'parent_id' => null],
                ],
            ],
            'missing column' => [
                'input' => [
                    ['id' => 1, 'status' => 'active'],
                    ['id' => 2], // missing status
                    ['id' => 3, 'status' => 'active'],
                ],
                'conditions' => ['status' => 'active'],
                'expected' => [
                    0 => ['id' => 1, 'status' => 'active'],
                    2 => ['id' => 3, 'status' => 'active'],
                ],
            ],
            'non-array elements are skipped' => [
                'input' => [
                    ['id' => 1, 'status' => 'active'],
                    'not an array',
                    ['id' => 2, 'status' => 'active'],
                ],
                'conditions' => ['status' => 'active'],
                'expected' => [
                    0 => ['id' => 1, 'status' => 'active'],
                    2 => ['id' => 2, 'status' => 'active'],
                ],
            ],
            'exact value matching' => [
                'input' => [
                    ['count' => 0],
                    ['count' => '0'],
                    ['count' => null],
                    ['count' => false],
                    ['count' => ''],
                ],
                'conditions' => ['count' => 0],
                'expected' => [
                    0 => ['count' => 0],
                ],
            ],
            'multiple exact value matching' => [
                'input' => [
                    ['status' => 'active', 'type' => 'user'],
                    ['status' => 'active', 'type' => 'admin'],
                    ['status' => 'inactive', 'type' => 'user'],
                    ['status' => 'active'], // missing type
                    ['type' => 'user'],    // missing status
                ],
                'conditions' => [
                    'status' => 'active',
                    'type' => 'user'
                ],
                'expected' => [
                    0 => ['status' => 'active', 'type' => 'user'],
                ],
            ],
        ];
    }

#endregion
#region Array Transformation

    /**
     * @dataProvider toArrayProvider
     */
    public function testToArray($input, $expectedOutput): void
    {
        $smartArray   = new SmartArray($input);
        $actualOutput = $smartArray->toArray();
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function toArrayProvider(): array
    {
        $testRecords               = $this->getTestRecords();
        $expectedTestRecordsOutput = $testRecords;
        foreach ($expectedTestRecordsOutput as $index => $record) { // Convert SmartString objects to their string values
            foreach ($record as $key => $value) {
                if ($value instanceof SmartString) {
                    $expectedTestRecordsOutput[$index][$key] = $value->value();
                }
            }
        }

        return [
            'empty array'                => [
                'input'          => [],
                'expectedOutput' => [],
            ],
            'flat array with primitives' => [
                'input'          => [1, 'string', true, null, 3.14],
                'expectedOutput' => [1, 'string', true, null, 3.14],
            ],
            'array with SmartString'     => [
                'input'          => [
                    'name' => 'John <b> Doe',
                    'age'  => 30,
                ],
                'expectedOutput' => [
                    'name' => 'John <b> Doe',
                    'age'  => 30,
                ],
            ],
            'nested SmartArray'          => [
                'input'          => [
                    'user'   => [
                        'name'  => 'Jane " Doe',
                        'email' => 'jane@example.com',
                    ],
                    'active' => true,
                ],
                'expectedOutput' => [
                    'user'   => [
                        'name'  => 'Jane " Doe',
                        'email' => 'jane@example.com',
                    ],
                    'active' => true,
                ],
            ],
            'deeply nested SmartArray'   => [
                'input'          => [
                    'level1' => [
                        'level2' => [
                            'level3' => [
                                'value' => '<deep> value',
                            ],
                        ],
                    ],
                ],
                'expectedOutput' => [
                    'level1' => [
                        'level2' => [
                            'level3' => [
                                'value' => '<deep> value',
                            ],
                        ],
                    ],
                ],
            ],
            'mixed types'                => [
                'input'          => [
                    'number'      => 42,
                    'string'      => 'hello',
                    'smartString' => '"world"',
                    'array'       => [1, 2, 3],
                    'smartArray'  => [4, 5, 6],
                ],
                'expectedOutput' => [
                    'number'      => 42,
                    'string'      => 'hello',
                    'smartString' => '"world"',
                    'array'       => [1, 2, 3],
                    'smartArray'  => [4, 5, 6],
                ],
            ],
            'using test records'         => [
                'input'          => $testRecords,
                'expectedOutput' => $expectedTestRecordsOutput,
            ],
        ];
    }

    /**
     * @dataProvider keysProvider
     */
    public function testKeys($input, $expected): void
    {
        $expectedSmartArray = new SmartArray($expected);
        $actualSmartArray   = (new SmartArray($input))->keys();
        $this->assertSame($expectedSmartArray->toArray(), $actualSmartArray->toArray());
    }

    public function keysProvider(): array
    {
        return [
            'empty array'                  => [
                'input'    => [],
                'expected' => [],
            ],
            'flat array with numeric keys' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'expected' => [0, 1, 2],
            ],
            'associative array'            => [
                'input'    => [
                    'first'  => 'apple',
                    'second' => 'banana',
                    'third'  => 'cherry',
                ],
                'expected' => ['first', 'second', 'third'],
            ],
            'nested array'                 => [
                'input'    => [
                    'fruits'     => ['apple', 'banana'],
                    'vegetables' => ['carrot', 'lettuce'],
                ],
                'expected' => ['fruits', 'vegetables'],
            ],
            'mixed keys'                   => [
                'input'    => [
                    0       => 'apple',
                    'one'   => 'banana',
                    2       => 'cherry',
                    'three' => 'date',
                ],
                'expected' => [0, 'one', 2, 'three'],
            ],
        ];
    }

    /**
     * @dataProvider valuesProvider
     */
    public function testValues($input, $expected): void
    {
        $expectedSmartArray = new SmartArray($expected);
        $actualSmartArray   = (new SmartArray($input))->values();
        $this->assertSame($expectedSmartArray->toArray(), $actualSmartArray->toArray());
    }

    public function valuesProvider(): array
    {
        return [
            'empty array'                => [
                'input'    => [],
                'expected' => [],
            ],
            'flat array with primitives' => [
                'input'    => [1, 'string', true, null, 3.14],
                'expected' => [1, 'string', true, null, 3.14],
            ],
            'associative array'          => [
                'input'    => [
                    'first'  => 'apple',
                    'second' => 'banana',
                    'third'  => 'cherry',
                ],
                'expected' => ['apple', 'banana', 'cherry'],
            ],
            'nested array'               => [
                'input'    => [
                    'fruits'     => ['apple', 'banana'],
                    'vegetables' => ['carrot', 'lettuce'],
                ],
                'expected' => [
                    ['apple', 'banana'],
                    ['carrot', 'lettuce'],
                ],
            ],
            'mixed keys'                 => [
                'input'    => [
                    0       => 'apple',
                    'one'   => 'banana',
                    2       => 'cherry',
                    'three' => 'date',
                ],
                'expected' => ['apple', 'banana', 'cherry', 'date'],
            ],
            'mixed data types'           => [
                'input'    => [
                    'name'    => 'John Doe',
                    'age'     => 30,
                    'emails'  => ['john@example.com', 'doe@example.com'],
                    'active'  => true,
                    'profile' => null,
                ],
                'expected' => [
                    'John Doe',
                    30,
                    ['john@example.com', 'doe@example.com'],
                    true,
                    null,
                ],
            ],
        ];
    }

    /**
     * @dataProvider indexByProvider
     */
    public function testIndexBy($input, $key, $expected): void
    {
        $smartArray = new SmartArray($input);
        $indexed    = $smartArray->indexBy($key);

        $expectedSmartArray = new SmartArray($expected);
        $this->assertSame($expectedSmartArray->toArray(), $indexed->toArray());
    }

    public function indexByProvider(): array
    {
        return [
            'unique keys'    => [
                'input'    => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'key'      => 'id',
                'expected' => [
                    1 => ['id' => 1, 'name' => 'Alice'],
                    2 => ['id' => 2, 'name' => 'Bob'],
                    3 => ['id' => 3, 'name' => 'Charlie'],
                ],
            ],
            'duplicate keys' => [
                'input'    => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 1, 'name' => 'Alicia'],
                ],
                'key'      => 'id',
                'expected' => [
                    1 => ['id' => 1, 'name' => 'Alicia'],
                    2 => ['id' => 2, 'name' => 'Bob'],
                ],
            ],
            'empty array'    => [
                'input'    => [],
                'key'      => 'id',
                'expected' => [],
            ],
            'mixed keys'     => [
                'input'    => [
                    ['key' => 'alpha', 'value' => 'A'],
                    ['key' => 2, 'value' => 'B'],
                    ['key' => 'gamma', 'value' => 'C'],
                ],
                'key'      => 'key',
                'expected' => [
                    'alpha' => ['key' => 'alpha', 'value' => 'A'],
                    2       => ['key' => 2, 'value' => 'B'],
                    'gamma' => ['key' => 'gamma', 'value' => 'C'],
                ],
            ],
        ];
    }

    /**
     * @dataProvider groupByProvider
     */
    public function testGroupBy($input, $key, $expected): void
    {
        $smartArray = new SmartArray($input);
        $grouped    = $smartArray->groupBy($key);

        $expectedSmartArray = new SmartArray($expected);
        $this->assertSame($expectedSmartArray->toArray(), $grouped->toArray());
    }

    public function groupByProvider(): array
    {
        return [
            'unique group keys'    => [
                'input'    => [
                    ['category' => 'Fruit', 'name' => 'Apple'],
                    ['category' => 'Vegetable', 'name' => 'Carrot'],
                    ['category' => 'Dairy', 'name' => 'Milk'],
                ],
                'key'      => 'category',
                'expected' => [
                    'Fruit'     => [
                        ['category' => 'Fruit', 'name' => 'Apple'],
                    ],
                    'Vegetable' => [
                        ['category' => 'Vegetable', 'name' => 'Carrot'],
                    ],
                    'Dairy'     => [
                        ['category' => 'Dairy', 'name' => 'Milk'],
                    ],
                ],
            ],
            'duplicate group keys' => [
                'input'    => [
                    ['category' => 'Fruit', 'name' => 'Apple'],
                    ['category' => 'Fruit', 'name' => 'Banana'],
                    ['category' => 'Vegetable', 'name' => 'Carrot'],
                    ['category' => 'Fruit', 'name' => 'Cherry'],
                    ['category' => 'Vegetable', 'name' => 'Lettuce'],
                ],
                'key'      => 'category',
                'expected' => [
                    'Fruit'     => [
                        ['category' => 'Fruit', 'name' => 'Apple'],
                        ['category' => 'Fruit', 'name' => 'Banana'],
                        ['category' => 'Fruit', 'name' => 'Cherry'],
                    ],
                    'Vegetable' => [
                        ['category' => 'Vegetable', 'name' => 'Carrot'],
                        ['category' => 'Vegetable', 'name' => 'Lettuce'],
                    ],
                ],
            ],
            'empty array'          => [
                'input'    => [],
                'key'      => 'category',
                'expected' => [],
            ],
            'mixed group keys'     => [
                'input'    => [
                    ['key' => 'alpha', 'value' => 'A'],
                    ['key' => 2, 'value' => 'B'],
                    ['key' => 'gamma', 'value' => 'C'],
                    ['key' => 2, 'value' => 'D'],
                ],
                'key'      => 'key',
                'expected' => [
                    'alpha' => [
                        ['key' => 'alpha', 'value' => 'A'],
                    ],
                    2       => [
                        ['key' => 2, 'value' => 'B'],
                        ['key' => 2, 'value' => 'D'],
                    ],
                    'gamma' => [
                        ['key' => 'gamma', 'value' => 'C'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider pluckProvider
     */
    public function testPluck($input, $key, $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of the original array

        // Start output buffering to capture the warnings when requesting non-existing keys from non-empty arrays
        ob_start();
        $plucked = $smartArray->pluck($key);
        ob_end_clean();

        // compare
        $this->assertEquals($expected, $plucked->toArray(), "Plucked SmartArray does not match expected output.");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified.");
    }

    public function pluckProvider(): array
    {
        return [
            'pluck on empty array'                     => [
                'input'    => [],
                'key'      => 'id',
                'expected' => [],
            ],
            'pluck existing key from nested array'     => [
                'input'    => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'key'      => 'name',
                'expected' => ['Alice', 'Bob', 'Charlie'],
            ],
            'pluck non-existing key from nested array' => [
                'input'    => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2],
                    ['id' => 3, 'name' => 'Charlie'],
                ],
                'key'      => 'name',
                'expected' => ['Alice', 'Charlie'],
            ],
            'pluck key missing in all elements'        => [
                'input'    => [
                    ['id' => 1],
                    ['id' => 2],
                    ['id' => 3],
                ],
                'key'      => 'name',
                'expected' => [],
            ],
            'pluck with mixed types in nested array'   => [
                'input'    => [
                    ['id' => 1, 'data' => ['value' => 10]],
                    ['id' => 2, 'data' => ['value' => 20]],
                    ['id' => 3, 'data' => ['value' => 30]],
                ],
                'key'      => 'data',
                'expected' => [
                    ['value' => 10],
                    ['value' => 20],
                    ['value' => 30],
                ],
            ],
            'pluck with numeric keys'                  => [
                'input'    => [
                    [0 => 'zero', 1 => 'one'],
                    [0 => 'zero', 1 => 'one'],
                    [0 => 'zero', 1 => 'one'],
                ],
                'key'      => 1,
                'expected' => ['one', 'one', 'one'],
            ],
            'pluck with key as integer string'         => [
                'input'    => [
                    ['0' => 'zero', '1' => 'one'],
                    ['0' => 'zero', '1' => 'one'],
                ],
                'key'      => '1',
                'expected' => ['one', 'one'],
            ],
        ];
    }

    public function testPluckOnNonNestedArrayThrowsException(): void
    {
        $smartArray    = new SmartArray(['id' => 1, 'name' => 'Alice']);
        $originalArray = $smartArray->toArray();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Expected a nested array, but got a flat array");

        $smartArray->pluck('name');

        // Ensure original SmartArray remains unchanged
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified.");
    }

    public function testPluckMissingKeyOutputsWarning(): void
    {
        $smartArray = new SmartArray([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Charlie'],
        ]);

        // Start output buffering to capture the warning output
        ob_start();
        $plucked = $smartArray->pluck('city');
        $output  = ob_get_clean();

        // Assert that the output contains the expected warning message
        $expectedWarningPattern = "/Warning: .*pluck\(\): 'city' doesn't exist .*Valid keys are:/s";
        $this->assertMatchesRegularExpression($expectedWarningPattern, $output, "Expected warning message not found in output.");

        // Assert that the plucked SmartArray matches the expected output
        $expected = [];
        $this->assertEquals($expected, $plucked->toArray(), "Plucked SmartArray does not match expected output.");
    }

    /**
     * @dataProvider pluckNthProvider
     */
    public function testPluckNth($input, $index, $expected): void
    {
        $smartArray = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of original array for verification

        // Start output buffering to capture any warnings
        ob_start();
        $result = $smartArray->pluckNth($index);
        ob_end_clean();

        // Verify the plucked values match expected output
        $this->assertEquals($expected, $result->toArray(), "Plucked values do not match expected output");

        // Verify original array wasn't modified
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original array should not be modified");
    }

    /** @noinspection SqlNoDataSourceInspection */
    public function pluckNthProvider(): array
    {
        return [
            'empty array' => [
                'input' => [],
                'index' => 0,
                'expected' => [],
            ],
            'first position (0) from flat values' => [
                'input' => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index' => 0,
                'expected' => ['John', 'Jane', 'Bob'],
            ],
            'middle position (1) from flat values' => [
                'input' => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index' => 1,
                'expected' => ['Doe', 'Smith', 'Brown'],
            ],
            'last position (2) from flat values' => [
                'input' => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index' => 2,
                'expected' => ['Developer', 'Designer', 'Manager'],
            ],
            'negative index (-1) gets last element' => [
                'input' => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index' => -1,
                'expected' => ['Developer', 'Designer', 'Manager'],
            ],
            'negative index (-2) gets second to last element' => [
                'input' => [
                    ['John', 'Doe', 'Developer'],
                    ['Jane', 'Smith', 'Designer'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index' => -2,
                'expected' => ['Doe', 'Smith', 'Brown'],
            ],
            'index beyond array bounds returns empty array' => [
                'input' => [
                    ['John', 'Doe'],
                    ['Jane', 'Smith'],
                ],
                'index' => 5,
                'expected' => [],
            ],
            'negative index beyond bounds returns empty array' => [
                'input' => [
                    ['John', 'Doe'],
                    ['Jane', 'Smith'],
                ],
                'index' => -5,
                'expected' => [],
            ],
            'rows with different lengths' => [
                'input' => [
                    ['John', 'Doe', 'Developer', 'Team A'],
                    ['Jane', 'Smith'],
                    ['Bob', 'Brown', 'Manager'],
                ],
                'index' => 2,
                'expected' => ['Developer', 'Manager'], // Skip row without index 2
            ],
            'single column result' => [
                'input' => [
                    ['SHOW TABLES'],
                    ['DESCRIBE table'],
                    ['SELECT * FROM table'],
                ],
                'index' => 0,
                'expected' => ['SHOW TABLES', 'DESCRIBE table', 'SELECT * FROM table'],
            ],
            'mixed value types' => [
                'input' => [
                    [1, 'active', true],
                    [2, 'inactive', false],
                    [3, 'pending', null],
                ],
                'index' => 1,
                'expected' => ['active', 'inactive', 'pending'],
            ],
            'MySQL SHOW TABLES simulation' => [
                'input' => [
                    ['Tables_in_database' => 'users'],
                    ['Tables_in_database' => 'posts'],
                    ['Tables_in_database' => 'comments'],
                ],
                'index' => 0,
                'expected' => ['users', 'posts', 'comments'],
            ],
            'nested objects at position' => [
                'input' => [
                    ['id' => 1, 'meta' => ['type' => 'user']],
                    ['id' => 2, 'meta' => ['type' => 'admin']],
                    ['id' => 3, 'meta' => ['type' => 'guest']],
                ],
                'index' => 1,
                'expected' => [
                    ['type' => 'user'],
                    ['type' => 'admin'],
                    ['type' => 'guest'],
                ],
            ],
            'associative arrays with numeric position' => [
                'input' => [
                    ['first' => 'John', 'last' => 'Doe', 'role' => 'admin'],
                    ['first' => 'Jane', 'last' => 'Smith', 'role' => 'user'],
                ],
                'index' => 0,
                'expected' => ['John', 'Jane'],
            ],
            'empty rows are skipped' => [
                'input' => [
                    ['John', 'Doe'],
                    [],
                    ['Jane', 'Smith'],
                ],
                'index' => 0,
                'expected' => ['John', 'Jane'],
            ],
        ];
    }

    /**
     * @dataProvider implodeProvider
     */
    public function testImplode($input, $separator, $expected, $shouldThrowException = false): void
    {
        $smartArray = new SmartArray($input);

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a flat array, but got a nested array");
            /** @noinspection UnusedFunctionResultInspection */
            $smartArray->implode($separator);
            return;
        }

        $actual = $smartArray->implode($separator);
        $this->assertSame($expected, $actual);
    }

    public function implodeProvider(): array
    {
        return [
            'empty array'                                   => [
                'input'                => [],
                'separator'            => ', ',
                'expected'             => '',
                'shouldThrowException' => false,
            ],
            'flat array with primitives'                    => [
                'input'                => ['apple', 'banana', 'cherry'],
                'separator'            => ', ',
                'expected'             => 'apple, banana, cherry',
                'shouldThrowException' => false,
            ],
            'implode with comma separator'                  => [
                'input'                => ['apple', 'banana', 'cherry'],
                'separator'            => ', ',
                'expected'             => 'apple, banana, cherry',
                'shouldThrowException' => false,
            ],
            'implode with space separator'                  => [
                'input'                => ['apple', 'banana', 'cherry'],
                'separator'            => ' ',
                'expected'             => 'apple banana cherry',
                'shouldThrowException' => false,
            ],
            'implode with hyphen separator'                 => [
                'input'                => ['apple', 'banana', 'cherry'],
                'separator'            => ' - ',
                'expected'             => 'apple - banana - cherry',
                'shouldThrowException' => false,
            ],
            'implode with special characters'               => [
                'input'                => ['He said "Hello"', "It's a test", 'Line1\nLine2', 'Comma, separated'],
                'separator'            => '; ',
                'expected'             => 'He said "Hello"; It\'s a test; Line1\nLine2; Comma, separated',
                'shouldThrowException' => false,
            ],
            'implode single element array'                  => [
                'input'                => ['onlyOne'],
                'separator'            => ', ',
                'expected'             => 'onlyOne',
                'shouldThrowException' => false,
            ],
            'implode with numeric elements'                 => [
                'input'                => [100, 200.5, 300],
                'separator'            => '-',
                'expected'             => '100-200.5-300',
                'shouldThrowException' => false,
            ],
            'implode nested array (should throw exception)' => [
                'input'                => [
                    ['apple', 'banana'],
                    ['cherry', 'date'],
                ],
                'separator'            => ', ',
                'expected'             => '', // Irrelevant since exception is expected
                'shouldThrowException' => true,
            ],
        ];
    }

    /**
     * @dataProvider mapProvider
     */
    public function testMap($input, $callback, $expected): void
    {
        $smartArray    = new SmartArray($input);
        $originalArray = $smartArray->toArray(); // Copy of the original array

        $mapped = $smartArray->map($callback);

        $this->assertEquals($expected, $mapped->toArray(), "Mapped SmartArray does not match expected output.");
        $this->assertEquals($originalArray, $smartArray->toArray(), "Original SmartArray should remain unmodified.");
    }

    /**
     * @noinspection SpellCheckingInspection // ignore test strings
     */
    public function mapProvider(): array
    {
        return [
            'map empty array with identity callback'                    => [
                'input'    => [],
                'callback' => fn($value) => $value,
                'expected' => [],
            ],
            'map flat array with primitive values to uppercase strings' => [
                'input'    => ['apple', 'banana', 'cherry'],
                'callback' => fn($value) => strtoupper($value),
                'expected' => ['APPLE', 'BANANA', 'CHERRY'],
            ],
            'map flat array with mixed data types'                      => [
                'input'    => [1, 'two', true, null, 5.5],
                'callback' => fn($value) => is_string($value) ? $value . ' mapped' : $value,
                'expected' => [1, 'two mapped', true, null, 5.5],
            ],
            'map keyed array'                                           => [
                'input'    => ['name' => 'Alice', 'age' => 30],
                'callback' => fn($value) => "<td>$value</td>",
                'expected' => ['name' => '<td>Alice</td>', 'age' => '<td>30</td>'],
            ],
            'map nested array adding ageGroup'                          => [
                'input'    => [
                    ['name' => 'Alice', 'age' => 30],
                    ['name' => 'Bob', 'age' => 25],
                    ['name' => 'Charlie', 'age' => 35],
                ],
                'callback' => fn($record) => array_merge($record, ['ageGroup' => $record['age'] >= 30 ? 'Adult' : 'Young']),
                'expected' => [
                    ['name' => 'Alice', 'age' => 30, 'ageGroup' => 'Adult'],
                    ['name' => 'Bob', 'age' => 25, 'ageGroup' => 'Young'],
                    ['name' => 'Charlie', 'age' => 35, 'ageGroup' => 'Adult'],
                ],
            ],
            'map with callback that returns different data types'       => [
                'input'    => ['apple', 'banana', 'cherry'],
                'callback' => fn($value) => strrev($value),
                'expected' => ['elppa', 'ananab', 'yrrehc'],
            ],
            'map with null elements'                                    => [
                'input'    => [null, 'hello', null],
                'callback' => fn($value) => $value ?? 'default',
                'expected' => ['default', 'hello', 'default'],
            ],
        ];
    }

#endregion
#region SmartNull

#endregion
#region Debugging and Help

    public function testToStringTriggersWarning(): void
    {
        $smartArray = new SmartArray([1, 2, 3]);

        $this->expectOutputRegex('/Warning: Can\'t convert SmartArray to string/');
        $result = (string)$smartArray;

        $this->assertSame('', $result);
    }

#endregion
#region Error Handling

    public function testWarnIfMissingOnNonEmptyArrayOutputsWarning(): void
    {
        $smartArray = new SmartArray([
            'id'   => 1,
            'name' => 'Alice',
        ]);

        // Start output buffering to capture the warning output
        ob_start();
        $smartArray->warnIfMissing('age', 'argument'); // 'age' key does not exist
        $output = ob_get_clean();

        // Build the expected warning message pattern
        $expectedWarningPattern = "/Warning: .*'age' doesn't exist .*Valid keys are: id, name/s";

        // Assert that the output contains the expected warning message
        $this->assertMatchesRegularExpression($expectedWarningPattern, $output, "Expected warning message not found in output.");
    }

    public function testWarnIfMissingOnEmptyArrayDoesNotOutputWarning(): void
    {
        $smartArray = new SmartArray([]);

        // Start output buffering to capture any output
        ob_start();
        $smartArray->warnIfMissing('age', 'argument'); // Any key
        $output = ob_get_clean();

        // Assert that there is no warning output
        $this->assertEmpty($output, "No warning should be output when the array is empty.");
    }

#endregion
#region Internal Methods

    /**
     * @dataProvider jsonSerializeProvider
     * @throws JsonException
     */
    public function testJsonSerialize($initialData): void
    {
        // Get the JSON-serialized data from the SmartArray
        $jsonSerialized = json_encode(new SmartArray($initialData), JSON_THROW_ON_ERROR);

        // Get the expected JSON by encoding the initial data
        $expectedJson = json_encode($initialData, JSON_THROW_ON_ERROR);

        // Assert that the JSON-serialized data matches the expected JSON
        $this->assertEquals($expectedJson, $jsonSerialized);
    }

    public function jsonSerializeProvider(): array
    {
        return [
            'Empty array'                        => [
                'initialData' => [],
            ],
            'Flat array of scalars'              => [
                'initialData' => ['a', 'b', 'c'],
            ],
            'Associative array'                  => [
                'initialData' => ['key1' => 'value1', 'key2' => 'value2'],
            ],
            'Nested arrays'                      => [
                'initialData' => [
                    'level1' => [
                        'level2' => [
                            'level3' => 'deep value',
                        ],
                    ],
                ],
            ],
            'Array with special characters'      => [
                'initialData' => ['special' => "Line1\nLine2\tTabbed"],
            ],
            'Array with numeric keys'            => [
                'initialData' => [0 => 'zero', 1 => 'one', 2 => 'two'],
            ],
            'Array with mixed types'             => [
                'initialData' => [
                    'string'    => 'text',
                    'int'       => 42,
                    'float'     => 3.14,
                    'boolTrue'  => true,
                    'boolFalse' => false,
                    'nullValue' => null,
                ],
            ],
            'Array with empty string keys'       => [
                'initialData' => ['' => 'empty key'],
            ],
            'Array with special JSON characters' => [
                'initialData' => ['quote' => '"Double quotes"', 'backslash' => 'Back\\slash'],
            ],
            'Multidimensional array'             => [
                'initialData' => [
                    'users' => [
                        ['id' => 1, 'name' => 'Alice'],
                        ['id' => 2, 'name' => 'Bob'],
                    ],
                    'roles' => ['admin', 'editor', 'subscriber'],
                ],
            ],
            'Array with UTF-8 characters'        => [
                'initialData' => ['greeting' => 'こんにちは', 'emoji' => '😃'],
            ],
            'Array with boolean values'          => [
                'initialData' => ['isActive' => true, 'isDeleted' => false],
            ],
            'Array with null values'             => [
                'initialData' => ['value' => null],
            ],
        ];
    }

#endregion

}
