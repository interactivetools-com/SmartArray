<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartNull;
use Itools\SmartArray\DebugInfo;
use Itools\SmartString\SmartString;

use PHPUnit\Framework\TestCase;
use ReflectionObject;

class SmartArrayTest extends TestCase
{
    #region internal test data

    public function getTestRecords(): array
    {
        return [
            [
                'html'    => "<img alt='\"'>",
                'int'     => 7,
                'float'   => 5.7,
                'string'  => '&nbsp;',
                'bool'    => true,
                'null'    => null,
                'isFirst' => 'C',
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

    // test record data is what we think it is
    public function testTestRecords(): void
    {
        $expected = <<<'__TEXT__'
            array(
                array(
                    html    => '<img alt=\'"\'>',
                    int     => 7,
                    float   => 5.7,
                    string  => '&nbsp;',
                    bool    => true,
                    null    => NULL,
                    isFirst => 'C',
                ),
                array(
                    html    => '<p>"It\'s"</p>',
                    int     => 0,
                    float   => 1.23,
                    string  => '"green"',
                    bool    => false,
                    null    => NULL,
                    isFirst => 'Q',
                ),
                array(
                    html    => '<hr class=\'line\'>',
                    int     => 1,
                    float   => -16.7,
                    string  => '<blue>',
                    bool    => false,
                    null    => NULL,
                    isFirst => 'K',
                ),
            ),
            __TEXT__;
        $actual   = rtrim(self::compactVarExport($this->getTestRecords()));
        $this->assertSame($expected, $actual);
    }

    // test record data is what we think it is
    public function testTestRecord(): void
    {
        $expected = <<<'__TEXT__'
            array(
                html    => '<p>"It\'s"</p>',
                int     => 0,
                float   => 1.23,
                string  => '"green"',
                bool    => false,
                null    => NULL,
                isFirst => 'Q',
            ),
            __TEXT__;
        $actual   = rtrim(self::compactVarExport($this->getTestRecord()));
        $this->assertSame($expected, $actual);
    }

    public static function compactVarExport($var, int $indent = 0): string
    {
        $indentIncrement = 4;
        $padding         = str_repeat(" ", $indent);
        $typeOrClass     = basename(get_debug_type($var));
        $output          = "";

        if ($var instanceof SmartArray) {
            // get properties
            $properties = [];
            $reflection = new ReflectionObject($var);
            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);
                if ($property->getName() !== 'parent') { // exclude parent reference
                    $properties[$property->getName()] = $property->getValue($var);
                }
            }
            $propertiesCSV = implode(", ", array_map(function ($key, $value) {
                $varExport = match (true) {
                    is_array($value) => "[" . implode(", ", array_map(static fn($k, $v) => "$k => " . var_export($v, true), array_keys($value), $value)) . "]",
                    default          => var_export($value, true),
                };
                return "$key => $varExport";
            }, array_keys($properties), array_values($properties)));
            $propertiesCSV = str_replace("true, ", "true,  ", $propertiesCSV); // align true/false values for better readability

            // build output
            $output = sprintf("%-19s// Properties: $propertiesCSV\n", "$padding$typeOrClass([");
            if ($var->isNotEmpty()) {
                $maxKeyLength = max(array_map('strlen', array_keys($var->toArray())));
                $arrayIsList  = array_keys($var->toArray()) === range(0, count($var) - 1); // check if array is a list, e.g. keys === [0, 1, 2, 3, ...]
                foreach ($var as $key => $value) {
                    if (!$arrayIsList) {
                        $keyPadding = $padding . str_repeat(" ", $indentIncrement);
                        $output     .= sprintf("$keyPadding%-{$maxKeyLength}s => ", $key);
                    }
                    $output .= self::compactVarExport($value, $indent + $indentIncrement);
                }
            }
            $output .= "$padding]),\n";
        } elseif ($typeOrClass === 'array') {
            $output      = "$padding$typeOrClass(\n";
            $arrayIsList = array_keys($var) === range(0, count($var) - 1); // check if array is a list, e.g. keys === [0, 1, 2, 3, ...]
            if (count($var)) {
                $maxKeyLength = max(array_map('strlen', array_keys($var)));
                foreach ($var as $key => $value) {
                    if (!$arrayIsList) {
                        $keyPadding = $padding . str_repeat(" ", $indentIncrement);
                        $output     .= sprintf("$keyPadding%-{$maxKeyLength}s => ", $key);
                    }
                    $output .= self::compactVarExport($value, $indent + $indentIncrement);
                }
            }
            $output .= "$padding),\n";
        } elseif ($var instanceof SmartString) {
            $output = "SmartString(" . var_export($var->value(), true) . ")\n";
        } elseif ($var instanceof SmartNull) {
            $output = "SmartNull()\n";
        } else {
            $output = var_export($var, true) . ",\n";
        }

        return $output;
    }

    #endregion
    #region new SmartArray()

    public function testConstructorWithNestedArray(): void
    {
        $object   = new SmartArray($this->getTestRecords());
        $expected = <<<'__TEXT__'
            SmartArray([       // Properties: isFirst => false, isLast => false, position => 0, metadata => []
                SmartArray([   // Properties: isFirst => true,  isLast => false, position => 1, metadata => []
                    html    => '<img alt=\'"\'>',
                    int     => 7,
                    float   => 5.7,
                    string  => '&nbsp;',
                    bool    => true,
                    null    => NULL,
                    isFirst => 'C',
                ]),
                SmartArray([   // Properties: isFirst => false, isLast => false, position => 2, metadata => []
                    html    => '<p>"It\'s"</p>',
                    int     => 0,
                    float   => 1.23,
                    string  => '"green"',
                    bool    => false,
                    null    => NULL,
                    isFirst => 'Q',
                ]),
                SmartArray([   // Properties: isFirst => false, isLast => true,  position => 3, metadata => []
                    html    => '<hr class=\'line\'>',
                    int     => 1,
                    float   => -16.7,
                    string  => '<blue>',
                    bool    => false,
                    null    => NULL,
                    isFirst => 'K',
                ]),
            ]),
            __TEXT__;

        $actual = rtrim(self::compactVarExport($object));
        $this->assertSame($expected, $actual);
    }

    public function testConstructorWithFlatArray(): void
    {
        $object   = new SmartArray($this->getTestRecord());
        $expected = <<<'__TEXT__'
            SmartArray([       // Properties: isFirst => false, isLast => false, position => 0, metadata => []
                html    => '<p>"It\'s"</p>',
                int     => 0,
                float   => 1.23,
                string  => '"green"',
                bool    => false,
                null    => NULL,
                isFirst => 'Q',
            ]),
            __TEXT__;

        $actual = rtrim(self::compactVarExport($object));
        $this->assertSame($expected, $actual);
    }

    public function testConstructorWithEmptyArray(): void
    {
        $object   = new SmartArray();
        $expected = <<<'__TEXT__'
            SmartArray([       // Properties: isFirst => false, isLast => false, position => 0, metadata => []
            ]),
            __TEXT__;
        $actual   = rtrim(self::compactVarExport($object));
        $this->assertSame($expected, $actual);
    }

#endregion
#region Conversion

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
     * @dataProvider jsonSerializeProvider
     * @throws \JsonException
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
#region Array Information

    /**
     * @dataProvider countProvider
     */
    public function testCountMethod($input, int $expected): void
    {
        $object = new SmartArray($input);
        $this->assertSame($expected, $object->count());
    }

    /**
     * @dataProvider countProvider
     * @noinspection PhpUnitTestsInspection
     */
    public function testCountFunction($input, int $expected): void
    {
        $object = new SmartArray($input);
        $this->assertSame($expected, count($object));
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
        $object = new SmartArray($input);
        match ($expected) {
            true  => $this->assertTrue($object->isEmpty()),
            false => $this->assertFalse($object->isEmpty()),
        };
    }

    /**
     * @dataProvider isEmptyProvider
     */
    public function testIsNotEmpty($input, bool $expected): void
    {
        $object = new SmartArray($input);
        match (!$expected) {
            true  => $this->assertTrue($object->isNotEmpty()),
            false => $this->assertFalse($object->isNotEmpty()),
        };
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
            $this->assertSame($isLastExpected, $value->isLast(), "Element at position $key: 'isLast()' mismatch." . self::compactVarExport($value));

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
    /**
     * @dataProvider positionProvider
     */
    public function testPosition($initialData, $expectedPositions): void
    {
        // Set up the initial data
        $array = new SmartArray($initialData);

        $actualPositions = [];
        foreach ($array as $key => $element) {
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
        foreach ($array as $key => $element) {
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

#endregion
#region Value Access

    /**
     * @dataProvider getProvider
     */
    public function testGet($initialData, $key, $expected): void
    {
        // Set up the initial data
        $array = new SmartArray($initialData);

        // Perform the get operation
        ob_start(); // capture any warnings output by SmartArray::get()
        $result = $array->get($key);
        ob_end_clean(); // discard any warnings output by SmartArray::get()

        // Get the actual result (convert SmartString or SmartArray to their underlying values)
        $actual = match (true) {
            $result instanceof SmartString => $result->value(),
            $result instanceof SmartArray  => $result->toArray(),
            $result instanceof SmartNull   => null,
            default                        => __FUNCTION__ . "() Unexpected value type: " . get_debug_type($result),
        };

        // Assert that the actual result matches the expected result
        $this->assertEquals($expected, $actual);
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
        $array = new SmartArray($initialData);

        // Get the first element
        $result = $array->first();

        // Get the actual result (convert SmartString or SmartArray to their underlying values)
        $actual = match (true) {
            $result instanceof SmartString => $result->value(),
            $result instanceof SmartArray  => $result->toArray(),
            $result instanceof SmartNull   => null,
            default                        => __FUNCTION__ . "() Unexpected value type: " . get_debug_type($result),
        };

        // Assert that the actual result matches the expected result
        $this->assertEquals($expected, $actual);
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
        $array = new SmartArray($initialData);

        // Get the last element
        $result = $array->last();

        // Get the actual result (convert SmartString or SmartArray to their underlying values)
        $actual = match (true) {
            $result instanceof SmartString => $result->value(),
            $result instanceof SmartArray  => $result->toArray(),
            $result instanceof SmartNull   => null,
            default                        => __FUNCTION__ . "() Unexpected value type: " . get_debug_type($result),
        };

        // Assert that the actual result matches the expected result
        $this->assertEquals($expected, $actual);
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
        $array = new SmartArray($initialData);

        // Perform the nth operation using the provided index
        $result = $array->nth($index);

        // Get the actual result (convert SmartString or SmartArray to their underlying values)
        $actual = match (true) {
            $result instanceof SmartString => $result->value(),
            $result instanceof SmartArray  => $result->toArray(),
            $result instanceof SmartNull   => null,
            default                        => __FUNCTION__ . "() Unexpected value type: " . get_debug_type($result),
        };

        // Assert that the actual result matches the expected result
        $this->assertEquals($expected, $actual);
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

    /**
     * @dataProvider offsetGetProvider
     */
    public function testOffsetGet($initialData, $get, $expected): void
    {
        // Set up the initial data
        $array = new SmartArray($initialData);

        // Perform the get operation using the provided closure
        ob_start(); // capture any warnings output by SmartArray::offsetGet()
        $result = $get($array);
        ob_end_clean(); // discard any warnings output by SmartArray::offsetGet()

        // Get the actual result (convert SmartString or SmartArray to their underlying values)
        $actual = match (true) {
            $result instanceof SmartString => $result->value(),
            $result instanceof SmartArray  => $result->toArray(),
            $result instanceof SmartNull   => null,
            default                        => __FUNCTION__ . "() Unexpected value type: " . get_debug_type($result),
        };

        // Assert that the actual result matches the expected result
        $this->assertEquals($expected, $actual);
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
        // set up the initial data
        $array = new SmartArray($initialData);

        // Perform the operation using the provided closure
        $set($array);

        // Get the actual result after the set operation
        $actual = $array->toArray();

        // Assert that the actual result matches the expected result
        $this->assertEquals($expected, $actual);
    }

    public function offsetSetProvider(): array
    {
        return [
            'Set scalar value with array syntax'        => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array['name'] = 'Alice';
                },
                'expected'    => [
                    'name' => 'Alice',
                ],
            ],
            'Set scalar value with property syntax'     => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array->age = 30;
                },
                'expected'    => [
                    'age' => 30,
                ],
            ],
            'Set array value with array syntax'         => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array['address'] = ['city' => 'New York', 'zip' => '10001'];
                },
                'expected'    => [
                    'address' => ['city' => 'New York', 'zip' => '10001'],
                ],
            ],
            'Set array value with property syntax'      => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array->contact = ['email' => 'alice@example.com', 'phone' => '123-456-7890'];
                },
                'expected'    => [
                    'contact' => ['email' => 'alice@example.com', 'phone' => '123-456-7890'],
                ],
            ],
            'Set null key (append) with scalar value'   => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array[] = 'appended value';
                },
                'expected'    => [
                    'appended value',
                ],
            ],
            'Set null key (append) with array value'    => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array[] = ['item1', 'item2'];
                },
                'expected'    => [
                    ['item1', 'item2'],
                ],
            ],
            'Overwrite existing key'                    => [
                'initialData' => ['key1' => 'initial'],
                'set'         => function (&$array) {
                    $array['key1'] = 'overwritten';
                },
                'expected'    => [
                    'key1' => 'overwritten',
                ],
            ],
            'Set integer key'                           => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array[42] = 'answer';
                },
                'expected'    => [
                    42 => 'answer',
                ],
            ],
            'Set float value'                           => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array->pi = 3.14;
                },
                'expected'    => [
                    'pi' => 3.14,
                ],
            ],
            'Set boolean value'                         => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array->isActive = true;
                },
                'expected'    => [
                    'isActive' => true,
                ],
            ],
            'Set null value'                            => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array->nothing = null;
                },
                'expected'    => [
                    'nothing' => null,
                ],
            ],
            'Set value using numeric string key'        => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array['123'] = 'numeric string key';
                },
                'expected'    => [
                    '123' => 'numeric string key',
                ],
            ],
            'Set value using special characters in key' => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array['key-with-dash'] = 'special key';
                },
                'expected'    => [
                    'key-with-dash' => 'special key',
                ],
            ],
            'Set value with empty string key'           => [
                'initialData' => [],
                'set'         => function (&$array) {
                    $array[''] = 'empty key';
                },
                'expected'    => [
                    '' => 'empty key',
                ],
            ],
            'Set value with overwriting different type' => [
                'initialData' => ['data' => ['initial' => 'array']],
                'set'         => function (&$array) {
                    $array->data = 'now a string';
                },
                'expected'    => [
                    'data' => 'now a string',
                ],
            ],
        ];
    }

