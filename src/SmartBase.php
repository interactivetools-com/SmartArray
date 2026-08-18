<?php
declare(strict_types=1);

namespace Itools\SmartArray;

/**
 * Common interface for SmartArray collections and SmartNull.
 * SmartString does not implement it: fields need their own type hints.
 *
 * Use for type hints that accept any SmartArray variant or SmartNull:
 *
 *     function process(SmartBase $data): void
 *
 * Type checking:
 *
 *     $x instanceof SmartBase       // Any collection type or SmartNull
 *     $x instanceof SmartArrayBase  // Any array type (SmartArray or SmartArrayHtml)
 *     $x instanceof SmartArray      // Raw mode only
 *     $x instanceof SmartArrayHtml  // HTML mode only
 *     $x instanceof SmartNull       // Null object only
 */
interface SmartBase
{
}
