<?php

declare(strict_types=1);

namespace LPhenom\Migrate;

use LPhenom\Db\Migration\MigrationInterface;

/**
 * Registry of named migrations.
 *
 * Migrations are indexed by their version string (from getVersion()).
 * This is the KPHP-compatible way to store migrations — no dynamic class loading,
 * no callable arrays, only explicit registration via register().
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class MigrationRegistry
{
    /**
     * @var array<string, MigrationInterface>
     */
    private array $migrations = [];

    /**
     * Register a migration under its own version key.
     *
     * Note: PHP auto-converts numeric string keys (e.g. "20260101000001")
     * to integer keys internally.  get() / has() / versions() handle this
     * transparently — all public methods accept and return string versions.
     */
    public function register(MigrationInterface $migration): void
    {
        $this->migrations[$migration->getVersion()] = $migration;
    }

    /**
     * Return a migration by version, or null if not found.
     *
     * Works correctly even when the version string looks numeric,
     * because PHP's array lookup coerces the string to int automatically.
     */
    public function get(string $version): ?MigrationInterface
    {
        $m = $this->migrations[$version] ?? null;

        return $m;
    }

    /**
     * Check whether a version is registered.
     */
    public function has(string $version): bool
    {
        return array_key_exists($version, $this->migrations);
    }

    /**
     * Return all registered version strings in insertion order.
     *
     * KPHP / PHP note: version strings like "20260101000001" are numeric,
     * so PHP auto-converts them to integer array keys when used as array keys.
     * We cast back to string here so callers always receive string[].
     *
     * @return string[]
     */
    public function versions(): array
    {
        $result = [];
        foreach ($this->migrations as $key => $migration) {
            $result[] = (string) $key;
        }

        return $result;
    }

    /**
     * Return the total number of registered migrations.
     */
    public function count(): int
    {
        return count($this->migrations);
    }
}
