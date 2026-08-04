<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Integration;

use Itools\SmartArray\Deprecations;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\SmartNull;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use Itools\SmartString\SmartString;
use JetBrains\PhpStorm\Deprecated;
use ReflectionClass;
use ReflectionMethod;

/**
 * Keeps the public API and the docs in sync in both directions.
 *
 * Forward: every public method of SmartArrayBase, SmartArray, and SmartArrayHtml
 * must appear in docs/method-reference.md. The exempt set is read off the code
 * itself (Deprecations membership, #[Deprecated], @deprecated, @internal,
 * interface methods, magic methods), so a new public method fails this test until
 * it is documented, and a newly deprecated one stops being required with no test
 * edit. There is no hand-maintained skip list.
 *
 * Reverse: every ->method() named in method-reference.md must exist as a callable
 * method on SmartArray, SmartArrayHtml, SmartNull, or SmartString, which catches
 * typos and references left behind after a rename.
 */
final class DocsCoverageTest extends SmartArrayTestCase
{
    //region Configuration

    private const METHOD_REFERENCE_PATH = __DIR__ . '/../../docs/method-reference.md';

    /** Classes whose own public methods make up the documented surface. */
    private const API_CLASSES = [SmartArrayBase::class, SmartArray::class, SmartArrayHtml::class];

    /**
     * Interface methods are reached through PHP syntax (foreach, count(), json_encode(),
     * $array['key']) rather than a documented ->method() call, so they are exempt. count()
     * is the exception: ->count() is an ordinary call in templates and both documents list
     * it, so it stays required.
     */
    private const INTERFACE_METHODS_STILL_REQUIRED = ['count'];

    /**
     * Public methods that no document mentions yet. Names listed here are dropped from
     * the forward check so the rest of the guard still runs, and each one is a docs bug
     * waiting to be written, not a permanent exemption.
     */
    // Empty as of 2026-08-02: every public method is documented in both files.
    private const UNDOCUMENTED_TODAY = [];

    //endregion
    //region Forward Coverage

    public function testMethodReferenceDocumentsEveryPublicMethod(): void
    {
        $this->assertDocumentsEveryPublicMethod(self::METHOD_REFERENCE_PATH, 'docs/method-reference.md');
    }

    /**
     * A reflection bug that returned an empty or tiny list would make the coverage tests
     * pass without checking anything, so pin the size and a few names that must be there.
     */
    public function testRequiredMethodListCoversTheKnownApi(): void
    {
        $required = self::requiredMethodNames();

        $this->assertGreaterThan(30, count($required), 'Required-method list looks truncated; the reflection scan is not seeing the API');

        foreach (['new', 'first', 'where', 'column', 'implode', 'count', 'orThrow'] as $anchor) {
            $this->assertContains($anchor, $required, "$anchor() should be part of the documented public API");
        }
    }

    /**
     * The coverage tests are only meaningful if the pattern matches a real call and not a
     * longer name that happens to end with the same letters.
     */
    public function testMentionPatternRejectsSubstringMatches(): void
    {
        $this->assertSame(1, preg_match(self::mentionPattern('at'), '$rows->at(0)'), 'Pattern should match a real ->at() call');
        $this->assertSame(0, preg_match(self::mentionPattern('at'), 'sprintf(format($x))'), 'Pattern should not match at() inside format()');
        $this->assertSame(0, preg_match(self::mentionPattern('set'), '$obj->offsetSet($k, $v)'), 'Pattern should not match set() inside offsetSet()');
    }

    //endregion
    //region Exemptions

