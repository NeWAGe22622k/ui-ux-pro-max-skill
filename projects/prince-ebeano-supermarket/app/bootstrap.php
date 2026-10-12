<?php
declare(strict_types=1);

// Every page starts here: loads config, database, session, helpers and auth.

define('APP_DIR', __DIR__);

if (!is_file(APP_DIR . '/config.php')) {
    http_response_code(500);
    exit('Setup needed: copy app/config.sample.php to app/config.php and fill in your database details.');
}

$GLOBALS['config'] = require APP_DIR . '/config.php';

// The web root (public_html on Hostinger, public/ locally).
$docroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
if ($docroot === '' || !is_dir($docroot . '/assets')) {
    foreach (['/../public_html', '/../public'] as $candidate) {
        if (is_dir(APP_DIR . $candidate . '/assets')) {
            $docroot = realpath(APP_DIR . $candidate);
            break;
        }
    }
}
define('PUBLIC_DIR', $docroot);

date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'Africa/Lagos');

require APP_DIR . '/db.php';
require APP_DIR . '/helpers.php';
require APP_DIR . '/auth.php';
require APP_DIR . '/uploads.php';
require APP_DIR . '/views/icons.php';
require APP_DIR . '/views/layout.php';
require APP_DIR . '/views/admin-layout.php';
require APP_DIR . '/views/auth-layout.php';
require APP_DIR . '/views/partials.php';

start_session();
