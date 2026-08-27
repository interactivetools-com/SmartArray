<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/ExitCalled.php';   // while this class is loaded, self::exit() throws it instead of exiting
require_once __DIR__ . '/Support/functions.php';    // http_response_code_clear()
