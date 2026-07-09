<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Support;

/**
 * Shared test data for the Unit and Integration suites. One copy, used
 * everywhere (replaces the three diverging helper copies in the old suite).
 */
class Fixtures
{
    /**
     * Uniform records covering the value-type spread: HTML-special strings,
     * zero/negative numbers, bools, nulls, unicode, and a field named after
     * the internal isFirst property to catch name collisions.
     */
    public static function records(): array
    {
        return [
            [
                'html'    => "<img src='' alt='\"'>",
                'int'     => 7,
                'float'   => 5.7,
                'string'  => '&nbsp;',
                'bool'    => true,
                'null'    => null,
                'isFirst' => 'C',  // intentionally named after internal private property to detect conflicts
            ],
            [
                'html'    => '<p>"It\'s"</p>',
                'int'     => 0,
                'float'   => 1.23,
                'string'  => '"green"',
                'bool'    => false,
                'null'    => null,
                'isFirst' => 'Q',
            ],
            [
                'html'    => "<hr class='line'>",
                'int'     => 1,
                'float'   => -16.7,
                'string'  => '<blue>',
                'bool'    => false,
                'null'    => null,
                'isFirst' => 'K',
            ],
            [
                'html'    => '<a href="?a=1&b=2">Héllo こんにちは 🚀</a>',
                'int'     => -3,
                'float'   => 0.0,
                'string'  => '',
                'bool'    => true,
                'null'    => null,
                'isFirst' => 'Z',
            ],
        ];
    }

    /**
     * A single record (records()[1]: the zero/false/empty-ish one).
     */
    public static function record(): array
    {
        return self::records()[1];
    }

    /**
     * The standard menu of scalar edge values for any value-transforming method.
     * Keys are labels for data-provider names.
     */
    public static function edgeScalars(): array
    {
        return [
            'empty string' => '',
            'zero string'  => '0',
            'zero int'     => 0,
            'zero float'   => 0.0,
            'false'        => false,
            'true'         => true,
            'null'         => null,
            'float'        => 3.14,
            'negative int' => -1,
            'apostrophe'   => "O'Brien",
            'script tag'   => '<script>alert(1)</script>',
            'pre-encoded'  => '&amp; already encoded',
            'tab'          => "tab\tted",
            'newline'      => "line\nbreak",
            'invalid utf8' => "caf\xE9",
            'unicode'      => 'ünïcödé こんにちは 🚀',
        ];
    }
}
