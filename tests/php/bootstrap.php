<?php

declare(strict_types=1);

/*
 * Test bootstrap. Unit tests need no database. Integration tests share one freshly
 * seeded copy of the demo school in the sibol_test database (see IntegrationTestCase).
 */

define('BASE_PATH', dirname(__DIR__, 2));
define('SIBOL_TESTING', true);

// Env values from phpunit.xml must win over .env; getenv() already takes precedence in Env::get().
require BASE_PATH . '/app/bootstrap.php';

// Seeded files for the test database go to a scratch folder, not the real uploads.
Sibol\Core\Config::set('uploads.path', BASE_PATH . '/storage/cache/test-uploads');
Sibol\Core\Config::set('log.path', BASE_PATH . '/storage/cache/test.log');

if (Sibol\Core\Config::get('db.name') !== 'sibol_test') {
    fwrite(STDERR, "Refusing to run: tests must use the sibol_test database.\n");
    exit(1);
}
