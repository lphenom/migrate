<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Tests\Unit;

use LPhenom\Migrate\Command\MakeCommand;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Migrate\Command\MakeCommand
 */
final class MakeCommandTest extends TestCase
{
    /**
     * @var string
     */
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lphenom_make_test_' . uniqid('', true);
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir);
        if ($files !== false) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path)) {
                    $this->removeDir($path);
                } else {
                    unlink($path);
                }
            }
        }
        rmdir($dir);
    }

    public function testCreatesFileWithCorrectStructure(): void
    {
        $cmd  = new MakeCommand($this->tmpDir, 'create_users_table');
        $code = $cmd->run();

        self::assertSame(0, $code);

        $files = array_values(array_diff((array) scandir($this->tmpDir), ['.', '..']));
        self::assertCount(1, $files);

        $file    = (string) $files[0];
        $content = (string) file_get_contents($this->tmpDir . DIRECTORY_SEPARATOR . $file);

        // File name: YYYYMMDD000001_create_users_table.php (8-digit date + 6-digit seq)
        self::assertMatchesRegularExpression('/^\d{8}\d{6}_create_users_table\.php$/', $file);

        // Content checks
        self::assertStringContainsString('declare(strict_types=1)', $content);
        self::assertStringContainsString('implements MigrationInterface', $content);
        self::assertStringContainsString('public function up(ConnectionInterface $conn): void', $content);
        self::assertStringContainsString('public function down(ConnectionInterface $conn): void', $content);
        self::assertStringContainsString('public function getVersion(): string', $content);
        self::assertStringContainsString('use LPhenom\\Db\\Contract\\ConnectionInterface', $content);
        self::assertStringContainsString('use LPhenom\\Db\\Migration\\MigrationInterface', $content);
        // No MigrationAutoRegistrar in new template
        self::assertStringNotContainsString('MigrationAutoRegistrar', $content);
    }

    public function testCreatesDirectoryIfNotExists(): void
    {
        // Use a sub-directory that does NOT yet exist
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'migrations';
        $cmd  = new MakeCommand($path, 'init');
        $code = $cmd->run();

        self::assertSame(0, $code);
        self::assertTrue(is_dir($path));
        // tearDown() will recursively clean up $this->tmpDir including sub/migrations
    }

    public function testReturnsErrorForEmptyName(): void
    {
        $cmd  = new MakeCommand($this->tmpDir, '');
        $code = $cmd->run();

        self::assertSame(1, $code);
        $files = array_diff((array) scandir($this->tmpDir), ['.', '..']);
        self::assertCount(0, $files);
    }

    public function testRejectsNameWithSpaces(): void
    {
        $cmd  = new MakeCommand($this->tmpDir, 'Add Email To Users');
        $code = $cmd->run();

        self::assertSame(1, $code);
        $files = array_diff((array) scandir($this->tmpDir), ['.', '..']);
        self::assertCount(0, $files);
    }

    public function testRejectsNameWithDashes(): void
    {
        $cmd  = new MakeCommand($this->tmpDir, 'add-index-to-posts');
        $code = $cmd->run();

        self::assertSame(1, $code);
        $files = array_diff((array) scandir($this->tmpDir), ['.', '..']);
        self::assertCount(0, $files);
    }

    public function testVersionIsSequentialFormat(): void
    {
        $cmd  = new MakeCommand($this->tmpDir, 'test_migration');
        $code = $cmd->run();

        self::assertSame(0, $code);
        $files   = array_values(array_diff((array) scandir($this->tmpDir), ['.', '..']));
        $file    = (string) $files[0];

        // Version: YYYYMMDDNNNNNN (14 digits: 8-digit date + 6-digit seq)
        $version = substr($file, 0, 14);
        self::assertMatchesRegularExpression('/^\d{8}\d{6}$/', $version);

        // Sequence part must be 000001 for the first migration
        $seq = substr($version, 8, 6);
        self::assertSame('000001', $seq);

        $content = (string) file_get_contents($this->tmpDir . DIRECTORY_SEPARATOR . $file);
        self::assertStringContainsString("return '" . $version . "'", $content);
        // Class name includes version AND PascalCase name
        self::assertStringContainsString('Migration' . $version . 'TestMigration', $content);
    }

    public function testSequentialNumberingIncrementsPerDay(): void
    {
        $datePrefix = date('Ymd');

        // Create first migration manually with seq 000001
        $existingFile = $this->tmpDir . '/' . $datePrefix . '000001_first_migration.php';
        file_put_contents($existingFile, '<?php // existing');

        // Now generate a new one — should get seq 000002
        $cmd  = new MakeCommand($this->tmpDir, 'second_migration');
        $code = $cmd->run();

        self::assertSame(0, $code);
        $files = array_values(array_diff((array) scandir($this->tmpDir), ['.', '..', $datePrefix . '000001_first_migration.php']));
        self::assertCount(1, $files);

        $newFile = (string) $files[0];
        $seq     = substr($newFile, 8, 6);
        self::assertSame('000002', $seq);
    }

    public function testClassNameIncludesPascalCaseName(): void
    {
        $cmd  = new MakeCommand($this->tmpDir, 'create_users_table');
        $code = $cmd->run();

        self::assertSame(0, $code);
        $files   = array_values(array_diff((array) scandir($this->tmpDir), ['.', '..']));
        $file    = (string) $files[0];
        $version = substr($file, 0, 14);
        $content = (string) file_get_contents($this->tmpDir . DIRECTORY_SEPARATOR . $file);

        // Class must be Migration{VERSION}CreateUsersTable
        self::assertStringContainsString('final class Migration' . $version . 'CreateUsersTable', $content);
    }

    public function testGeneratedFileIsValidPhp(): void
    {
        $cmd  = new MakeCommand($this->tmpDir, 'test_valid_php');
        $code = $cmd->run();

        self::assertSame(0, $code);
        $files    = array_values(array_diff((array) scandir($this->tmpDir), ['.', '..']));
        $filePath = $this->tmpDir . DIRECTORY_SEPARATOR . (string) $files[0];

        // Check PHP syntax
        $output = shell_exec('php -l ' . escapeshellarg($filePath) . ' 2>&1');
        self::assertStringContainsString('No syntax errors detected', (string) $output);
    }

    public function testDispatcherRoutesMakeCommand(): void
    {
        // Test that CommandDispatcher routes migrate:make without a real connection
        $registry   = new \LPhenom\Migrate\MigrationRegistry();
        $conn       = new \LPhenom\Migrate\Tests\Stub\TestConnection();
        $repository = new \LPhenom\Migrate\SchemaRepository($conn);
        $migrator   = new \LPhenom\Migrate\Migrator($registry, $repository);

        $dispatcher = new \LPhenom\Migrate\CommandDispatcher(
            ['migrate', 'migrate:make', 'create_orders_table'],
            $migrator,
            $conn,
            $this->tmpDir
        );

        ob_start();
        $code = $dispatcher->dispatch();
        ob_end_clean();

        self::assertSame(0, $code);
        $files = array_diff((array) scandir($this->tmpDir), ['.', '..']);
        self::assertCount(1, $files);
    }
}
