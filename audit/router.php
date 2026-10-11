<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = realpath(dirname(__DIR__).'/public');
$file = realpath($public.$path);
if (str_starts_with($path, '/build/') && $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR) && is_file($file)) {
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    header('Content-Type: '.(['css'=>'text/css','js'=>'text/javascript','svg'=>'image/svg+xml'][$ext] ?? 'application/octet-stream'));
    readfile($file);
    return;
}
$app = require __DIR__.'/bootstrap.php';
$app->handleRequest(\Illuminate\Http\Request::capture());
