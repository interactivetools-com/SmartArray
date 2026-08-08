<?php
declare(strict_types=1);

/**
 * Web target for DebugTest: serves debug() output of a value holding a
 * literal </xmp> closing tag. Run under PHP's built-in server (php -S),
 * whose cli-server SAPI takes the <xmp>-wrapping web branch in xmpWrap()
 * that CLI tests can't reach.
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

\Itools\SmartArray\SmartArray::new(['payload' => '</xmp><script>alert(1)</script>'])->debug();