    public function testDeprecatedMethodsAreNotRequired(): void
    {
        $traitMethods = [];
        foreach ((new ReflectionClass(Deprecations::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $traitMethods[] = $method->getName();
        }

        $stillRequired = array_values(array_intersect(self::requiredMethodNames(), $traitMethods));

        $this->assertSame([], $stillRequired, 'Methods declared in Deprecations should not be required in the docs');
    }

    public function testInternalMethodsAreNotRequired(): void
    {
        $this->assertNotContains('root', self::requiredMethodNames(), 'root() carries @internal, so it is exempt from the docs requirement');
    }

    public function testInterfacePlumbingIsNotRequiredExceptCount(): void
    {
        $required = self::requiredMethodNames();

        foreach (['offsetExists', 'offsetGet', 'offsetSet', 'offsetUnset', 'getIterator', 'jsonSerialize'] as $name) {
            $this->assertNotContains($name, $required, "$name() is interface plumbing, callers reach it through PHP syntax");
        }

        $this->assertContains('count', $required, 'count() is a documented call, not just Countable plumbing');
    }

    //endregion
    //region Reverse Coverage

    /**
     * Catches doc typos and references to renamed or removed methods.
     */
    public function testMethodReferenceMethodMentionsAreCallable(): void
    {
        $text = self::readDoc(self::METHOD_REFERENCE_PATH);
        preg_match_all('/->([a-zA-Z_][a-zA-Z0-9_]*)\(/', $text, $matches);

        $callable = self::callableMethodNames();
        $unknown  = [];
        foreach (array_unique($matches[1]) as $name) {
            if (!in_array($name, $callable, true)) {
                $unknown[] = "$name()";
            }
        }
        sort($unknown);

        $this->assertSame([], $unknown, 'docs/method-reference.md names methods that do not exist on SmartArray, SmartArrayHtml, SmartNull, or SmartString');
    }

    //endregion
    //region Pinned Gaps

    /**
     * Once a pinned method gets documented it must leave the list, otherwise
     * the pin quietly turns into a permanent skip.
     */
    public function testUndocumentedTodayPinsAreStillAccurate(): void
    {
        $reference = self::readDoc(self::METHOD_REFERENCE_PATH);

        $nowDocumented = [];
        foreach (self::UNDOCUMENTED_TODAY as $name) {
            $pattern = self::mentionPattern($name);
            if (preg_match($pattern, $reference) === 1) {
                $nowDocumented[] = "$name()";
            }
        }

        $this->assertSame([], $nowDocumented, 'These methods are documented now; remove them from UNDOCUMENTED_TODAY');
    }

    //endregion
    //region Helpers

    private function assertDocumentsEveryPublicMethod(string $path, string $label): void
    {
        $text    = self::readDoc($path);
        $missing = [];
        foreach (self::requiredMethodNames() as $name) {
            if (preg_match(self::mentionPattern($name), $text) !== 1) {
                $missing[] = "$name()";
            }
        }

        $this->assertSame([], $missing, "$label has no entry for these public methods; document them, or mark them @deprecated or @internal in the source");
    }

    /**
     * Public methods that must appear in both documents, gathered by reflection.
     *
     * @return string[] sorted, deduplicated method names
     */
    private static function requiredMethodNames(): array
    {
        $names = [];
        foreach (self::API_CLASSES as $class) {
            $reflection = new ReflectionClass($class);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue; // inherited or overridden; the declaring class already contributed it
                }
                if (self::isExemptFromDocs($method)) {
                    continue;
                }
                $names[$method->getName()] = true;
            }
        }

        foreach (self::UNDOCUMENTED_TODAY as $pinned) {
            unset($names[$pinned]);
        }

        $names = array_keys($names);
        sort($names);
        return $names;
    }

    private static function isExemptFromDocs(ReflectionMethod $method): bool
    {
        return str_starts_with($method->getName(), '__')
            || self::isDeclaredInDeprecations($method)
            || $method->getAttributes(Deprecated::class) !== []
            || self::hasDocTag($method, 'deprecated')
            || self::hasDocTag($method, 'internal')
            || self::isInterfacePlumbing($method->getName());
    }

    private static function isDeclaredInDeprecations(ReflectionMethod $method): bool
    {
        static $trait = null;
        $trait ??= new ReflectionClass(Deprecations::class);

        $name = $method->getName();
        if (!$trait->hasMethod($name)) {
            return false;
        }

        // Trait methods report the using class as their declaring class, so compare source files instead
        return $trait->getMethod($name)->getFileName() === $method->getFileName();
    }

    private static function hasDocTag(ReflectionMethod $method, string $tag): bool
    {
        $docComment = $method->getDocComment();

        return $docComment !== false && preg_match('/^\s*\*\s*@' . $tag . '\b/m', $docComment) === 1;
    }

    private static function isInterfacePlumbing(string $name): bool
    {
        static $plumbing = null;
        if ($plumbing === null) {
            $plumbing = [];
            foreach ((new ReflectionClass(SmartArrayBase::class))->getInterfaceNames() as $interface) {
                foreach ((new ReflectionClass($interface))->getMethods() as $method) {
                    $plumbing[$method->getName()] = true;
                }
            }
            foreach (self::INTERFACE_METHODS_STILL_REQUIRED as $required) {
                unset($plumbing[$required]);
            }
        }

        return isset($plumbing[$name]);
    }

    /**
     * Every method a documented ->method() call could legitimately land on: collection
     * methods, the SmartNull placeholder, and the SmartString methods that element values
     * expose in HTML mode.
     *
     * @return string[]
     */
    private static function callableMethodNames(): array
    {
        static $names = null;
        $names ??= array_values(array_unique(array_merge(
            get_class_methods(SmartArray::class),
            get_class_methods(SmartArrayHtml::class),
            get_class_methods(SmartNull::class),
            get_class_methods(SmartString::class),
        )));

        return $names;
    }

    private static function mentionPattern(string $name): string
    {
        return '/\b' . preg_quote($name, '/') . '\(/';
    }

    private static function readDoc(string $path): string
    {
        self::assertFileExists($path);

        return (string)file_get_contents($path);
    }

    //endregion
}