#endregion
#region Array Transformation

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
     * @dataProvider implodeProvider
     */
    public function testImplode($input, $separator, $expected, $shouldThrowException = false): void
    {
        $smartArray = new SmartArray($input);

        if ($shouldThrowException) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Expected a flat array, but got a nested array");
            $smartArray->implode($separator);
            return;
        }

        $joined              = $smartArray->implode($separator);
        $expectedSmartString = new SmartString($expected);
        $this->assertSame($expectedSmartString->value(), $joined->value());
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
        ob_get_clean();

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

        $this->expectException(\InvalidArgumentException::class);
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
#region Debugging and Help

    /**
     * @dataProvider debugInfoProvider
     */
    public function testDebugInfo($input, $expected): void
    {
        // actual
        ob_start();
        print_r(new SmartArray($input));
        $actual = rtrim(ob_get_clean());

        // compare
        $this->assertSame($expected, $actual);
    }

    public function debugInfoProvider(): array
    {
        return [
            'empty array'                => [
                'input'    => [],
                'expected' => <<<'__TEXT__'
                                  Itools\SmartArray\SmartArray Object
                                  (
                                      [__DEBUG_INFO__] => // Values are SmartArrays and SmartStrings unless specified otherwise, call $var->help() for inline help
                                  
                                      [
                                      ],
                                  )
                                  __TEXT__
                ,
            ],
            'flat array with primitives' => [
                'input'    => [11, 'string', true, null, 3.14],
                'expected' => <<<'__TEXT__'
                                  Itools\SmartArray\SmartArray Object
                                  (
                                      [__DEBUG_INFO__] => // Values are SmartArrays and SmartStrings unless specified otherwise, call $var->help() for inline help
                                  
                                      [
                                          [0] => 11,
                                          [1] => 'string',
                                          [2] => true,
                                          [3] => NULL,
                                          [4] => 3.14,
                                      ],
                                  )
                                  __TEXT__,
            ],

            'nested array' => [
                'input'    => [
                    ['name' => 'John', 'city' => 'New York', 'color' => 'blue'],
                    ['name' => 'Jane', 'city' => 'Los Angeles', 'color' => 'red'],
                    ['name' => 'Jack', 'city' => 'Chicago', 'color' => 'green'],
                    ['name' => 'Jill', 'city' => 'Miami', 'color' => 'green'],
                ],
                'expected' => <<<'__TEXT__'
                                  Itools\SmartArray\SmartArray Object
                                  (
                                      [__DEBUG_INFO__] => // Values are SmartArrays and SmartStrings unless specified otherwise, call $var->help() for inline help
                                  
                                      [
                                          [0] => [
                                              'name'  => 'John',
                                              'city'  => 'New York',
                                              'color' => 'blue',
                                          ],
                                          [1] => [
                                              'name'  => 'Jane',
                                              'city'  => 'Los Angeles',
                                              'color' => 'red',
                                          ],
                                          [2] => [
                                              'name'  => 'Jack',
                                              'city'  => 'Chicago',
                                              'color' => 'green',
                                          ],
                                          [3] => [
                                              'name'  => 'Jill',
                                              'city'  => 'Miami',
                                              'color' => 'green',
                                          ],
                                      ],
                                  )
                                  __TEXT__
                ,
            ],

            'quad-nested array' => [
                'input'    => [
                    'North America' => [
                        'Canada'        => [
                            'British Columbia' => ['Vancouver', 'Victoria', 'Kelowna', 'Nanaimo'],
                            'Alberta'          => ['Calgary', 'Edmonton', 'Red Deer'],
                        ],
                        'United States' => [
                            'California' => ['Los Angeles', 'San Francisco', 'San Diego'],
                            'New York'   => ['New York City', 'Buffalo', 'Rochester'],
                            'Florida'    => ['Miami', 'Orlando', 'Tampa'],
                        ],
                    ],
                ],
                'expected' => <<<'__TEXT__'
                                  Itools\SmartArray\SmartArray Object
                                  (
                                      [__DEBUG_INFO__] => // Values are SmartArrays and SmartStrings unless specified otherwise, call $var->help() for inline help
                                  
                                      [
                                          'North America' => [
                                              'Canada'        => [
                                                  'British Columbia' => [
                                                      [0] => 'Vancouver',
                                                      [1] => 'Victoria',
                                                      [2] => 'Kelowna',
                                                      [3] => 'Nanaimo',
                                                  ],
                                                  'Alberta'          => [
                                                      [0] => 'Calgary',
                                                      [1] => 'Edmonton',
                                                      [2] => 'Red Deer',
                                                  ],
                                              ],
                                              'United States' => [
                                                  'California' => [
                                                      [0] => 'Los Angeles',
                                                      [1] => 'San Francisco',
                                                      [2] => 'San Diego',
                                                  ],
                                                  'New York'   => [
                                                      [0] => 'New York City',
                                                      [1] => 'Buffalo',
                                                      [2] => 'Rochester',
                                                  ],
                                                  'Florida'    => [
                                                      [0] => 'Miami',
                                                      [1] => 'Orlando',
                                                      [2] => 'Tampa',
                                                  ],
                                              ],
                                          ],
                                      ],
                                  )
                                  __TEXT__
                ,
            ],
        ];
    }

    public function testToStringTriggersWarning(): void
    {
        $smartArray = new SmartArray([1, 2, 3]);

        $this->expectOutputRegex('/Warning: Can\'t convert SmartArray to string/');
        $result = (string)$smartArray;

        $this->assertSame('', $result);
    }

    public function testHelpOutputsCorrectInformation(): void
    {
        // expected
        $expected = DebugInfo::help();

        // actual
        $smartArray = new SmartArray();
        ob_start();
        $smartArray->help();
        $actual = ob_get_clean();

        // compare
        $this->assertSame($expected, $actual);
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

}
