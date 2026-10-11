<?php

use Carbon\Carbon;

// Test server only: real public/index.php, fixed clock, no controller/auth shims.
if (getenv('FIXES_BROWSER_TEST') !== '1') {
    http_response_code(500);
    exit('Set FIXES_BROWSER_TEST=1 for this test router.');
}
require_once dirname(__DIR__, 2).'/vendor/autoload.php';
Carbon::setTestNow(Carbon::parse('2026-10-14 09:00:00', 'Africa/Casablanca'));
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(dirname(__DIR__, 2).'/public'.$path)) {
    return false;
}
require dirname(__DIR__, 2).'/public/index.php';
