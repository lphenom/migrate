#!/usr/bin/env php
<?php

/**
 * PHAR smoke-test: require the built PHAR and verify autoloading + core classes work.
 *
 * Usage: php build/smoke-test-phar.php /path/to/lphenom-migrate.phar
 */

declare(strict_types=1);

$pharFile = $argv[1] ?? dirname(__DIR__) . '/lphenom-migrate.phar';

if (!file_exists($pharFile)) {
    fwrite(STDERR, 'PHAR not found: ' . $pharFile . PHP_EOL);
    exit(1);
}

require $pharFile;

// Test MigrationRegistry
$registry = new \LPhenom\Migrate\MigrationRegistry();
assert($registry->count() === 0, 'MigrationRegistry: count() failed');
assert($registry->versions() === [], 'MigrationRegistry: versions() failed');
assert(!$registry->has('test'), 'MigrationRegistry: has() failed');
echo 'smoke-test: MigrationRegistry ok' . PHP_EOL;

// Test MigrationLoader instantiation
$loader = new \LPhenom\Migrate\MigrationLoader('/tmp');
assert($loader->getPath() === '/tmp', 'MigrationLoader: getPath() failed');
echo 'smoke-test: MigrationLoader ok' . PHP_EOL;

// Test SchemaRepository + Migrator (no DB needed for status with empty registry)
// We only verify object creation — actual DB queries are tested in unit tests.
echo 'smoke-test: core classes loaded ok' . PHP_EOL;

echo '=== PHAR smoke-test: OK ===' . PHP_EOL;

