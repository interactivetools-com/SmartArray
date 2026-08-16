<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use ReflectionMethod;

/**
 * Call-site-form enforcement: contains(), where(), and whereNot() each carry an
 * inlined copy of the value-match comparison (row bool-to-int cast plus $isMatch
 * match block) because a shared helper would need a per-row call. This test
 * reads the source of each method and fails if the copies drift apart, so an
 * edit to one copy can't silently change how the others match.
 */
class ValueMatchParityTest extends SmartArrayTestCase
{
    private const INLINED_COPY_METHODS = ['contains', 'where', 'whereNot'];

    public function testInlinedComparisonCopiesAreIdentical(): void
    {
        $copies = [];
        foreach (self::INLINED_COPY_METHODS as $method) {
            $copies[$method] = $this->extractComparisonBlock($method);
        }

        $reference = $copies['contains'];
        foreach ($copies as $method => $copy) {
            $this->assertSame($reference, $copy, "$method() comparison block differs from contains() - the inlined copies must stay identical");
        }
    }

    public function testEachMethodHoistsTheValueCastOutOfItsLoop(): void
    {
        foreach (self::INLINED_COPY_METHODS as $method) {
            $body = $this->normalize($this->methodSource($method));
            $this->assertStringContainsString(
                '$value = is_bool($value) ? (int)$value : $value;',
                $body,
                "$method() lost the loop-invariant \$value bool-to-int cast above its row loop",
            );
        }
    }

    /**
     * The per-row comparison: the row-value bool-to-int cast plus the $isMatch
     * match block, whitespace collapsed and the row variable renamed so
     * contains()'s $element compares equal to where()'s $rowValue.
     */
    private function extractComparisonBlock(string $method): string
    {
        $source  = $this->methodSource($method);
        $pattern = '/\$(\w+)\s+=\s+is_bool\(\$\1\)\s*\?\s*\(int\)\$\1\s*:\s*\$\1;\s+\$isMatch\s+=\s+match\s*\(true\)\s*\{.*?\};/s';
        $found   = preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);
        $this->assertSame(1, $found, "$method() should contain exactly one inlined per-row comparison block");

        [$block, $rowVar] = $matches[0];
        return $this->normalize(str_replace('$' . $rowVar, '$rowValue', $block));
    }

    private function methodSource(string $method): string
    {
        $reflection = new ReflectionMethod(SmartArrayBase::class, $method);
        $lines      = file($reflection->getFileName());
        return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
    }

    private function normalize(string $code): string
    {
        return trim(preg_replace('/\s+/', ' ', $code));
    }
}
