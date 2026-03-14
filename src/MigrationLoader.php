<?php

declare(strict_types=1);

namespace LPhenom\Migrate;

use LPhenom\Migrate\Exception\MigrateException;

/**
 * Loads migration PHP files from a directory and populates a MigrationRegistry.
 *
 * Each migration file MUST call MigrationAutoRegistrar::register() at file scope
 * to self-register into the active registry.  This convention avoids any dynamic
 * class loading (new $className()) and is fully KPHP-compatible.
 *
 * Example migration file (database/migrations/20260101000001_create_users.php):
 *   <?php
 *   declare(strict_types=1);
 *   use LPhenom\Migrate\MigrationAutoRegistrar;
 *   use LPhenom\Db\Contract\ConnectionInterface;
 *   use LPhenom\Db\Migration\MigrationInterface;
 *
 *   final class Migration20260101000001 implements MigrationInterface {
 *       public function getVersion(): string { return '20260101000001'; }
 *       public function up(ConnectionInterface $conn): void { ... }
 *       public function down(ConnectionInterface $conn): void { ... }
 *   }
 *   MigrationAutoRegistrar::register(new Migration20260101000001());
 *
 * KPHP note: require_once is supported in KPHP CLI mode.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class MigrationLoader
{
    /**
     * @var string
     */
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Load all .php files from the migrations directory and register them.
     *
     * @throws MigrateException if the directory does not exist or a file throws
     */
    public function load(MigrationRegistry $registry): void
    {
        if (!is_dir($this->path)) {
            throw new MigrateException('Migrations directory not found: ' . $this->path);
        }

        $files = scandir($this->path);
        if ($files === false) {
            return;
        }

        MigrationAutoRegistrar::setRegistry($registry);

        $exception = null;
        try {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (substr($file, -4) !== '.php') {
                    continue;
                }

                require_once $this->path . '/' . $file;
            }
        } catch (\Throwable $e) {
            $exception = $e;
        }

        MigrationAutoRegistrar::clearRegistry();

        if ($exception !== null) {
            throw new MigrateException(
                'Failed to load migration: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Return the configured migrations directory path.
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
