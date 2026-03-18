<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Tests\Unit;

use LPhenom\Migrate\Exception\MigrateException;
use LPhenom\Migrate\MigrationAutoRegistrar;
use LPhenom\Migrate\MigrationLoader;
use LPhenom\Migrate\MigrationRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Migrate\MigrationLoader
 * @covers \LPhenom\Migrate\MigrationAutoRegistrar
 */
final class MigrationLoaderTest extends TestCase
{
    /**
     * @var string
     */
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lphenom_migrate_test_' . uniqid('', true);
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Cleanup temp files
        if (is_dir($this->tmpDir)) {
            $files = scandir($this->tmpDir);
            if ($files !== false) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    unlink($this->tmpDir . DIRECTORY_SEPARATOR . $file);
                }
            }
            rmdir($this->tmpDir);
        }
    }

    public function testLoadEmptyDirectory(): void
    {
        $registry = new MigrationRegistry();
        $loader   = new MigrationLoader($this->tmpDir);
        $loader->load($registry);

        self::assertSame([], $registry->versions());
    }

    public function testLoadSingleMigration(): void
    {
        $this->writeMigrationFile('20260801000001_create_users.php', '20260801000001');

        $registry = new MigrationRegistry();
        $loader   = new MigrationLoader($this->tmpDir);
        $loader->load($registry);

        self::assertSame(['20260801000001'], $registry->versions());
    }

    public function testLoadMultipleMigrations(): void
    {
        $this->writeMigrationFile('20260802000001_create_users.php', '20260802000001');
        $this->writeMigrationFile('20260802000002_create_posts.php', '20260802000002');
        $this->writeMigrationFile('20260802000003_add_index.php', '20260802000003');

        $registry = new MigrationRegistry();
        $loader   = new MigrationLoader($this->tmpDir);
        $loader->load($registry);

        self::assertCount(3, $registry->versions());
    }

    public function testSkipsNonPhpFiles(): void
    {
        $this->writeMigrationFile('20260803000001_create_users.php', '20260803000001');
        file_put_contents($this->tmpDir . '/README.txt', 'should be ignored');
        file_put_contents($this->tmpDir . '/migration.sql', 'SELECT 1');

        $registry = new MigrationRegistry();
        $loader   = new MigrationLoader($this->tmpDir);
        $loader->load($registry);

        self::assertSame(1, $registry->count());
    }

    public function testThrowsOnMissingDirectory(): void
    {
        $this->expectException(MigrateException::class);

        $registry = new MigrationRegistry();
        $loader   = new MigrationLoader('/nonexistent/path/to/migrations');
        $loader->load($registry);
    }

    public function testAutoRegistrarThrowsOutsideLoadCycle(): void
    {
        $this->expectException(MigrateException::class);

        $migration = new \LPhenom\Migrate\Tests\Stub\TestMigration('20260101000001');
        MigrationAutoRegistrar::register($migration);
    }

    public function testGetPath(): void
    {
        $loader = new MigrationLoader('/some/path');

        self::assertSame('/some/path', $loader->getPath());
    }

    public function testFilenameToClassName(): void
    {
        self::assertSame(
            'Migration20260318000001CreateUsersTable',
            MigrationLoader::filenameToClassName('20260318000001_create_users_table.php')
        );
        self::assertSame(
            'Migration20260101000001Init',
            MigrationLoader::filenameToClassName('20260101000001_init.php')
        );
        self::assertSame(
            'Migration20260318000001CreateUsersTable',
            MigrationLoader::filenameToClassName('20260318000001_create_users_table')
        );
    }

    // ---- helpers ----

    /**
     * Write a migration PHP file with class name matching MigrationLoader::filenameToClassName().
     * Uses unique date prefixes per version to avoid "class already declared" PHP errors
     * when multiple test methods run in the same process.
     */
    private function writeMigrationFile(string $filename, string $version): void
    {
        $className = MigrationLoader::filenameToClassName($filename);

        $content = <<<PHP
<?php
declare(strict_types=1);

use LPhenom\\Db\\Contract\\ConnectionInterface;
use LPhenom\\Db\\Migration\\MigrationInterface;

final class {$className} implements MigrationInterface
{
    public function getVersion(): string { return '{$version}'; }
    public function up(ConnectionInterface \$conn): void {}
    public function down(ConnectionInterface \$conn): void {}
}
PHP;

        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . $filename, $content);
    }
}
