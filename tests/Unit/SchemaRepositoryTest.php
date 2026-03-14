<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Tests\Unit;

use LPhenom\Migrate\SchemaRepository;
use LPhenom\Migrate\Tests\Stub\TestConnection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Migrate\SchemaRepository
 */
final class SchemaRepositoryTest extends TestCase
{
    private TestConnection $conn;
    private SchemaRepository $repository;

    protected function setUp(): void
    {
        $this->conn       = new TestConnection();
        $this->repository = new SchemaRepository($this->conn);
        $this->repository->ensureTable();
    }

    public function testEnsureTableIsIdempotent(): void
    {
        // Calling twice must not throw
        $this->repository->ensureTable();

        self::assertSame('schema_migrations', $this->repository->getTable());
    }

    public function testGetAppliedEmpty(): void
    {
        self::assertSame([], $this->repository->getApplied());
    }

    public function testGetLastBatchZeroWhenEmpty(): void
    {
        self::assertSame(0, $this->repository->getLastBatch());
    }

    public function testRecordAndGetApplied(): void
    {
        $this->repository->record('20260101000001', 1);
        $this->repository->record('20260101000002', 1);

        $applied = $this->repository->getApplied();

        self::assertContains('20260101000001', $applied);
        self::assertContains('20260101000002', $applied);
        self::assertCount(2, $applied);
    }

    public function testGetLastBatchAfterRecord(): void
    {
        $this->repository->record('20260101000001', 1);
        $this->repository->record('20260101000002', 1);
        $this->repository->record('20260102000001', 2);

        self::assertSame(2, $this->repository->getLastBatch());
    }

    public function testGetByBatch(): void
    {
        $this->repository->record('20260101000001', 1);
        $this->repository->record('20260101000002', 1);
        $this->repository->record('20260102000001', 2);

        $batch1 = $this->repository->getByBatch(1);
        $batch2 = $this->repository->getByBatch(2);

        self::assertCount(2, $batch1);
        self::assertContains('20260101000001', $batch1);
        self::assertContains('20260101000002', $batch1);
        self::assertSame(['20260102000001'], $batch2);
    }

    public function testDelete(): void
    {
        $this->repository->record('20260101000001', 1);
        $this->repository->record('20260101000002', 1);

        $this->repository->delete('20260101000001');

        $applied = $this->repository->getApplied();

        self::assertNotContains('20260101000001', $applied);
        self::assertContains('20260101000002', $applied);
    }

    public function testCustomTableName(): void
    {
        $repo = new SchemaRepository($this->conn, 'custom_migrations');
        $repo->ensureTable();
        $repo->record('20260101000001', 1);

        self::assertSame(['20260101000001'], $repo->getApplied());
        self::assertSame('custom_migrations', $repo->getTable());
    }

    public function testGetByBatchReturnsDescendingOrder(): void
    {
        $this->repository->record('20260101000001', 1);
        $this->repository->record('20260101000003', 1);
        $this->repository->record('20260101000002', 1);

        $batch = $this->repository->getByBatch(1);

        // DESC order by version
        self::assertSame('20260101000003', $batch[0]);
        self::assertSame('20260101000002', $batch[1]);
        self::assertSame('20260101000001', $batch[2]);
    }
}
