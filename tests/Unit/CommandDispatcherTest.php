<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Tests\Unit;

use LPhenom\Migrate\CommandDispatcher;
use LPhenom\Migrate\MigrationRegistry;
use LPhenom\Migrate\Migrator;
use LPhenom\Migrate\SchemaRepository;
use LPhenom\Migrate\Tests\Stub\TestConnection;
use LPhenom\Migrate\Tests\Stub\TestMigration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Migrate\CommandDispatcher
 * @covers \LPhenom\Migrate\Command\MigrateCommand
 * @covers \LPhenom\Migrate\Command\RollbackCommand
 * @covers \LPhenom\Migrate\Command\StatusCommand
 */
final class CommandDispatcherTest extends TestCase
{
    private TestConnection $conn;
    private MigrationRegistry $registry;
    private Migrator $migrator;

    protected function setUp(): void
    {
        $this->conn     = new TestConnection();
        $this->registry = new MigrationRegistry();
        $repository     = new SchemaRepository($this->conn);
        $this->migrator = new Migrator($this->registry, $repository);
    }

    public function testDispatchNoArgsPrintsUsage(): void
    {
        $dispatcher = new CommandDispatcher(['migrate'], $this->migrator, $this->conn);

        ob_start();
        $code = $dispatcher->dispatch();
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Usage', $output);
    }

    public function testDispatchMigrateCommand(): void
    {
        $m1 = new TestMigration('20260101000001');
        $this->registry->register($m1);

        $dispatcher = new CommandDispatcher(['migrate', 'migrate'], $this->migrator, $this->conn);

        ob_start();
        $code = $dispatcher->dispatch();
        ob_end_clean();

        self::assertSame(0, $code);
        self::assertTrue($m1->upCalled);
    }

    public function testDispatchMigrateNothingToMigrate(): void
    {
        $dispatcher = new CommandDispatcher(['migrate', 'migrate'], $this->migrator, $this->conn);

        ob_start();
        $code = $dispatcher->dispatch();
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Nothing to migrate', $output);
    }

    public function testDispatchRollbackCommand(): void
    {
        $m1 = new TestMigration('20260101000001');
        $this->registry->register($m1);

        // Apply first
        $applyDispatcher = new CommandDispatcher(['migrate', 'migrate'], $this->migrator, $this->conn);
        ob_start();
        $applyDispatcher->dispatch();
        ob_end_clean();

        // Then rollback
        $rollbackDispatcher = new CommandDispatcher(['migrate', 'migrate:rollback'], $this->migrator, $this->conn);

        ob_start();
        $code = $rollbackDispatcher->dispatch();
        ob_end_clean();

        self::assertSame(0, $code);
        self::assertTrue($m1->downCalled);
    }

    public function testDispatchStatusCommand(): void
    {
        $m1 = new TestMigration('20260101000001');
        $this->registry->register($m1);

        $dispatcher = new CommandDispatcher(['migrate', 'migrate:status'], $this->migrator, $this->conn);

        ob_start();
        $code = $dispatcher->dispatch();
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('20260101000001', $output);
        self::assertStringContainsString('pending', $output);
    }

    public function testDispatchUnknownCommandPrintsUsage(): void
    {
        $dispatcher = new CommandDispatcher(['migrate', 'unknown:command'], $this->migrator, $this->conn);

        ob_start();
        $code = $dispatcher->dispatch();
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Usage', $output);
    }

    public function testDispatchStatusEmptyRegistry(): void
    {
        $dispatcher = new CommandDispatcher(['migrate', 'migrate:status'], $this->migrator, $this->conn);

        ob_start();
        $code = $dispatcher->dispatch();
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('No migrations', $output);
    }
}
