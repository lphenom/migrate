<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Command;

/**
 * Generates a new migration file.
 *
 * Usage:
 *   vendor/bin/migrate migrate:make create_users_table
 *
 * Generated file name:
 *   database/migrations/20260318000001_create_users_table.php
 *
 * Generated class name:
 *   Migration20260318000001CreateUsersTable
 *
 * Version format: YYYYMMDDNNNNNN
 *   YYYYMMDD — current date
 *   NNNNNN   — 6-digit sequential number per day (starts at 000001, increments)
 *
 * KPHP notes:
 *  - date() supported in KPHP.
 *  - is_dir(), mkdir(), file_exists(), file_put_contents(), scandir() — KPHP CLI mode.
 *  - explode() + ucfirst() — KPHP-compatible.
 *  - No str_starts_with / str_ends_with / str_contains — not supported in KPHP.
 *  - fwrite(STDERR) — supported in KPHP CLI.
 *
 * @lphenom-build shared,kphp
 */
final class MakeCommand implements CommandInterface
{
    /**
     * @var string
     */
    private string $migrationsPath;

    /**
     * @var string
     */
    private string $name;

    public function __construct(string $migrationsPath, string $name)
    {
        $this->migrationsPath = $migrationsPath;
        $this->name           = $name;
    }

    public function run(): int
    {
        if ($this->name === '') {
            fwrite(STDERR, 'Error: migration name is required.' . PHP_EOL);
            fwrite(STDERR, 'Usage: migrate:make <name>' . PHP_EOL);
            fwrite(STDERR, 'Example: migrate:make create_users_table' . PHP_EOL);

            return 1;
        }

        if (!$this->isValidName($this->name)) {
            fwrite(STDERR, 'Error: migration name must contain only [a-z0-9_].' . PHP_EOL);
            fwrite(STDERR, 'Usage: migrate:make <name>' . PHP_EOL);
            fwrite(STDERR, 'Example: migrate:make create_users_table' . PHP_EOL);

            return 1;
        }

        // Ensure migrations directory exists
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        $datePrefix = date('Ymd');
        $sequence   = $this->nextSequence($datePrefix);
        $version    = $datePrefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        $className  = $this->buildClassName($version, $this->name);
        $fileName   = $version . '_' . $this->name . '.php';
        $filePath   = $this->migrationsPath . '/' . $fileName;

        if (file_exists($filePath)) {
            fwrite(STDERR, 'Error: migration already exists: ' . $filePath . PHP_EOL);

            return 1;
        }

        $content = $this->renderTemplate($version, $className);
        file_put_contents($filePath, $content);

        echo 'Created migration: ' . $this->migrationsPath . '/' . $fileName . PHP_EOL;

        return 0;
    }

    /**
     * Validate that name contains only [a-z0-9_].
     */
    private function isValidName(string $name): bool
    {
        $len = strlen($name);
        if ($len === 0) {
            return false;
        }

        for ($i = 0; $i < $len; $i++) {
            $ch = $name[$i];
            if (
                ($ch >= 'a' && $ch <= 'z')
                || ($ch >= '0' && $ch <= '9')
                || $ch === '_'
            ) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Find the next 6-digit sequential number for a given date prefix.
     * Scans the migrations directory for existing files with the same YYYYMMDD prefix.
     */
    private function nextSequence(string $datePrefix): int
    {
        $maxSeq = 0;

        $files = scandir($this->migrationsPath);
        if ($files === false) {
            return 1;
        }

        foreach ($files as $file) {
            if (substr($file, -4) !== '.php') {
                continue;
            }
            if (substr($file, 0, 8) !== $datePrefix) {
                continue;
            }
            // Version is characters 0-13: YYYYMMDDNNNNNN
            $seqStr = substr($file, 8, 6);
            $seq    = (int) $seqStr;
            if ($seq > $maxSeq) {
                $maxSeq = $seq;
            }
        }

        return $maxSeq + 1;
    }

    /**
     * Build PascalCase class name from version and snake_case name.
     * (20260318000001, create_users_table) → Migration20260318000001CreateUsersTable
     */
    private function buildClassName(string $version, string $name): string
    {
        $parts  = explode('_', $name);
        $pascal = '';
        foreach ($parts as $part) {
            $pascal .= ucfirst($part);
        }

        return 'Migration' . $version . $pascal;
    }

    /**
     * Render the migration file template.
     */
    private function renderTemplate(string $version, string $className): string
    {
        return '<?php' . PHP_EOL
            . PHP_EOL
            . 'declare(strict_types=1);' . PHP_EOL
            . PHP_EOL
            . 'use LPhenom\\Db\\Contract\\ConnectionInterface;' . PHP_EOL
            . 'use LPhenom\\Db\\Migration\\MigrationInterface;' . PHP_EOL
            . PHP_EOL
            . 'final class ' . $className . ' implements MigrationInterface' . PHP_EOL
            . '{' . PHP_EOL
            . '    public function up(ConnectionInterface $conn): void' . PHP_EOL
            . '    {' . PHP_EOL
            . '        // TODO: implement' . PHP_EOL
            . '    }' . PHP_EOL
            . PHP_EOL
            . '    public function down(ConnectionInterface $conn): void' . PHP_EOL
            . '    {' . PHP_EOL
            . '        // TODO: implement' . PHP_EOL
            . '    }' . PHP_EOL
            . PHP_EOL
            . '    public function getVersion(): string' . PHP_EOL
            . '    {' . PHP_EOL
            . '        return \'' . $version . '\';' . PHP_EOL
            . '    }' . PHP_EOL
            . '}' . PHP_EOL;
    }
}
