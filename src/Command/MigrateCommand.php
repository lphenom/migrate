<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Command;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Migrate\Migrator;

/**
 * Applies all pending migrations.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class MigrateCommand implements CommandInterface
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

        $applied = $this->migrator->migrate($this->conn);

        if (count($applied) === 0) {
            echo 'Nothing to migrate.' . PHP_EOL;

            return 0;
        }

        foreach ($applied as $version) {
            echo 'Migrated:  ' . $version . PHP_EOL;
        }

        echo PHP_EOL . 'Applied ' . count($applied) . ' migration(s).' . PHP_EOL;

        return 0;
    }
}
