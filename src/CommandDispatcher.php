<?php

declare(strict_types=1);

namespace LPhenom\Migrate;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Migrate\Command\MakeCommand;
use LPhenom\Migrate\Command\MigrateCommand;
use LPhenom\Migrate\Command\RollbackCommand;
use LPhenom\Migrate\Command\StatusCommand;

/**
 * Routes CLI arguments to the correct command.
 *
 * Supported commands:
 *   migrate            — apply pending migrations
 *   migrate:rollback   — rollback last batch
 *   migrate:status     — show migration status
 *
 * KPHP notes:
 *  - Uses if/elseif instead of match (match expression has limited KPHP support).
 *  - $argv is string[] — accessed by integer index with ?? default.
 *  - substr() used instead of str_starts_with() (not available in KPHP).
 *  - No dynamic class instantiation.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class CommandDispatcher
{
    /**
     * @var string[]
     */
    private array $argv;

    /**
     * @var Migrator
     */
    private Migrator $migrator;

    /**
     * @var ConnectionInterface
     */
    private ConnectionInterface $conn;

    /**
     * @var string
     */
    private string $migrationsPath;

    /**
     * @param string[] $argv Raw CLI argument list (typically $argv from PHP globals)
     */
    public function __construct(array $argv, Migrator $migrator, ConnectionInterface $conn, string $migrationsPath = 'database/migrations')
    {
        $this->argv          = $argv;
        $this->migrator      = $migrator;
        $this->conn          = $conn;
        $this->migrationsPath = $migrationsPath;
    }

    /**
     * Dispatch the command and return an exit code.
     *
     * @return int 0 on success, non-zero on error
     */
    public function dispatch(): int
    {
        $command = $this->argv[1] ?? '';

        if ($command === 'migrate:make') {
            $name    = $this->argv[2] ?? '';
            $makeCmd = new MakeCommand($this->migrationsPath, $name);

            return $makeCmd->run();
        }

        if ($command === 'migrate') {
            $migrateCmd = new MigrateCommand($this->migrator, $this->conn);

            return $migrateCmd->run();
        }

        if ($command === 'migrate:rollback') {
            $rollbackCmd = new RollbackCommand($this->migrator, $this->conn);

            return $rollbackCmd->run();
        }

        if ($command === 'migrate:status') {
            $statusCmd = new StatusCommand($this->migrator);

            return $statusCmd->run();
        }

        $this->printUsage();

        return 0;
    }

    /**
     * Print usage information to stdout.
     */
    public function printUsage(): void
    {
        echo 'LPhenom Migrate CLI' . PHP_EOL;
        echo PHP_EOL;
        echo 'Usage:' . PHP_EOL;
        echo '  migrate:make <name>   Generate a new migration file' . PHP_EOL;
        echo '  migrate               Apply all pending migrations' . PHP_EOL;
        echo '  migrate:rollback      Rollback the last batch' . PHP_EOL;
        echo '  migrate:status        Show migration status' . PHP_EOL;
        echo PHP_EOL;
        echo 'Options (for bin/migrate script):' . PHP_EOL;
        echo '  --config=<file>   Path to migrate.php config (default: migrate.php)' . PHP_EOL;
        echo '  --path=<dir>      Migrations directory (default: database/migrations)' . PHP_EOL;
        echo '  --table=<name>    Tracking table name (default: schema_migrations)' . PHP_EOL;
    }
}
