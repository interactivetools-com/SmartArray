<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use PHPUnit\Framework\TestCase;
use Itools\SmartArray\SmartArray;

class GlobalSettingsTest extends TestCase
{
    private bool $originalWarnIfMissing;
    private bool $originalLogDeprecations;

    protected function setUp(): void
    {
        // Store original global settings before each test
        $this->originalWarnIfMissing = SmartArray::$warnIfMissing;
        $this->originalLogDeprecations = SmartArray::$logDeprecations;
    }

    protected function tearDown(): void
    {
        // Restore original global settings after each test
        SmartArray::$warnIfMissing = $this->originalWarnIfMissing;
        SmartArray::$logDeprecations = $this->originalLogDeprecations;
    }

    public function testWarnIfMissingGlobalSettingDefault(): void
    {
        // Verify the default value is true
        $this->assertTrue(SmartArray::$warnIfMissing, "warnIfMissing should be enabled by default");
    }

    public function testLogDeprecationsGlobalSettingDefault(): void
    {
        // Verify the default value is false
        $this->assertFalse(SmartArray::$logDeprecations);
    }

    public function testWarnIfMissingGlobalSettingCanBeChanged(): void
    {
        // Change the setting
        SmartArray::$warnIfMissing = false;

        // Verify it changed
        $this->assertFalse(SmartArray::$warnIfMissing);

        // Change back and verify
        SmartArray::$warnIfMissing = true;
        $this->assertTrue(SmartArray::$warnIfMissing);
    }

    public function testLogDeprecationsGlobalSettingCanBeChanged(): void
    {
        // Change the setting
        SmartArray::$logDeprecations = true;

        // Verify it changed
        $this->assertTrue(SmartArray::$logDeprecations);

        // Change back and verify
        SmartArray::$logDeprecations = false;
        $this->assertFalse(SmartArray::$logDeprecations);
    }

    public function testWarnIfMissingDisablesWarningsForNonexistentProperties(): void
    {
        $array = new SmartArray(['name' => 'Alice']);

        // With warnings enabled (default)
        SmartArray::$warnIfMissing = true;
        ob_start();
        $value = $array->age; // Access nonexistent property
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        $this->assertStringContainsString(" age ", $output);

        // With warnings disabled
        SmartArray::$warnIfMissing = false;
        ob_start();
        $value = $array->age; // Access nonexistent property
        $output = ob_get_clean();
        $this->assertEmpty($output);
    }

    public function testWarnIfMissingDisablesWarningsForArrayAccess(): void
    {
        $array = new SmartArray(['name' => 'Alice']);

        // With warnings enabled (default)
        SmartArray::$warnIfMissing = true;
        ob_start();
        $value = $array['age']; // Access nonexistent key
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        $this->assertStringContainsString(" age ", $output);

        // With warnings disabled
        SmartArray::$warnIfMissing = false;
        ob_start();
        $value = $array['age']; // Access nonexistent key
        $output = ob_get_clean();
        $this->assertEmpty($output);
    }

