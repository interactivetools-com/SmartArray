<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use BadMethodCallException, InvalidArgumentException;
use ReflectionException, ReflectionObject;
use PHPUnit\Framework\TestCase;
use ArrayObject;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartNull;
use Itools\SmartString\SmartString;

class SmartArrayTest extends TestCase
{
    //region internal test functions

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
        $objRoot = $obj->root();
        match (true) {
            $weAreRoot && $objRoot !== $obj     => throw new InvalidArgumentException("\$rootArray->root() should reference self, got " . get_debug_type($objRoot) . " instead."),
            $weAreRoot && !is_null($actualRoot) => throw new InvalidArgumentException("No actualRoot should be provided in testplan when \$rootArray->root() is called.  Path: $path"),
            default                             => null,
        };
        if (!$weAreRoot && $objRoot !== $actualRoot) { // child
            $expected = basename(get_debug_type($actualRoot)) . " #" . spl_object_id($actualRoot);
            $actual   = !is_object($objRoot) ? $objRoot : basename(get_debug_type($objRoot)) . " #" . spl_object_id($objRoot);
            throw new InvalidArgumentException("Invalid property at $path->"."root(). Expected $expected, got $actual instead.");
        }

        // Check mysqli metadata
        if (!$weAreRoot && (array) $obj->mysqli() !== (array) $actualRoot->mysqli()) {
            throw new InvalidArgumentException("Invalid mysqli metadata at $path. Expected " . var_export($actualRoot->mysqli(), true) . ", got " . var_export($obj->mysqli(), true) . " instead.");
        }

        // Check useSmartArrays

        $thisObjectUseSmartStrings = $this->callPrivateMethod($obj, 'getProperty', ['useSmartStrings']);
        $actualRootUseSmartStrings = $this->callPrivateMethod($obj->root(), 'getProperty', ['useSmartStrings']);
        if (!$weAreRoot && $thisObjectUseSmartStrings !== $actualRootUseSmartStrings) {
            throw new InvalidArgumentException("Invalid useSmartStrings at $path. Expected " . var_export($actualRootUseSmartStrings, true) . ", got " . var_export($thisObjectUseSmartStrings, true) . " instead.");
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
     * We use this to compare expected output with what SmartArray::new() should return when useSmartStrings is enabled
     */
    public static function toArrayResolveSS(mixed $obj): array
    {
        // Use getIterator to convert everything to SmartStrings
        $array = [];
        foreach ($obj->getIterator() as $key => $value) {  // getIterator|foreach converts everything to SmartStrings when they are enabled
            $array[$key] = self::normalizeSS($value);
        }

        return $array;

    }

    /**
     * Html encode all strings in an array structure
     * We use this to encode our test data to match what SmartArray with SmartStrings should return
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
     * returned by SmartArray::new()->withSmartStrings() - includes SmartStrings but not scalar|null
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

    /**
     * Calls a private or protected method on an object or a static method on a class.
     *
     * @param object|string $objectOrClass The object instance or class name for static methods.
     * @param string $methodName The name of the method to call.
     * @param array $args An array of arguments to pass to the method.
     * @return mixed The result of the method call.
     *
     * @throws ReflectionException
     * @usage $this->callPrivateMethod($object, 'methodName', [$arg1, $arg2, ...]);
     */
    private function callPrivateMethod(object|string $objectOrClass, string $methodName, array $args = []): mixed {
        // Determine whether we're dealing with an object instance or a class name for static methods
        $reflection = new ReflectionObject($objectOrClass);

        // Check if the method exists
        if ($reflection->hasMethod($methodName)) {
            $method = $reflection->getMethod($methodName);

            // Make the method accessible if it's not public
            if (!$method->isPublic()) {
                $method->setAccessible(true);
            }

            // Invoke the method based on whether it's static or not
            return $method->invokeArgs($objectOrClass, $args);
        }

        // Attempt to use magic methods if the target method doesn't exist
        if ($reflection->hasMethod('__call')) {
            $methodInstance = $reflection->getMethod('__call');
            if (!$methodInstance->isPublic()) {
                $methodInstance->setAccessible(true);
            }

            return $methodInstance->invoke($objectOrClass, $methodName, $args);
        }

        // Throw an exception if the method doesn't exist
        throw new BadMethodCallException("Method $methodName does not exist.");
    }

    //endregion
    //region new SmartArray()

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
            'using test records'         => TestHelpers::getTestRecords(),
        ]];
    }

