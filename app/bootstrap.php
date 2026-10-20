<?php

declare(strict_types=1);

/*
 * Shared bootstrap for the web front controller, CLI commands and tests.
 * Works on PHP 8.0+ (XAMPP ships 8.0.x); avoid 8.1+ syntax such as enums,
 * readonly properties, `never`, or first-class callable syntax.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Composer (PDF parser, PHPUnit) when installed; otherwise our own PSR-4 loader.
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Sibol\\', 6) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, 6));
    $file = BASE_PATH . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

Sibol\Core\Env::load(BASE_PATH . '/.env');
Sibol\Core\Config::load(BASE_PATH . '/config/app.php');

// All stored timestamps are UTC; conversion to the school/user timezone happens on display.
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