    public function testWarnIfMissingAlwaysShowsWarningsForMethodArguments(): void
    {
        // Create a nested array structure to test with
        $array = new SmartArray([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        // Save original value
        $warnIfMissingOriginal = SmartArray::$warnIfMissing;

        // With warnings enabled (default)
        SmartArray::$warnIfMissing = true;
        ob_start();
        $plucked = $array->pluck('nonexistent_column'); // pluck() uses warnIfMissing with 'argument' type
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        $this->assertStringContainsString("'nonexistent_column'", $output);

        // With warnings disabled - method argument warnings are still shown
        SmartArray::$warnIfMissing = false;
        ob_start();
        $plucked = $array->pluck('nonexistent_column'); // pluck() uses warnIfMissing with 'argument' type
        $output = ob_get_clean();
        $this->assertNotEmpty($output, "Failed asserting that method argument warning is not empty, got: " . var_export($output, true));
        $this->assertStringContainsString("'nonexistent_column'", $output);

        // Restore original value
        SmartArray::$warnIfMissing = $warnIfMissingOriginal;
    }

    public function testWarnIfMissingAlwaysShowsWarningsForIndexByMethodArguments(): void
    {
        // Create a nested array structure to test with
        $array = new SmartArray([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        // Save original value
        $warnIfMissingOriginal = SmartArray::$warnIfMissing;

        try {
            // With warnings disabled - method argument warnings should still show for indexBy method
            SmartArray::$warnIfMissing = false;

            ob_start();
            $indexedArray = $array->indexBy('nonexistent_column'); // indexBy() uses warnIfMissing with 'argument' type
            $output = ob_get_clean();

            // Assert that warning is still shown despite global setting being disabled
            $this->assertNotEmpty($output, "Method argument warnings should still be shown for indexBy() even with warnIfMissing disabled");
            $this->assertStringContainsString("'nonexistent_column'", $output);
        } finally {
            // Restore original value
            SmartArray::$warnIfMissing = $warnIfMissingOriginal;
        }
    }

    public function testLogDeprecationsControlsDeprecationNotices(): void
    {
        $array = new SmartArray(['name' => 'Alice']);

        // With deprecation logging enabled
        SmartArray::$logDeprecations = true;

        // Use reflection to call the private method directly
        $reflectionClass = new \ReflectionClass(SmartArray::class);
        $method = $reflectionClass->getMethod('logDeprecation');
        $method->setAccessible(true);

        // We need to capture the error using set_error_handler because @ suppresses output
        $deprecationCaught = false;
        set_error_handler(function ($errno, $errstr) use (&$deprecationCaught) {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationCaught = true;
            }
            return true;
        });

        $method->invokeArgs(null, ['This is a test deprecation message']);
        restore_error_handler();

        $this->assertTrue($deprecationCaught, "Deprecation notice should be triggered when logging is enabled");

        // With deprecation logging disabled
        SmartArray::$logDeprecations = false;

        $deprecationCaught = false;
        set_error_handler(function ($errno, $errstr) use (&$deprecationCaught) {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationCaught = true;
            }
            return true;
        });

        $method->invokeArgs(null, ['This is a test deprecation message']);
        restore_error_handler();

        $this->assertFalse($deprecationCaught, "Deprecation notice should not be triggered when logging is disabled");
    }

    public function testWarnIfMissingWithAllArrayMethodsThatUseIt(): void
    {
        $users = new SmartArray([
            ['id' => 1, 'name' => 'Alice', 'city' => 'New York'],
            ['id' => 2, 'name' => 'Bob', 'city' => 'Boston'],
            ['id' => 3, 'name' => 'Charlie', 'city' => 'Chicago']
        ]);

        // Methods to test (these all use warnIfMissing internally with 'argument' type)
        $methodTests = [
            // Method name => [arguments for nonexistent column]
            'indexBy' => ['nonexistent_column'],
            'pluck' => ['nonexistent_column'],
            'groupBy' => ['nonexistent_column'],
        ];

        // Test with warnings enabled
        SmartArray::$warnIfMissing = true;

        foreach ($methodTests as $method => $args) {
            ob_start();
            try {
                $users->$method(...$args);
            } catch (\Exception $e) {
                // Some methods might throw exceptions, that's fine
            }
            $output = ob_get_clean();
            $this->assertNotEmpty($output, "Method $method should warn when warnIfMissing is true");
            $this->assertStringContainsString("nonexistent_column", $output);
        }

        // Test with warnings disabled - method argument warnings should still show
        SmartArray::$warnIfMissing = false;

        foreach ($methodTests as $method => $args) {
            ob_start();
            try {
                $users->$method(...$args);
            } catch (\Exception $e) {
                // Some methods might throw exceptions, that's fine
            }
            $output = ob_get_clean();
            $this->assertNotEmpty($output, "Method $method should still warn for method arguments even when warnIfMissing is false");
            $this->assertStringContainsString("nonexistent_column", $output);
        }
    }

    public function testWarnIfMissingBehaviorDiffersBasedOnWarningType(): void
    {
        // Special test to verify the different behavior for 'argument' vs 'offset' warning types
        $users = new SmartArray([['id' => 1, 'name' => 'Alice']]);

        $reflectionClass = new \ReflectionClass(SmartArray::class);
        $warnIfMissing = $reflectionClass->getMethod('warnIfMissing');
        $warnIfMissing->setAccessible(true);

        // Test 1: With warnings disabled, 'argument' warnings should still show
        SmartArray::$warnIfMissing = false;
        ob_start();
        $warnIfMissing->invokeArgs($users, ['nonexistent_column', 'argument']);
        $output = ob_get_clean();

        $this->assertNotEmpty($output, "Method argument warnings should show regardless of warnIfMissing setting");
        $this->assertStringContainsString("nonexistent_column", $output);

        // Test 2: With warnings disabled, 'offset' warnings should NOT show
        SmartArray::$warnIfMissing = false;
        ob_start();
        $warnIfMissing->invokeArgs($users, ['nonexistent_column', 'offset']);
        $output = ob_get_clean();

        $this->assertEmpty($output, "Offset warnings should be suppressed when warnIfMissing is false");

        // Test 3: With warnings enabled, both types should show
        SmartArray::$warnIfMissing = true;

        ob_start();
        $warnIfMissing->invokeArgs($users, ['nonexistent_column', 'argument']);
        $output1 = ob_get_clean();

        ob_start();
        $warnIfMissing->invokeArgs($users, ['nonexistent_column', 'offset']);
        $output2 = ob_get_clean();

        $this->assertNotEmpty($output1, "Method argument warnings should show when warnIfMissing is true");
        $this->assertNotEmpty($output2, "Offset warnings should show when warnIfMissing is true");
    }

    public function testDisablingWarnIfMissingPreventsPropertyWarnings(): void
    {
        $users = new SmartArray(['name' => 'John', 'email' => 'john@example.com']);

        // With warnings enabled (default)
        SmartArray::$warnIfMissing = true;
        ob_start();
        $value = $users->nonexistent_property;
        $output = ob_get_clean();

        $this->assertNotEmpty($output, "Warning should be shown for nonexistent property when warnIfMissing is enabled");
        $this->assertStringContainsString("nonexistent_property", $output);

        // With warnings disabled
        SmartArray::$warnIfMissing = false;
        ob_start();
        $value = $users->nonexistent_property;
        $output = ob_get_clean();

        $this->assertEmpty($output, "No warning should be shown for nonexistent property when warnIfMissing is disabled");
    }

    public function testDisablingWarnIfMissingPreventsArrayAccessWarnings(): void
    {
        $users = new SmartArray(['name' => 'John', 'email' => 'john@example.com']);

        // With warnings enabled (default)
        SmartArray::$warnIfMissing = true;
        ob_start();
        $value = $users['nonexistent_key'];
        $output = ob_get_clean();

        $this->assertNotEmpty($output, "Warning should be shown for nonexistent array key when warnIfMissing is enabled");
        $this->assertStringContainsString("nonexistent_key", $output);

        // With warnings disabled
        SmartArray::$warnIfMissing = false;
        ob_start();
        $value = $users['nonexistent_key'];
        $output = ob_get_clean();

        $this->assertEmpty($output, "No warning should be shown for nonexistent array key when warnIfMissing is disabled");
    }
}