//endregion
//region SmartStrings Configuration

    public function smartStringsConfigProvider(): array
    {
        $testArray = TestHelpers::getTestRecords();
        return [
            'Regular array' => [$testArray],
            'Nested arrays' => [
                [
                    'users' => [
                        ['name' => "John O'Connor", 'city' => 'New York'],
                        ['name' => 'Tom & Jerry', 'city' => 'Vancouver']
                    ],
                    'settings' => ['theme' => 'dark', 'notifications' => true]
                ]
            ],
        ];
    }

    /**
     * @dataProvider smartStringsConfigProvider
     */
    public function testWithSmartStringsNewCopy($inputArray): void
    {
        $original = SmartArray::new($inputArray);
        $this->assertFalse($original->usingSmartStrings());

        // Get new copy with SmartStrings enabled
        $copy = $original->enableSmartStrings(true);

        // Verify original is unchanged
        $this->assertFalse($original->usingSmartStrings());

        // Verify copy has SmartStrings enabled
        $this->assertTrue($copy->usingSmartStrings());

        // Verify they have the same data but different instances
        $this->assertEquals($original->toArray(), $copy->toArray());
        $this->assertNotSame($original, $copy);
    }

    /**
     * @dataProvider smartStringsConfigProvider
     */
    public function testNoSmartStringsNewCopy($inputArray): void
    {
        $original = SmartArray::new($inputArray, true);
        $this->assertTrue($original->usingSmartStrings());

        // Get new copy with SmartStrings disabled
        $copy = $original->disableSmartStrings(true);

        // Verify original is unchanged
        $this->assertTrue($original->usingSmartStrings());

        // Verify copy has SmartStrings disabled
        $this->assertFalse($copy->usingSmartStrings());

        // Verify they have the same data but different instances
        $this->assertEquals($original->toArray(), $copy->toArray());
        $this->assertNotSame($original, $copy);
    }

    /**
     * @dataProvider smartStringsConfigProvider
     */
    public function testWithSmartStringsModifyInPlace($inputArray): void
    {
        $original = SmartArray::new($inputArray);
        $this->assertFalse($original->usingSmartStrings());

        // Modify in place (default behavior)
        $result = $original->enableSmartStrings();

        // Verify original is modified
        $this->assertTrue($original->usingSmartStrings());

        // Verify result is the same instance
        $this->assertSame($original, $result);
    }

    /**
     * @dataProvider smartStringsConfigProvider
     */
    public function testNoSmartStringsModifyInPlace($inputArray): void
    {
        $original = SmartArray::new($inputArray, true);
        $this->assertTrue($original->usingSmartStrings());

        // Modify in place (default behavior)
        $result = $original->disableSmartStrings();

        // Verify original is modified
        $this->assertFalse($original->usingSmartStrings());

        // Verify result is the same instance
        $this->assertSame($original, $result);
    }

    /**
     * @dataProvider smartStringsConfigProvider
     */
    public function testUsingSmartStrings($inputArray): void
    {
        $withoutSS = SmartArray::new($inputArray);
        $this->assertFalse($withoutSS->usingSmartStrings());

        $withSS = SmartArray::new($inputArray, true);
        $this->assertTrue($withSS->usingSmartStrings());

        // Test toggling
        $withoutSS->enableSmartStrings();
        $this->assertTrue($withoutSS->usingSmartStrings());

        $withSS->disableSmartStrings();
        $this->assertFalse($withSS->usingSmartStrings());
    }

//endregion
//region Database Operations

//endregion
//region SmartNull

