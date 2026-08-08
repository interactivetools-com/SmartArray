<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Pins the three-way redeclaration contract between SmartArrayBase and its
 * two subclasses. The subclasses exist to narrow the base's wide return
 * unions via covariance, and their proxy methods must stay in lockstep:
 *
 * - a method redeclared in one subclass is redeclared in the other
 * - parameter lists are identical across all three declarations
 * - subclass return types equal the base union minus the other mode's types
 *   (SmartArray drops SmartString, SmartArrayHtml drops the raw scalars)
 * - every redeclaration narrows the return type in at least one subclass; a
 *   proxy that narrows nothing belongs in the base only (it adds a call frame,
 *   and forwarding breaks methods that branch on func_num_args())
 *
 * IDE inspections can't catch drift here, so this test is the guard.
 */
class SignatureContractTest extends SmartArrayTestCase
{
    /** Methods with real per-class bodies, exempt from the proxy contract. */
    private const REAL_BODIES = ['__construct', 'new', 'asRaw', 'asHtml'];

    /** Union members each subclass removes from the base return type. */
    private const RAW_DROPS  = ['Itools\SmartString\SmartString'];
    private const HTML_DROPS = ['string', 'int', 'float', 'bool', 'null'];

    public function testProxiesDeclaredInBothSubclassesOrNeither(): void
    {
        $raw  = $this->redeclaredMethodNames(SmartArray::class);
        $html = $this->redeclaredMethodNames(SmartArrayHtml::class);

        $this->assertSame([], array_values(array_diff($raw, $html)), 'declared in SmartArray but missing from SmartArrayHtml');
        $this->assertSame([], array_values(array_diff($html, $raw)), 'declared in SmartArrayHtml but missing from SmartArray');
    }

    public function testParameterListsMatchAcrossAllThree(): void
    {
        foreach ($this->redeclaredMethodNames(SmartArray::class) as $name) {
            $base = $this->parameterShape(new ReflectionMethod(SmartArrayBase::class, $name));
            $raw  = $this->parameterShape(new ReflectionMethod(SmartArray::class, $name));
            $html = $this->parameterShape(new ReflectionMethod(SmartArrayHtml::class, $name));

            $this->assertSame($base, $raw, "SmartArray::$name() parameters differ from SmartArrayBase");
            $this->assertSame($base, $html, "SmartArrayHtml::$name() parameters differ from SmartArrayBase");
        }
    }

    public function testReturnTypesNarrowPerMode(): void
    {
        foreach ($this->redeclaredMethodNames(SmartArray::class) as $name) {
            $base = $this->typeSet((new ReflectionMethod(SmartArrayBase::class, $name))->getReturnType());
            $raw  = $this->typeSet((new ReflectionMethod(SmartArray::class, $name))->getReturnType());
            $html = $this->typeSet((new ReflectionMethod(SmartArrayHtml::class, $name))->getReturnType());

            $this->assertSame(
                array_values(array_diff($base, self::RAW_DROPS)),
                $raw,
                "SmartArray::$name() return type should be the base union minus SmartString",
            );
            $this->assertSame(
                array_values(array_diff($base, self::HTML_DROPS)),
                $html,
                "SmartArrayHtml::$name() return type should be the base union minus the raw scalars",
            );
        }
    }

    public function testRedeclaredMethodsNarrowSomething(): void
    {
        foreach ($this->redeclaredMethodNames(SmartArray::class) as $name) {
            $base = $this->typeSet((new ReflectionMethod(SmartArrayBase::class, $name))->getReturnType());
            $raw  = $this->typeSet((new ReflectionMethod(SmartArray::class, $name))->getReturnType());
            $html = $this->typeSet((new ReflectionMethod(SmartArrayHtml::class, $name))->getReturnType());

            $this->assertTrue(
                $raw !== $base || $html !== $base,
                "$name() narrows nothing in either subclass - a no-op proxy; declare it in SmartArrayBase only",
            );
        }
    }

    /**
     * Public methods redeclared in the given subclass, excluding the
     * intentionally-different per-class bodies. Sorted for stable diffs.
     *
     * @return string[]
     */
    private function redeclaredMethodNames(string $class): array
    {
        $names = [];
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            if (in_array($method->getName(), self::REAL_BODIES, true)) {
                continue;
            }
            $names[] = $method->getName();
        }
        sort($names);
        return $names;
    }

    /**
     * Comparable description of a method's parameter list: name, type,
     * by-reference, variadic, and default per parameter.
     */
    private function parameterShape(ReflectionMethod $method): array
    {
        $shape = [];
        foreach ($method->getParameters() as $param) {
            $shape[] = [
                'name'     => $param->getName(),
                'type'     => $this->typeSet($param->getType()),
                'byRef'    => $param->isPassedByReference(),
                'variadic' => $param->isVariadic(),
                'default'  => $param->isDefaultValueAvailable() ? var_export($param->getDefaultValue(), true) : null,
            ];
        }
        return $shape;
    }

    /**
     * A type as a sorted list of member names, so unions compare regardless
     * of declaration order. Nullable shorthand (?Foo) normalizes to [Foo, null].
     *
     * @return string[]
     */
    private function typeSet(?ReflectionType $type): array
    {
        if ($type === null) {
            return [];
        }
        if ($type instanceof ReflectionNamedType) {
            $names = [$type->getName()];
            if ($type->allowsNull() && !in_array($type->getName(), ['null', 'mixed'], true)) {
                $names[] = 'null';
            }
        } elseif ($type instanceof ReflectionUnionType) { // no intersection types in this API
            $names = array_map(static fn(ReflectionNamedType $t) => $t->getName(), $type->getTypes());
        } else {
            $this->fail("Unexpected reflection type " . get_class($type));
        }
        sort($names);
        return $names;
    }
}
