<?php

declare(strict_types=1);

namespace LPhenom\Migrate;

use LPhenom\Migrate\Exception\MigrateException;

/**
 * Загружает файлы миграций из директории и регистрирует их в MigrationRegistry.
 *
 * Каждый файл миграции ДОЛЖЕН вызывать MigrationAutoRegistrar::register() на уровне файла,
 * чтобы самостоятельно зарегистрироваться в активном реестре. Это позволяет избежать
 * динамической загрузки классов (new $className()), но само по себе требует интерпретатора PHP.
 *
 * Пример файла миграции (database/migrations/20260101000001_create_users.php):
 *   <?php
 *   declare(strict_types=1);
 *   use LPhenom\Migrate\MigrationAutoRegistrar;
 *   use LPhenom\Db\Contract\ConnectionInterface;
 *   use LPhenom\Db\Migration\MigrationInterface;
 *
 *   final class Migration20260101000001 implements MigrationInterface {
 *       public function getVersion(): string { return '20260101000001'; }
 *       public function up(ConnectionInterface $conn): void { ... }
 *       public function down(ConnectionInterface $conn): void { ... }
 *   }
 *   MigrationAutoRegistrar::register(new Migration20260101000001());
 *
 * @kphp-incompatible
 *
 * Этот класс НЕ включается в KPHP-сборку и НЕ компилируется через kphp.
 *
 * Причина: метод load() выполняет `require_once $this->path . '/' . $file`, где путь
 * вычисляется в рантайме из результатов scandir(). KPHP компилирует PHP → C++ на этапе
 * сборки и обязан разрешить ВСЕ пути require_once статически (compile-time constants).
 * Динамический путь, зависящий от файловой системы в рантайме, недопустим для KPHP.
 *
 * В KPHP-режиме миграции регистрируются явно в build/kphp-entrypoint.php:
 *   require_once __DIR__ . '/../migrations/20260101000001_create_users.php';
 *   // файл сам вызывает: MigrationAutoRegistrar::register(new Migration20260101000001());
 *
 * Исключён из build/kphp-entrypoint.php.
 * Доступен только в PHP (shared hosting) режиме.
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
     * Load all .php files from the migrations directory and register them.
     *
     * @throws MigrateException if the directory does not exist or a file throws
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

        MigrationAutoRegistrar::setRegistry($registry);

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
            }
        } catch (\Throwable $e) {
            $exception = $e;
        }

        MigrationAutoRegistrar::clearRegistry();

        if ($exception !== null) {
            throw new MigrateException(
                'Failed to load migration: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Return the configured migrations directory path.
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
