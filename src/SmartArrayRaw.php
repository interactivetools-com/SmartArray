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
        @trigger_error('SmartArrayRaw is deprecated. Use SmartArray instead.', E_USER_DEPRECATED);
        parent::__construct($array, $properties);
    }

    /**
     * @deprecated Use SmartArray::new() instead.
     */
    public static function new(array $array = [], array|bool $properties = []): static
    {
        @trigger_error('SmartArrayRaw::new() is deprecated. Use SmartArray::new() instead.', E_USER_DEPRECATED);
        return new static($array, $properties);
    }
}
