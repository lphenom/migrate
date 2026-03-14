<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Tests\Unit;

use LPhenom\Migrate\MigrationRegistry;
use LPhenom\Migrate\Tests\Stub\TestMigration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Migrate\MigrationRegistry
 */
final class MigrationRegistryTest extends TestCase
{
    public function testEmptyRegistry(): void
    {
        $registry = new MigrationRegistry();

        self::assertSame([], $registry->versions());
        self::assertSame(0, $registry->count());
        self::assertFalse($registry->has('20260101000001'));
        self::assertNull($registry->get('20260101000001'));
    }

    public function testRegisterAndGet(): void
    {
        $registry  = new MigrationRegistry();
        $migration = new TestMigration('20260101000001');

        $registry->register($migration);

        self::assertTrue($registry->has('20260101000001'));
        self::assertSame($migration, $registry->get('20260101000001'));
        self::assertSame(1, $registry->count());
    }

    public function testVersionsReturnedInInsertionOrder(): void
    {
        $registry = new MigrationRegistry();
        $registry->register(new TestMigration('20260103000001'));
        $registry->register(new TestMigration('20260101000001'));
        $registry->register(new TestMigration('20260102000001'));

        self::assertSame(
            ['20260103000001', '20260101000001', '20260102000001'],
            $registry->versions()
        );
    }

    public function testRegisterOverwritesSameVersion(): void
    {
        $registry   = new MigrationRegistry();
        $migration1 = new TestMigration('20260101000001');
        $migration2 = new TestMigration('20260101000001');

        $registry->register($migration1);
        $registry->register($migration2);

        self::assertSame($migration2, $registry->get('20260101000001'));
        self::assertSame(1, $registry->count());
    }

    public function testGetReturnsNullForUnknownVersion(): void
    {
        $registry = new MigrationRegistry();

        self::assertNull($registry->get('unknown'));
    }
}