//endregion
//region Debugging and Help

    public function testToStringTriggersWarning(): void
    {
        $smartArray = new SmartArray([1, 2, 3]);

        $this->expectOutputRegex('/Warning: Can\'t convert SmartArray to string/');
        $result = (string)$smartArray;

        $this->assertSame('', $result);
    }

    public function testHelp(): void
    {
        $smartArray = new SmartArray([1, 2, 3]);

        // Start output buffering to capture the help output
        ob_start();
        $smartArray->help();
        $output = ob_get_clean();

        // Verify help text contains useful information
        $this->assertStringContainsString('<xmp>', $output, "Help output should be wrapped in <xmp> tags");
        $this->assertStringContainsString('SmartArray:', $output, "Help output should contain introductory text");
        $this->assertStringContainsString('contains(value)', $output, "Help output should mention contains() method");
        $this->assertStringContainsString('smartMap', $output, "Help output should mention smartMap() method");
        $this->assertStringContainsString('each', $output, "Help output should mention each() method");
        $this->assertStringContainsString('pluck', $output, "Help output should mention pluck() method");
    }

//endregion
//region Error Handling

    public function testWarnIfMissingOnNonEmptyArrayOutputsWarning(): void
    {
        $smartArray = new SmartArray([
            'id'   => 1,
            'name' => 'Alice',
        ]);

        // Start output buffering to capture the warning output
        ob_start();
        $this->callPrivateMethod($smartArray, 'warnIfMissing', ['age', 'argument']); // 'age' key does not exist
        $output = ob_get_clean();

        // Build the expected warning message pattern
        $expectedWarningPattern = "/Warning: .*'age' doesn't exist/s";

        // Assert that the output contains the expected warning message
        $this->assertMatchesRegularExpression($expectedWarningPattern, $output, "Expected warning message not found in output.");
    }

    public function testWarnIfMissingOnEmptyArrayDoesNotOutputWarning(): void
    {
        $smartArray = new SmartArray([]);

        // Start output buffering to capture any output
        ob_start();
        $this->callPrivateMethod($smartArray, 'warnIfMissing', ['age', 'argument']); // Any key
        $output = ob_get_clean();

        // Assert that there is no warning output
        $this->assertEmpty($output, "No warning should be output when the array is empty.");
    }

    public function testWarnIfMissingOffsetWithGlobalSettingDisabled(): void
    {
        $smartArray = new SmartArray([
            'id'   => 1,
            'name' => 'Alice',
        ]);

        // Store original setting
        $originalWarnIfMissing = SmartArray::$warnIfMissing;

        try {
            // Disable warnings globally
            SmartArray::$warnIfMissing = false;

            // For offset warnings, the global setting should prevent warnings
            ob_start();
            $this->callPrivateMethod($smartArray, 'warnIfMissing', ['age', 'offset']);
            $output = ob_get_clean();

            // Assert that no warning is shown when globally disabled
            $this->assertEmpty($output, "No warning should be output for offset access when warnIfMissing is disabled");
        } finally {
            // Restore original setting
            SmartArray::$warnIfMissing = $originalWarnIfMissing;
        }
    }

    public function testWarnIfMissingArgumentWithGlobalSettingDisabled(): void
    {
        $smartArray = new SmartArray([
            'id'   => 1,
            'name' => 'Alice',
        ]);

        // Store original setting
        $originalWarnIfMissing = SmartArray::$warnIfMissing;

        try {
            // Disable warnings globally
            SmartArray::$warnIfMissing = false;

            // Method argument warnings should still show even with the global setting disabled
            ob_start();
            $this->callPrivateMethod($smartArray, 'warnIfMissing', ['age', 'argument']);
            $output = ob_get_clean();

            // Assert that the warning is still shown despite the global setting
            $expectedWarningPattern = "/Warning: .*'age' doesn't exist/s";
            $this->assertMatchesRegularExpression($expectedWarningPattern, $output,
                "Method argument warnings should still be shown even with warnIfMissing disabled");
        } finally {
            // Restore original setting
            SmartArray::$warnIfMissing = $originalWarnIfMissing;
        }
    }

//endregion
//region Internal Methods

    /**
     * @dataProvider jsonSerializeProvider
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

//endregion

}
