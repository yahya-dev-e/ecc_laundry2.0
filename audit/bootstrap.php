<?php
// Audit-only scaffolding. Never use this bootstrap in production.
$root = dirname(__DIR__);
foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache/data', 'storage/logs', 'audit/.runtime'] as $dir) {
    if (!is_dir("$root/$dir")) mkdir("$root/$dir", 0777, true);
}
$db = "$root/audit/.runtime/test.sqlite";
if (!file_exists($db)) touch($db);
foreach ([
    'APP_ENV' => 'local', 'APP_DEBUG' => 'true',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('A', 32)),
    'APP_URL' => 'http://127.0.0.1:8765', 'APP_TIMEZONE' => 'Africa/Casablanca',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $db, 'DB_URL' => '',
    'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'log',
    'LOG_CHANNEL' => 'single', 'LOG_LEVEL' => 'debug',
] as $key => $value) { putenv("$key=$value"); $_ENV[$key] = $_SERVER[$key] = $value; }
require "$root/vendor/autoload.php";
// The reviewed checkout has no App\Http\Controllers\Controller.
if (!class_exists('App\\Http\\Controllers\\Controller', false)) {
    class_alias(\Illuminate\Routing\Controller::class, 'App\\Http\\Controllers\\Controller');
}
return require "$root/bootstrap/app.php";
