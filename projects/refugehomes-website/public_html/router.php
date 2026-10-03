<?php
// Local preview only (php -S localhost:8000 router.php). Hostinger uses .htaccess instead.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(includes|content|private)(/|$)#', $path)) { http_response_code(403); exit('Forbidden'); }
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}
if (preg_match('#^/admin/?$#', $path)) { require __DIR__ . '/admin/index.php'; return true; }
$name = trim($path, '/') ?: 'index';
if (preg_match('/^[a-z0-9-]+$/', $name) && is_file(__DIR__ . "/$name.php")) {
    $_SERVER['SCRIPT_NAME'] = "/$name.php";
    require __DIR__ . "/$name.php";
    return true;
}
http_response_code(404);
require __DIR__ . '/404.php';
