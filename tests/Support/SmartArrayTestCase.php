<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Support;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\SmartNull;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\TestCase;

/**
 * Base class for the Unit and Integration suites.
 *
 * Conventions (full list in __test-plan.md):
 * - Behaviors run against both SmartArray and SmartArrayHtml via modeProvider()
 * - Warnings and deprecation notices are echoed output by design: tests assert
 *   the text or assert silence, never capture-and-discard
 * - assertSame with literal expected values
 */
abstract class SmartArrayTestCase extends TestCase
{
    //region Modes

    /**
     * Data provider: run the test against both collection modes.
     *
     * @return array<string, array{class-string<SmartArrayBase>}>
     */
    public static function modeProvider(): array
    {
        return [
            'raw'  => [SmartArray::class],
            'html' => [SmartArrayHtml::class],
        ];
    }

    /**
     * Assert a scalar/null element came back in the right wrapper for the mode:
     * the raw PHP value for SmartArray, a SmartString wrapping it for SmartArrayHtml.
     */
    protected function assertModeValue(string|int|float|bool|null $expected, mixed $actual, string $class, string $message = ''): void
    {
        if ($class === SmartArrayHtml::class) {
            $this->assertInstanceOf(SmartString::class, $actual, $message);
            $this->assertSame($expected, $actual->value(), $message);
        } else {
            $this->assertSame($expected, $actual, $message);
        }
    }

    protected function assertSmartNull(mixed $actual, string $message = ''): void
    {
        $this->assertInstanceOf(SmartNull::class, $actual, $message);
    }

    //endregion
    //region Output and Error Capture

    /**
     * Run $fn capturing echoed output. Returns [result, output].
     *
     * @return array{0: mixed, 1: string}
     */
    protected function captureOutput(callable $fn): array
    {
        ob_start();
        try {
            $result = $fn();
        } finally {
            $output = ob_get_clean();
        }
        return [$result, $output];
    }

    /**
     * Run $fn collecting E_USER_DEPRECATED messages. The library sends these via
     * @trigger_error, so only an error handler can observe them. Returns [result, messages].
     *
     * @return array{0: mixed, 1: string[]}
     */
    protected function captureDeprecations(callable $fn): array
    {
        $messages = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$messages): bool {
            $messages[] = $errstr;
            return $errno === E_USER_DEPRECATED; // always true given the mask; anything else falls through to PHP
        }, E_USER_DEPRECATED);
        try {
            $result = $fn();
        } finally {
            restore_error_handler();
        }
        return [$result, $messages];
    }

    //endregion
    //region Structure Invariants

    /**
     * Walk the whole object and verify structural invariants: element types,
     * position metadata on child rows, root references, mysqli propagation,
     * and mode propagation. Call on the result of any transformation.
     */
    protected function assertValidStructure(SmartArrayBase $obj, string $path = '$obj', ?SmartArrayBase $root = null): void
    {
        $isRoot = $root === null;
        $keys   = $obj->keys()->toArray();

        // Elements must be SmartArrayBase, SmartString, scalar, or null
        foreach ($obj as $key => $element) {
            $isAllowed = $element instanceof SmartArrayBase || $element instanceof SmartString || is_scalar($element) || is_null($element);
            $this->assertTrue($isAllowed, "$path->$key: invalid element type " . get_debug_type($element));
        }

        // Child rows carry 1-based position metadata; scalar slots still count
        $position = 0;
        $firstKey = $keys[0] ?? null;
        $lastKey  = $keys[count($keys) - 1] ?? null;
        foreach ($obj as $key => $el) {
            $position++;
            if (!$el instanceof SmartArrayBase) {
                continue;
            }
            $this->assertSame($position, $el->position(), "$path->$key position()");
            $this->assertSame($key === $firstKey, $el->isFirst(), "$path->$key isFirst()");
            $this->assertSame($key === $lastKey, $el->isLast(), "$path->$key isLast()");
        }

        // Root reference: self for the root, the actual root for children
        if ($isRoot) {
            $this->assertSame($obj, $obj->root(), "$path root() should reference self");
        } else {
            $this->assertSame($root, $obj->root(), "$path root() should reference the root array");
            $this->assertSame($root->mysqli(), $obj->mysqli(), "$path mysqli() should match root");
            $this->assertSame($root->usingSmartStrings(), $obj->usingSmartStrings(), "$path usingSmartStrings() should match root");
        }

        // Recurse over child SmartArrays
        $root ??= $obj;
        foreach ($obj as $key => $element) {
            if ($element instanceof SmartArrayBase) {
                $this->assertValidStructure($element, "$path->$key", $root);
            }
        }
    }

    /**
     * Assert a derived array kept the source's metadata: mysqli data, root
     * reference, and mode.
     */
    protected function assertMetadataPreserved(SmartArrayBase $source, SmartArrayBase $result): void
    {
        $this->assertSame($source->mysqli(), $result->mysqli(), 'mysqli metadata should carry to derived arrays');
        $this->assertSame($source->root(), $result->root(), 'root reference should carry to derived arrays');
        $this->assertSame($source->usingSmartStrings(), $result->usingSmartStrings(), 'mode should carry to derived arrays');
    }

    //endregion
}
