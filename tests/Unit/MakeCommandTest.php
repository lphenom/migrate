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

        // File name: YYYYMMDDHHMMSS_create_users_table.php
        self::assertMatchesRegularExpression('/^\d{14}_create_users_table\.php$/', $file);

        // Content checks
        self::assertStringContainsString('declare(strict_types=1)', $content);
        self::assertStringContainsString('implements MigrationInterface', $content);
        self::assertStringContainsString('public function up(ConnectionInterface $conn): void', $content);
        self::assertStringContainsString('public function down(ConnectionInterface $conn): void', $content);
        self::assertStringContainsString('public function getVersion(): string', $content);
        self::assertStringContainsString('MigrationAutoRegistrar::register(', $content);
        self::assertStringContainsString('use LPhenom\\Db\\Contract\\ConnectionInterface', $content);
        self::assertStringContainsString('use LPhenom\\Db\\Migration\\MigrationInterface', $content);
        self::assertStringContainsString('use LPhenom\\Migrate\\MigrationAutoRegistrar', $content);
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
        // setUp() created tmpDir, so scandir is safe; no files should be created
        $files = array_diff((array) scandir($this->tmpDir), ['.', '..']);
        self::assertCount(0, $files);
    }

    public function testNormalisesNameWithSpaces(): void
    {
        $cmd  = new MakeCommand($this->tmpDir, 'Add Email To Users');
        $code = $cmd->run();

        self::assertSame(0, $code);
        $files = array_values(array_diff((array) scandir($this->tmpDir), ['.', '..']));
        self::assertMatchesRegularExpression('/^\d{14}_add_email_to_users\.php$/', (string) $files[0]);
    }

    public function testNormalisesNameWithDashes(): void
    {
        $cmd  = new MakeCommand($this->tmpDir, 'add-index-to-posts');
        $code = $cmd->run();

        self::assertSame(0, $code);
        $files = array_values(array_diff((array) scandir($this->tmpDir), ['.', '..']));
        self::assertMatchesRegularExpression('/^\d{14}_add_index_to_posts\.php$/', (string) $files[0]);
    }

    public function testVersionIsTimestampFormat(): void
    {
        $cmd  = new MakeCommand($this->tmpDir, 'test_migration');
        $code = $cmd->run();

        self::assertSame(0, $code);
        $files   = array_values(array_diff((array) scandir($this->tmpDir), ['.', '..']));
        $file    = (string) $files[0];
        $version = substr($file, 0, 14); // YYYYMMDDHHMMSS

        self::assertMatchesRegularExpression('/^\d{14}$/', $version);

        $content = (string) file_get_contents($this->tmpDir . DIRECTORY_SEPARATOR . $file);
        self::assertStringContainsString("return '" . $version . "'", $content);
        self::assertStringContainsString('Migration' . $version, $content);
    }

    public function testGeneratedFileIsValidPhp(): void
    {
        $cmd  = new MakeCommand($this->tmpDir, 'test_valid_php');
        $code = $cmd->run();

        self::assertSame(0, $code);
        $files   = array_values(array_diff((array) scandir($this->tmpDir), ['.', '..']));
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
