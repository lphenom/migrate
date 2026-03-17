<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Command;

/**
 * Contract for a single CLI command.
 *
 * @lphenom-build shared,kphp
 */
interface CommandInterface
{
    /**
     * Execute the command.
     *
     * @return int exit code (0 = success, non-zero = failure)
     */
    public function run(): int;
}
