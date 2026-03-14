<?php

declare(strict_types=1);

namespace LPhenom\Migrate;

use DateTimeImmutable;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Param\ParamBinder;

/**
 * Manages the schema_migrations tracking table.
 *
 * Schema:
 *   version    VARCHAR(255) NOT NULL  — migration version from getVersion()
 *   batch      INTEGER      NOT NULL  — batch number, incremented per migrate run
 *   applied_at DATETIME     NOT NULL  — UTC timestamp of application
 *   PRIMARY KEY (version)
 *
 * KPHP notes:
 *  - No constructor property promotion or readonly — explicit property declarations used.
 *  - array<string, Param> for named SQL parameters — KPHP-compatible homogeneous array.
 *  - MAX() may return null when table is empty; handled with explicit null check.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class SchemaRepository
{
    /**
     * @var ConnectionInterface
     */
    private ConnectionInterface $conn;

    /**
     * @var string
     */
    private string $table;

    public function __construct(ConnectionInterface $conn, string $table = 'schema_migrations')
    {
        $this->conn  = $conn;
        $this->table = $table;
    }

    /**
     * Ensure the schema_migrations table exists (idempotent).
     */
    public function ensureTable(): void
    {
        $this->conn->execute(
            'CREATE TABLE IF NOT EXISTS ' . $this->table . ' ('
            . 'version    VARCHAR(255) NOT NULL, '
            . 'batch      INTEGER      NOT NULL, '
            . 'applied_at DATETIME     NOT NULL, '
            . 'PRIMARY KEY (version)'
            . ')'
        );
    }

    /**
     * Return all applied migration versions in ascending order.
     *
     * @return string[]
     */
    public function getApplied(): array
    {
        $rows = $this->conn->query(
            'SELECT version FROM ' . $this->table . ' ORDER BY version ASC'
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $val = $row['version'] ?? null;
            if ($val !== null) {
                $result[] = (string) $val;
            }
        }

        return $result;
    }

    /**
     * Return the highest batch number currently stored (0 if table is empty).
     */
    public function getLastBatch(): int
    {
        $row = $this->conn->query(
            'SELECT MAX(batch) AS b FROM ' . $this->table
        )->fetchOne();

        if ($row === null) {
            return 0;
        }

        $val = $row['b'] ?? null;
        if ($val === null) {
            return 0;
        }

        return (int) $val;
    }

    /**
     * Return migration versions belonging to a given batch, newest-first (for rollback).
     *
     * @return string[]
     */
    public function getByBatch(int $batch): array
    {
        $rows = $this->conn->query(
            'SELECT version FROM ' . $this->table . ' WHERE batch = :batch ORDER BY version DESC',
            [':batch' => ParamBinder::int($batch)]
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $val = $row['version'] ?? null;
            if ($val !== null) {
                $result[] = (string) $val;
            }
        }

        return $result;
    }

    /**
     * Record a migration as applied.
     */
    public function record(string $version, int $batch): void
    {
        $appliedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->conn->execute(
            'INSERT INTO ' . $this->table . ' (version, batch, applied_at) VALUES (:version, :batch, :applied_at)',
            [
                ':version'    => ParamBinder::str($version),
                ':batch'      => ParamBinder::int($batch),
                ':applied_at' => ParamBinder::str($appliedAt),
            ]
        );
    }

    /**
     * Remove a migration record (mark as reverted).
     */
    public function delete(string $version): void
    {
        $this->conn->execute(
            'DELETE FROM ' . $this->table . ' WHERE version = :version',
            [':version' => ParamBinder::str($version)]
        );
    }

    /**
     * Return the table name used for tracking.
     */
    public function getTable(): string
    {
        return $this->table;
    }
}
