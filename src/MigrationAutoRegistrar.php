<?php

declare(strict_types=1);

namespace LPhenom\Migrate;

use LPhenom\Db\Migration\MigrationInterface;
use LPhenom\Migrate\Exception\MigrateException;

/**
 * Provides a static registration point that migration files call at include-time.
 *
 * Usage in a migration file:
 *   LPhenom\Migrate\MigrationAutoRegistrar::register(new MyMigration());
 *
 * The MigrationLoader sets the active registry before require_once-ing each file,
 * then clears it afterwards.  This avoids dynamic class instantiation (new $class())
 * and keeps everything KPHP-compatible.
 *
 * KPHP notes:
 *  - Static properties on final classes are supported.
 *  - Static method calls are supported.
 *  - No callable, no Reflection, no dynamic class loading.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class MigrationAutoRegistrar
{
    /**
     * @var MigrationRegistry|null
     */
    private static ?MigrationRegistry $registry = null;

    private function __construct()
    {
    }

    /**
     * Set the active registry before loading migration files.
     */
    public static function setRegistry(MigrationRegistry $registry): void
    {
        self::$registry = $registry;
    }

    /**
     * Clear the active registry after loading.
     */
    public static function clearRegistry(): void
    {
        self::$registry = null;
    }

    /**
     * Called from inside each migration file to self-register.
     *
     * @throws MigrateException if called outside a load() cycle
     */
    public static function register(MigrationInterface $migration): void
    {
        $reg = self::$registry;
        if ($reg === null) {
            throw new MigrateException(
                'MigrationAutoRegistrar::register() called outside a MigrationLoader::load() cycle. '
                . 'Ensure your migration file is only included via MigrationLoader.'
            );
        }

        $reg->register($migration);
    }
}
