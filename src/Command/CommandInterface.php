<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Command;

/**
 * Contract for a single CLI command.
 *
 * Compatible with PHP 8.1+ and KPHP.
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
