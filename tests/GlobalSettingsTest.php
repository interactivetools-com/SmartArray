<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use PHPUnit\Framework\TestCase;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;

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

    public function testOnOffsetAccessDefaultIsNotify(): void
    {
        $this->assertSame('notify', SmartArrayBase::$onOffsetAccess);
    }

    public function testOnOffsetAccessNotifyEchoesAndLogs(): void
    {
        $array = new SmartArray(['name' => 'Alice']);

        $this->withMode('notify', function () use ($array) {
            $deprecationsCaught = [];
            set_error_handler(function ($errno, $errstr) use (&$deprecationsCaught) {
                if ($errno === E_USER_DEPRECATED) {
                    $deprecationsCaught[] = $errstr;
                }
                return true;
            });

            try {
                ob_start();
                $value = $array['name'];
                $output = ob_get_clean();
            } finally {
                restore_error_handler();
            }

            $this->assertStringContainsString('Deprecated:', $output, "notify mode should echo Deprecated: prefix");
            $this->assertStringContainsString("Replace ['name'] with ->name", $output, "notify mode should echo replacement suggestion");
            $this->assertNotEmpty($deprecationsCaught, "notify mode should also fire E_USER_DEPRECATED for tooling");
        });
    }

    public function testOnOffsetAccessLogDoesNotEcho(): void
    {
        $array = new SmartArray(['name' => 'Alice']);

        $this->withMode('log', function () use ($array) {
            $deprecationsCaught = [];
            set_error_handler(function ($errno, $errstr) use (&$deprecationsCaught) {
                if ($errno === E_USER_DEPRECATED) {
                    $deprecationsCaught[] = $errstr;
                }
                return true;
            });

            try {
                ob_start();
                $value = $array['name'];
                $output = ob_get_clean();
            } finally {
                restore_error_handler();
            }

            $this->assertStringNotContainsString('Deprecated:', $output, "log mode should not echo");
            $this->assertNotEmpty($deprecationsCaught, "log mode should still fire E_USER_DEPRECATED");
            $this->assertStringContainsString("Replace ['name']", $deprecationsCaught[0]);
        });
    }

    public function testOnOffsetAccessThrowRaisesRuntimeException(): void
    {
        $array = new SmartArray(['name' => 'Alice']);

        $this->withMode('throw', function () use ($array) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("Replace ['name'] with ->name");
            $value = $array['name'];
        });
    }

    public function testOnOffsetAccessThrowBlocksSet(): void
    {
        $array = new SmartArray(['name' => 'Alice']);

        $this->withMode('throw', function () use ($array) {
            try {
                $array['age'] = 30;
                $this->fail("Expected RuntimeException");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString("Replace ['age'] with ->age = \$value", $e->getMessage());
                $this->assertFalse($array->offsetExists('age'), "throw mode should halt before the assignment runs");
            }
        });
    }

    public function testOnOffsetAccessThrowBlocksUnset(): void
    {
        $array = new SmartArray(['name' => 'Alice']);

        $this->withMode('throw', function () use ($array) {
            try {
                unset($array['name']);
                $this->fail("Expected RuntimeException");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString("Replace ['name']", $e->getMessage());
                $this->assertTrue($array->offsetExists('name'), "throw mode should halt before the unset runs");
            }
        });
    }

    public function testOnOffsetAccessInvalidModeThrows(): void
    {
        $array = new SmartArray(['name' => 'Alice']);

        $this->withMode('bogus', function () use ($array) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage("Invalid SmartArrayBase::\$onOffsetAccess value: 'bogus'");
            $value = $array['name'];
        });
    }

    /**
     * Temporarily swap $onOffsetAccess, run a callback, and restore afterwards.
     * Guarantees restoration even if the callback throws or the test fails.
     */
    private function withMode(string $mode, callable $fn): void
    {
        $original = SmartArrayBase::$onOffsetAccess;
        SmartArrayBase::$onOffsetAccess = $mode;
        try {
            $fn();
        } finally {
            SmartArrayBase::$onOffsetAccess = $original;
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
