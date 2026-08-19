<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Unit;

use Error;
use InvalidArgumentException;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * The message-encoding rule: every error, warning, or exception message
 * HTML-encodes the values it interpolates (keys, method names, fields - anything
 * not safe by construction), because handlers often echo messages into pages.
 * Sites encode with SharedHelpers::h(), usually inlined as {$h(...)}.
 */
class ErrorMessageEncodingTest extends SmartArrayTestCase
{
    private const PAYLOAD = '<b>x</b>';

    /** Each case triggers one message site with markup in the data slot */
    public static function messageSiteProvider(): array
    {
        return [
            'setElement key'         => [fn($class) => $class::new([])->{self::PAYLOAD} = new stdClass()],
            '__call method'          => [fn($class) => $class::new([])->{self::PAYLOAD}()],
            '__callStatic method'    => [fn($class) => $class::{self::PAYLOAD}()],
            'where() list hint'      => [fn($class) => $class::new([['a' => 1]])->where([self::PAYLOAD])],
            'indexBy float field'    => [fn($class) => $class::new([[self::PAYLOAD => 1.5]])->indexBy(self::PAYLOAD)],
            'groupBy float field'    => [fn($class) => $class::new([[self::PAYLOAD => 1.5]])->groupBy(self::PAYLOAD)],
            'column float indexKey'  => [fn($class) => $class::new([['a' => 1, self::PAYLOAD => 1.5]])->column('a', self::PAYLOAD)],
        ];
    }

    #[DataProvider('modeProvider')]
    public function testMessageSitesEncodeInterpolatedData(string $class): void
    {
        foreach (self::messageSiteProvider() as $site => [$trigger]) {
            try {
                $trigger($class);
                $this->fail("$site: expected a throw");
            } catch (Error|InvalidArgumentException $e) {
                $this->assertStringContainsString('&lt;b&gt;', $e->getMessage(), "$site: markup must be encoded");
                $this->assertStringNotContainsString(self::PAYLOAD, $e->getMessage(), "$site: raw markup must not appear");
            }
        }
    }
}
