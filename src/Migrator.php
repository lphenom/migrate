<?php

declare(strict_types=1);

namespace LPhenom\Migrate;

use LPhenom\Db\Contract\ConnectionInterface;

/**
 * Core migration runner.
 *
 * Depends on:
 *  - MigrationRegistry  — source of all known migrations
 *  - SchemaRepository   — tracks applied migrations in the database
 *  - ConnectionInterface passed to migrate() / rollback() — used to run up()/down()
 *
 * The SchemaRepository's connection and the connection passed to migrate/rollback
 * are typically the same instance, but kept separate to allow flexibility.
 *
 * KPHP notes:
 *  - No Reflection, no dynamic class instantiation.
 *  - array<string, string> used for status() return — homogeneous types, KPHP-safe.
 *  - in_array() with strict=true is KPHP-compatible.
 *  - sort() on string[] is KPHP-compatible.
 *
 * @lphenom-build shared,kphp
 */
final class Migrator
{
    /**
     * @var MigrationRegistry
     */
    private MigrationRegistry $registry;

    /**
     * @var SchemaRepository
     */
    private SchemaRepository $repository;

    public function __construct(MigrationRegistry $registry, SchemaRepository $repository)
    {
        $this->registry   = $registry;
        $this->repository = $repository;
    }

    /**
     * Ensure the schema_migrations table exists.
     * Must be called before migrate(), rollback(), or status().
     */
    public function prepare(): void
    {
        $this->repository->ensureTable();
    }

    /**
     * Return versions of migrations not yet applied, sorted ascending.
     *
     * @return string[]
     */
    public function getPending(): array
    {
        $applied  = $this->repository->getApplied();
        $versions = $this->registry->versions();
        sort($versions);

        $pending = [];
        foreach ($versions as $version) {
            if (!in_array($version, $applied, true)) {
                $pending[] = $version;
            }
        }

        return $pending;
    }

    /**
     * Apply all pending migrations in version-ascending order.
     *
     * @return string[] versions of newly applied migrations
     */
    public function migrate(ConnectionInterface $conn): array
    {
        $pending = $this->getPending();
        if (count($pending) === 0) {
            return [];
        }

        $batch   = $this->repository->getLastBatch() + 1;
        $applied = [];

        foreach ($pending as $version) {
            $migration = $this->registry->get($version);
            if ($migration === null) {
                continue;
            }

            $migration->up($conn);
            $this->repository->record($version, $batch);
            $applied[] = $version;
        }

        return $applied;
    }

    /**
     * Rollback the last batch of migrations in reverse-version order.
     *
     * @return string[] versions of rolled-back migrations
     */
    public function rollback(ConnectionInterface $conn): array
    {
        $lastBatch = $this->repository->getLastBatch();
        if ($lastBatch === 0) {
            return [];
        }

        $versions   = $this->repository->getByBatch($lastBatch);
        $rolledBack = [];

        foreach ($versions as $version) {
            $migration = $this->registry->get($version);
            if ($migration === null) {
                continue;
            }

            $migration->down($conn);
            $this->repository->delete($version);
            $rolledBack[] = $version;
        }

        return $rolledBack;
    }

    /**
     * Return the status of all registered migrations.
     *
     * @return array<string, string> version => 'applied' | 'pending'
     */
    public function status(): array
    {
        $applied  = $this->repository->getApplied();
        $versions = $this->registry->versions();
        sort($versions);

        $result = [];
        foreach ($versions as $version) {
            if (in_array($version, $applied, true)) {
                $result[$version] = 'applied';
            } else {
                $result[$version] = 'pending';
            }
        }

        return $result;
    }
}
