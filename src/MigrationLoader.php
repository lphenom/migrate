<?php

declare(strict_types=1);

namespace LPhenom\Migrate;

use LPhenom\Migrate\Exception\MigrateException;

/**
 * Загружает файлы миграций из директории и регистрирует их в MigrationRegistry.
 *
 * Каждый файл миграции должен объявлять класс в глобальном пространстве имён.
 * MigrationLoader определяет имя класса по имени файла через filenameToClassName(),
 * затем создаёт экземпляр через new $className() и регистрирует его в реестре.
 *
 * Пример файла миграции (database/migrations/20260318000001_create_users.php):
 *   <?php
 *   declare(strict_types=1);
 *   use LPhenom\Db\Contract\ConnectionInterface;
 *   use LPhenom\Db\Migration\MigrationInterface;
 *
 *   final class Migration20260318000001CreateUsers implements MigrationInterface {
 *       public function getVersion(): string { return '20260318000001'; }
 *       public function up(ConnectionInterface $conn): void { ... }
 *       public function down(ConnectionInterface $conn): void { ... }
 *   }
 *
 * Алгоритм filenameToClassName():
 *   1. Убрать расширение .php
 *   2. Разбить по '_'
 *   3. Каждую часть — ucfirst()
 *   4. Склеить с префиксом 'Migration'
 *
 *   Пример: 20260318000001_create_users_table.php → Migration20260318000001CreateUsersTable
 *
 * @lphenom-build shared
 *
 * Этот класс НЕ включается в KPHP-сборку и НЕ компилируется через kphp.
 *
 * Причина: метод load() выполняет `new $className`, где $className вычисляется в рантайме.
 * KPHP запрещает динамическое создание классов (new $variable()).
 *
 * В KPHP-режиме миграции регистрируются явно в build/kphp-entrypoint.php:
 *   require_once __DIR__ . '/../migrations/20260318000001_create_users_table.php';
 *   MigrationAutoRegistrar::register(new Migration20260318000001CreateUsersTable());
 *
 * Исключён из build/kphp-entrypoint.php.
 * Доступен только в PHP shared hosting режиме.
 */
final class MigrationLoader
{
    /**
     * @var string
     */
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Загружает все .php файлы из директории миграций и регистрирует их.
     *
     * @throws MigrateException если директория не существует или файл выбросил исключение
     */
    public function load(MigrationRegistry $registry): void
    {
        if (!is_dir($this->path)) {
            throw new MigrateException('Migrations directory not found: ' . $this->path);
        }

        $files = scandir($this->path);
        if ($files === false) {
            return;
        }

        $exception = null;
        try {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (substr($file, -4) !== '.php') {
                    continue;
                }

                require_once $this->path . '/' . $file;

                $className = self::filenameToClassName($file);
                $migration = new $className();
                $registry->register($migration);
            }
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw new MigrateException(
                'Failed to load migration: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Convert a migration filename to its class name.
     *
     * Algorithm:
     *  1. Strip .php extension
     *  2. Split by '_'
     *  3. ucfirst() each part
     *  4. Prepend 'Migration'
     *
     * Examples:
     *   20260318000001_create_users_table.php  → Migration20260318000001CreateUsersTable
     *   20260101000001_init.php                → Migration20260101000001Init
     */
    public static function filenameToClassName(string $filename): string
    {
        // Strip .php extension
        if (substr($filename, -4) === '.php') {
            $filename = substr($filename, 0, strlen($filename) - 4);
        }

        $parts  = explode('_', $filename);
        $result = '';
        foreach ($parts as $part) {
            $result .= ucfirst($part);
        }

        return 'Migration' . $result;
    }

    /**
     * Возвращает путь к директории миграций.
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
