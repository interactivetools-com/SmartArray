<?php
declare(strict_types=1);

namespace Itools\SmartArray;

/**
 * @deprecated Use SmartArray instead. This class will be removed in a future version.
 */
class SmartArrayRaw extends SmartArray
{
    public function __construct(array $array = [], ?array $properties = [])
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
        if (is_bool($properties)) {
            $properties = [];
        }
        return new static($array, $properties);
    }
}
