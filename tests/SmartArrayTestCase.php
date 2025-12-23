<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests;

use PHPUnit\Framework\TestCase;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartNull;
use Itools\SmartString\SmartString;

/**
 * Base test case for SmartArray method tests.
 * Provides shared helper methods and test utilities.
 */
abstract class SmartArrayTestCase extends TestCase
{

    //region Normalization Helpers

    /**
     * Normalize result for Raw variant comparison (handles SmartArray/SmartNull)
     */
    protected function normalizeRaw(mixed $var): mixed
    {
        return TestHelpers::normalizeRaw($var);
    }

    /**
     * Normalize SmartString result (HTML-encoded strings)
     */
    protected function normalizeSS(mixed $var): mixed
    {
        return TestHelpers::normalizeSS($var);
    }

    /**
     * HTML-encode expected values for SmartString comparison
     */
    protected function htmlEncode(mixed $var): mixed
    {
        return TestHelpers::recursiveHtmlEncode($var);
    }

    //endregion
    //region Test Data Helpers

    /**
     * Standard test records for data providers
     */
    protected static function getTestRecords(): array
    {
        return TestHelpers::getTestRecords();
    }

    protected static function getTestRecord(): array
    {
        return TestHelpers::getTestRecord();
    }

    //endregion

}
