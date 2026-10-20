<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in web server, for trying Sibol on another PHP version without Apache
 * (for example the browser tests on a portable PHP 8.3). Local testing only, never for real use:
 *   C:\tools\php83\php.exe -d error_reporting=-1 -S 127.0.0.1:8083 -t public tools\dev-server.php
 * with APP_URL=http://127.0.0.1:8083 in the environment. Files that exist under public/ are served as
 * they are; every other address goes through the front controller, as public/.htaccess does in Apache.
 */

$public = dirname(__DIR__) . '/public';
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($path !== '/' && !str_contains($path, '..') && is_file($public . $path)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $public . '/index.php';
require $public . '/index.php';
