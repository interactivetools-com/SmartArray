<?php
declare(strict_types=1);

namespace Itools\SmartArray;

/**
 * @deprecated Use SmartArray instead. This class will be removed in a future version.
 */
class SmartArrayRaw extends SmartArray
{
    /**
     * @deprecated Use new SmartArray() instead.
     */
    public function __construct(array $array = [], bool|array|null $properties = [])
    {
        self::logDeprecation('Replace SmartArrayRaw with SmartArray');
        parent::__construct($array, $properties);
    }

    /**
     * @deprecated Use SmartArray::new() instead.
     */
    public static function new(array $array = [], array|bool $properties = []): static
    {
        // No notice here: the constructor below logs one, so new() stays at one notice per call
        return new static($array, $properties);
    }
}
