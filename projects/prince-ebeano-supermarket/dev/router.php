<?php
// Router for PHP's built-in server: copies what public/.htaccess does on Hostinger
// (friendly URLs such as /shop and the 404 page). Local development only.
//   php -S localhost:8000 -t public dev/router.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = $_SERVER['DOCUMENT_ROOT'];

if ($uri !== '/' && is_file($root . $uri)) {
    return false;
}
if ($uri === '/') {
    require $root . '/index.php';
    return true;
}
if (is_dir($root . $uri) && is_file($root . rtrim($uri, '/') . '/index.php')) {
    require $root . rtrim($uri, '/') . '/index.php';
    return true;
}
if (is_file($root . $uri . '.php')) {
    require $root . $uri . '.php';
    return true;
}
require $root . '/404.php';
