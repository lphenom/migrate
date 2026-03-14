<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Tests\Stub;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;

/**
 * Simple migration stub for testing.
 */
final class TestMigration implements MigrationInterface
{
    /**
     * @var string
     */
    private string $version;

    /**
     * @var bool
     */
    public bool $upCalled = false;

    /**
     * @var bool
     */
    public bool $downCalled = false;

    public function __construct(string $version = '20260101000001')
    {
        $this->version = $version;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function up(ConnectionInterface $conn): void
    {
        $this->upCalled = true;
    }

    public function down(ConnectionInterface $conn): void
    {
        $this->downCalled = true;
    }
}
