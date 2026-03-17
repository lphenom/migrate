<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Command;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Migrate\Migrator;

/**
 * Rolls back the last applied migration batch.
 *
 * @lphenom-build shared,kphp
 */
final class RollbackCommand implements CommandInterface
{
    /**
     * @var Migrator
     */
    private Migrator $migrator;

    /**
     * @var ConnectionInterface
     */
    private ConnectionInterface $conn;

    public function __construct(Migrator $migrator, ConnectionInterface $conn)
    {
        $this->migrator = $migrator;
        $this->conn     = $conn;
    }

    public function run(): int
    {
        $this->migrator->prepare();

        $rolledBack = $this->migrator->rollback($this->conn);

        if (count($rolledBack) === 0) {
            echo 'Nothing to rollback.' . PHP_EOL;

            return 0;
        }

        foreach ($rolledBack as $version) {
            echo 'Rolled back: ' . $version . PHP_EOL;
        }

        echo PHP_EOL . 'Rolled back ' . count($rolledBack) . ' migration(s).' . PHP_EOL;

        return 0;
    }
}
