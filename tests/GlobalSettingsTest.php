<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use PHPUnit\Framework\TestCase;
use Itools\SmartArray\SmartArray;

class GlobalSettingsTest extends TestCase
{

    public function testDeprecationsAlwaysTriggered(): void
    {
        // Verify that deprecation notices are always triggered (no longer optional)
        $deprecationCaught = false;
        set_error_handler(function ($errno) use (&$deprecationCaught) {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationCaught = true;
            }
            return true;
        });

        try {
            $reflectionClass = new \ReflectionClass(SmartArray::class);
            $method          = $reflectionClass->getMethod('logDeprecation');
            $method->setAccessible(true);
            $method->invokeArgs(null, ['This is a test deprecation message']);
        } finally {
            restore_error_handler();
        }

        $this->assertTrue($deprecationCaught, "Deprecation notice should always be triggered");
    }

    public function testWarningsShownForMissingProperties(): void
    {
        $array = new SmartArray(['name' => 'Alice']);

        ob_start();
        $value = $array->age; // Access nonexistent property
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $this->assertStringContainsString(" age ", $output);
    }

    public function testWarningsShownForMissingArrayKeys(): void
    {
        $array = new SmartArray(['name' => 'Alice']);

        ob_start();
        $value = $array['age']; // Access nonexistent key
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $this->assertStringContainsString(" age ", $output);
    }

    public function testWarningsShownForMissingMethodArguments(): void
    {
        $array = new SmartArray([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $methodTests = [
            'indexBy' => ['nonexistent_column'],
            'pluck'   => ['nonexistent_column'],
            'groupBy' => ['nonexistent_column'],
        ];

        foreach ($methodTests as $method => $args) {
            ob_start();
            try {
                $array->$method(...$args);
            } catch (\Exception $e) {
                // Some methods might throw exceptions, that's fine
            }
            $output = ob_get_clean();
            $this->assertNotEmpty($output, "Method $method should warn for missing column");
            $this->assertStringContainsString("nonexistent_column", $output);
        }
    }

    public function testInternalMethodsDoNotTriggerDeprecationWarnings(): void
    {
        $arr = SmartArray::new(['a' => 1, 'b' => 2, 'c' => 3]);

        // Capture any deprecation notices
        $deprecationsCaught = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecationsCaught) {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationsCaught[] = $errstr;
            }
            return true;
        });

        // Call internal methods that previously used offsetGet()
        $arr->first();
        $arr->last();
        $arr->nth(1);
        $arr->each(fn($v, $k) => null);
        $arr->map(fn($v) => $v);

        restore_error_handler();

        // Filter out any deprecations that contain "Array access"
        $arrayAccessDeprecations = array_filter($deprecationsCaught, fn($msg) => str_contains($msg, 'Array access'));

        $this->assertEmpty(
            $arrayAccessDeprecations,
            "Internal methods should not trigger array access deprecation warnings. Got: " . implode(', ', $arrayAccessDeprecations)
        );
    }
}
