<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Tests\Unit;

use LPhenom\Migrate\MigrationRegistry;
use LPhenom\Migrate\Migrator;
use LPhenom\Migrate\SchemaRepository;
use LPhenom\Migrate\Tests\Stub\TestConnection;
use LPhenom\Migrate\Tests\Stub\TestMigration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Migrate\Migrator
 */
final class MigratorTest extends TestCase
{
    private TestConnection $conn;
    private MigrationRegistry $registry;
    private SchemaRepository $repository;
    private Migrator $migrator;

    protected function setUp(): void
    {
        $this->conn       = new TestConnection();
        $this->registry   = new MigrationRegistry();
        $this->repository = new SchemaRepository($this->conn);
        $this->migrator   = new Migrator($this->registry, $this->repository);
        $this->migrator->prepare();
    }

    public function testGetPendingEmptyRegistryAndEmptyDb(): void
    {
        self::assertSame([], $this->migrator->getPending());
    }

    public function testGetPendingReturnsAllWhenNoneApplied(): void
    {
        $this->registry->register(new TestMigration('20260101000002'));
        $this->registry->register(new TestMigration('20260101000001'));

        $pending = $this->migrator->getPending();

        self::assertSame(['20260101000001', '20260101000002'], $pending);
    }

    public function testMigrateAppliesAll(): void
    {
        $m1 = new TestMigration('20260101000001');
        $m2 = new TestMigration('20260101000002');
        $this->registry->register($m1);
        $this->registry->register($m2);

        $applied = $this->migrator->migrate($this->conn);

        self::assertSame(['20260101000001', '20260101000002'], $applied);
        self::assertTrue($m1->upCalled);
        self::assertTrue($m2->upCalled);
    }

    public function testMigrateSkipsAlreadyApplied(): void
    {
        $m1 = new TestMigration('20260101000001');
        $m2 = new TestMigration('20260101000002');
        $this->registry->register($m1);
        $this->registry->register($m2);

        // First run
        $this->migrator->migrate($this->conn);

        // Register a third migration
        $m3 = new TestMigration('20260101000003');
        $this->registry->register($m3);

        // Second run — only m3 should be applied
        $applied = $this->migrator->migrate($this->conn);

        self::assertSame(['20260101000003'], $applied);
        self::assertTrue($m3->upCalled);
    }

    public function testMigrateReturnsEmptyWhenNoPending(): void
    {
        self::assertSame([], $this->migrator->migrate($this->conn));
    }

    public function testRollbackReturnedEmptyWhenNothingApplied(): void
    {
        self::assertSame([], $this->migrator->rollback($this->conn));
    }

    public function testRollbackLastBatch(): void
    {
        $m1 = new TestMigration('20260101000001');
        $m2 = new TestMigration('20260101000002');
        $m3 = new TestMigration('20260101000003');
        $this->registry->register($m1);
        $this->registry->register($m2);
        $this->registry->register($m3);

        $this->migrator->migrate($this->conn);  // batch 1: m1, m2, m3

        $rolledBack = $this->migrator->rollback($this->conn);

        self::assertCount(3, $rolledBack);
        self::assertTrue($m1->downCalled);
        self::assertTrue($m2->downCalled);
        self::assertTrue($m3->downCalled);

        // All should be pending again
        self::assertSame(
            ['20260101000001', '20260101000002', '20260101000003'],
            $this->migrator->getPending()
        );
    }

    public function testRollbackOnlyLastBatch(): void
    {
        $m1 = new TestMigration('20260101000001');
        $m2 = new TestMigration('20260101000002');
        $this->registry->register($m1);
        $this->registry->register($m2);

        $this->migrator->migrate($this->conn); // batch 1: m1, m2

        $m3 = new TestMigration('20260101000003');
        $this->registry->register($m3);
        $this->migrator->migrate($this->conn); // batch 2: m3

        $rolledBack = $this->migrator->rollback($this->conn);

        self::assertSame(['20260101000003'], $rolledBack);
        self::assertFalse($m1->downCalled);
        self::assertFalse($m2->downCalled);
        self::assertTrue($m3->downCalled);
    }

    public function testStatusShowsAllMigrations(): void
    {
        $m1 = new TestMigration('20260101000001');
        $m2 = new TestMigration('20260101000002');
        $this->registry->register($m1);
        $this->registry->register($m2);

        $this->migrator->migrate($this->conn); // apply m1, m2

        $m3 = new TestMigration('20260101000003');
        $this->registry->register($m3);

        $status = $this->migrator->status();

        self::assertSame('applied', $status['20260101000001']);
        self::assertSame('applied', $status['20260101000002']);
        self::assertSame('pending', $status['20260101000003']);
    }

    public function testStatusEmptyRegistryReturnsEmptyArray(): void
    {
        self::assertSame([], $this->migrator->status());
    }

    public function testBatchNumberIncrementsPerRun(): void
    {
        $m1 = new TestMigration('20260101000001');
        $this->registry->register($m1);
        $this->migrator->migrate($this->conn); // batch 1

        $m2 = new TestMigration('20260101000002');
        $this->registry->register($m2);
        $this->migrator->migrate($this->conn); // batch 2

        self::assertSame(2, $this->repository->getLastBatch());
        self::assertSame(['20260101000001'], $this->repository->getByBatch(1));
        self::assertSame(['20260101000002'], $this->repository->getByBatch(2));
    }
}
