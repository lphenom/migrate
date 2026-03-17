<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Command;

use LPhenom\Migrate\Migrator;

/**
 * Displays the status of all registered migrations.
 *
 * @lphenom-build shared,kphp
 */
final class StatusCommand implements CommandInterface
{
    /**
     * @var Migrator
     */
    private Migrator $migrator;

    public function __construct(Migrator $migrator)
    {
        $this->migrator = $migrator;
    }

    public function run(): int
    {
        $this->migrator->prepare();

        $status = $this->migrator->status();

        if (count($status) === 0) {
            echo 'No migrations registered.' . PHP_EOL;

            return 0;
        }

        echo sprintf('%-50s %s', 'Version', 'Status') . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;

        foreach ($status as $version => $state) {
            echo sprintf('%-50s %s', $version, $state) . PHP_EOL;
        }

        return 0;
    }
}
