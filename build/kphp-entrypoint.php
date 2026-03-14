<?php

/**
 * KPHP entry point for lphenom/migrate.
 *
 * Includes all KPHP-compatible source files via explicit require_once.
 * PSR-4 autoloading is not supported in KPHP.
 *
 * WHY MigrationLoader is excluded:
 *   MigrationLoader uses `require_once $this->path . '/' . $file` — a dynamic path
 *   computed at runtime from scandir() results.
 *   KPHP compiles PHP → C++ at build time and must resolve ALL require_once paths
 *   during compilation, not at runtime. A KPHP binary contains no PHP interpreter,
 *   so it cannot load PHP files at runtime.
 *
 *   In KPHP mode, migrations are registered EXPLICITLY:
 *     require_once 'path/to/migration_file.php';
 *     $registry->register(new MigrationXXX());
 *   These paths are compile-time constants — KPHP can follow them.
 *
 * PHP-only components (shared-hosting mode only):
 *   MigrationLoader   — dynamically loads migration files from a directory
 *   MakeCommand       — generates new migration PHP files (pointless in compiled binary)
 *
 * Commands available in KPHP binary:
 *   migrate             ✅
 *   migrate:rollback    ✅
 *   migrate:status      ✅
 *   migrate:make        ❌  (PHP/shared-hosting only)
 *
 * Build:
 *   kphp -d /build/kphp-out -M cli /build/build/kphp-entrypoint.php
 *
 * Run (smoke test — no args → shows usage, exits 0):
 *   /build/kphp-out/cli
 *
 * Run (real usage with FfiMySqlConnection):
 *   DB_HOST=localhost DB_NAME=myapp DB_USER=root DB_PASS=secret \
 *       /build/kphp-out/cli migrate
 *
 * See docs/migrate.md for the full KPHP workflow.
 */

declare(strict_types=1);

// ── lphenom/db interfaces and value-objects ───────────────────────────────────

require_once __DIR__ . '/../vendor/lphenom/db/src/Param/Param.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/ParamBinder.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ResultInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/TransactionCallbackInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ConnectionInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Migration/MigrationInterface.php';

// ── lphenom/migrate source (KPHP-compatible files only) ───────────────────────

require_once __DIR__ . '/../src/Exception/MigrateException.php';
require_once __DIR__ . '/../src/MigrationRegistry.php';
require_once __DIR__ . '/../src/MigrationAutoRegistrar.php';
// MigrationLoader is excluded: uses require_once $dynamicPath (runtime path)
// which KPHP cannot resolve at compile time. Use MigrationRegistry::register()
// directly in KPHP applications.
require_once __DIR__ . '/../src/SchemaRepository.php';
require_once __DIR__ . '/../src/Migrator.php';
require_once __DIR__ . '/../src/Command/CommandInterface.php';
require_once __DIR__ . '/../src/Command/MakeCommand.php'; // compiles OK; migrate:make exits early in binary mode
require_once __DIR__ . '/../src/Command/MigrateCommand.php';
require_once __DIR__ . '/../src/Command/RollbackCommand.php';
require_once __DIR__ . '/../src/Command/StatusCommand.php';
require_once __DIR__ . '/../src/CommandDispatcher.php';

// ── KPHP stubs: no-op implementations for smoke compilation ──────────────────

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\ResultInterface;
use LPhenom\Db\Contract\TransactionCallbackInterface;
use LPhenom\Db\Migration\MigrationInterface;
use LPhenom\Db\Param\Param;

/**
 * No-op result — returns empty data for KPHP binary test.
 */
final class KphpNullResult implements ResultInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(): ?array
    {
        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(): array
    {
        return [];
    }
}

/**
 * No-op connection — used only for binary smoke test (no command given = usage).
 */
final class KphpNullConnection implements ConnectionInterface
{
    /**
     * @param array<string, Param> $params
     */
    public function query(string $sql, array $params = []): ResultInterface
    {
        return new KphpNullResult();
    }

    /**
     * @param array<string, Param> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return 0;
    }

    /**
     * @return mixed
     */
    public function transaction(TransactionCallbackInterface $callback): mixed
    {
        return null;
    }
}

/**
 * Example migration for KPHP compilation verification.
 */
final class KphpExampleMigration implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20260101000001';
    }

    public function up(ConnectionInterface $conn): void
    {
        $conn->execute(
            'CREATE TABLE IF NOT EXISTS example (id INTEGER NOT NULL, PRIMARY KEY (id))'
        );
    }

    public function down(ConnectionInterface $conn): void
    {
        $conn->execute('DROP TABLE IF EXISTS example');
    }
}

// ── Entry point ───────────────────────────────────────────────────────────────

$conn       = new KphpNullConnection();
$registry   = new \LPhenom\Migrate\MigrationRegistry();
$registry->register(new KphpExampleMigration());
$repository = new \LPhenom\Migrate\SchemaRepository($conn, 'schema_migrations');
$migrator   = new \LPhenom\Migrate\Migrator($registry, $repository);

// KPHP infers global $argv as mixed — build an explicit string[] for the dispatcher.
// In KPHP CLI binaries $argv contains the actual command-line arguments.
$cliArgs = [];
foreach ($argv as $a) {
    $cliArgs[] = (string) $a;
}

$dispatcher = new \LPhenom\Migrate\CommandDispatcher($cliArgs, $migrator, $conn, '/tmp/migrations');

$exitCode = $dispatcher->dispatch();

exit($exitCode);

