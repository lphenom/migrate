# lphenom/migrate — Usage Guide

`lphenom/migrate` — CLI инструмент для управления миграциями базы данных в приложениях LPhenom.

Поддерживает PHP 8.1+ (shared hosting) и компиляцию через KPHP.

---

## Установка

```bash
composer require lphenom/migrate
```

---

## Создание конфигурационного файла

Создайте `migrate.php` в корне проекта. Файл должен возвращать `ConnectionInterface` или массив с ключом `connection`:

### Вариант 1 — возвращает `ConnectionInterface` напрямую

```php
<?php
declare(strict_types=1);

use LPhenom\Db\Driver\ConnectionFactory;

return ConnectionFactory::create([
    'driver'   => 'pdo_mysql',
    'host'     => $_ENV['DB_HOST'] ?? 'localhost',
    'port'     => (int)($_ENV['DB_PORT'] ?? 3306),
    'dbname'   => $_ENV['DB_NAME'] ?? 'myapp',
    'user'     => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASS'] ?? '',
]);
```

### Вариант 2 — массив с дополнительными настройками

```php
<?php
declare(strict_types=1);

use LPhenom\Db\Driver\ConnectionFactory;

return [
    'connection' => ConnectionFactory::create([
        'driver'   => 'pdo_mysql',
        'host'     => 'localhost',
        'dbname'   => 'myapp',
        'user'     => 'root',
        'password' => 'secret',
    ]),
    'path'  => __DIR__ . '/database/migrations',
    'table' => 'schema_migrations',
];
```

---

## Структура папки миграций

По умолчанию миграции загружаются из `database/migrations/`.

Каждый файл миграции ДОЛЖЕН:
1. Реализовывать `LPhenom\Db\Migration\MigrationInterface`
2. Вызвать `MigrationAutoRegistrar::register()` на уровне файла

```php
<?php
// database/migrations/20260101000001_create_users.php
declare(strict_types=1);

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;
use LPhenom\Migrate\MigrationAutoRegistrar;

final class Migration20260101000001 implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20260101000001';
    }

    public function up(ConnectionInterface $conn): void
    {
        $conn->execute(
            'CREATE TABLE users (
                id      INTEGER NOT NULL,
                name    VARCHAR(255) NOT NULL,
                email   VARCHAR(255) NOT NULL,
                PRIMARY KEY (id)
            )'
        );
    }

    public function down(ConnectionInterface $conn): void
    {
        $conn->execute('DROP TABLE IF EXISTS users');
    }
}

MigrationAutoRegistrar::register(new Migration20260101000001());
```

> **Соглашение по именованию файлов:** `YYYYMMDDHHMMSS_description.php`  
> Версия в `getVersion()` должна совпадать с временным префиксом файла.

---

## Команды

### `migrate` — применить все ожидающие миграции

```bash
vendor/bin/migrate migrate
# или с явным конфигом:
vendor/bin/migrate migrate --config=migrate.php --path=database/migrations
```

Вывод:
```
Migrated:  20260101000001
Migrated:  20260101000002

Applied 2 migration(s).
```

### `migrate:rollback` — откатить последний batch

```bash
vendor/bin/migrate migrate:rollback
```

Вывод:
```
Rolled back: 20260101000002
Rolled back: 20260101000001

Rolled back 2 migration(s).
```

### `migrate:status` — статус всех миграций

```bash
vendor/bin/migrate migrate:status
```

Вывод:
```
Version                                            Status
------------------------------------------------------------
20260101000001                                     applied
20260101000002                                     applied
20260101000003                                     pending
```

---

## Параметры командной строки

| Параметр | Описание | По умолчанию |
|----------|----------|--------------|
| `--config=<file>` | Путь к конфигурационному файлу | `migrate.php` |
| `--path=<dir>` | Директория с миграциями | `database/migrations` |
| `--table=<name>` | Таблица для отслеживания | `schema_migrations` |

---

## Таблица `schema_migrations`

Создаётся автоматически при первом запуске:

```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
    version    VARCHAR(255) NOT NULL,
    batch      INTEGER      NOT NULL,
    applied_at DATETIME     NOT NULL,
    PRIMARY KEY (version)
)
```

| Колонка | Описание |
|---------|----------|
| `version` | Идентификатор миграции из `getVersion()` |
| `batch` | Номер батча — инкрементируется при каждом `migrate` |
| `applied_at` | Дата и время применения (UTC) |

### Batching

- Каждый запуск `migrate` создаёт новый batch (номер батча + 1 от предыдущего максимума).
- `migrate:rollback` откатывает **все** миграции последнего batch.
- Это позволяет группировать связанные миграции и откатывать их вместе.

---

## Использование как библиотеки (programmatic API)

```php
<?php
declare(strict_types=1);

use LPhenom\Migrate\MigrationRegistry;
use LPhenom\Migrate\MigrationAutoRegistrar;
use LPhenom\Migrate\MigrationLoader;
use LPhenom\Migrate\SchemaRepository;
use LPhenom\Migrate\Migrator;

// 1. Создайте registry и загрузите миграции из папки
$registry = new MigrationRegistry();
$loader   = new MigrationLoader(__DIR__ . '/database/migrations');
$loader->load($registry);

// 2. Или зарегистрируйте вручную (для KPHP-совместимого кода)
$registry->register(new CreateUsersTable());

// 3. Создайте репозиторий и Migrator
$repository = new SchemaRepository($conn, 'schema_migrations');
$migrator   = new Migrator($registry, $repository);
$migrator->prepare(); // создаёт таблицу schema_migrations

// 4. Применить миграции
$applied = $migrator->migrate($conn);

// 5. Откатить последний batch
$rolledBack = $migrator->rollback($conn);

// 6. Получить статус
$status = $migrator->status(); // array<string, string>: version => 'applied'|'pending'
```

---

## KPHP-совместимость

В KPHP-режиме автозагрузка не поддерживается. Вместо `MigrationLoader` регистрируйте миграции явно:

```php
// build/kphp-entrypoint.php
require_once __DIR__ . '/../src/...'; // все файлы через require_once

$registry = new \LPhenom\Migrate\MigrationRegistry();
$registry->register(new CreateUsersTable());
$registry->register(new AddUsersIndex());

$conn       = new \MyApp\FfiMySqlConnection(...);
$repository = new \LPhenom\Migrate\SchemaRepository($conn);
$migrator   = new \LPhenom\Migrate\Migrator($registry, $repository);
$dispatcher = new \LPhenom\Migrate\CommandDispatcher($argv, $migrator, $conn);

exit($dispatcher->dispatch());
```

> Подробнее о KPHP-совместимости — в [kphp-compatibility.md](./kphp-compatibility.md).

---

## Окружение разработки (Docker)

```bash
# Поднять контейнер
make up

# Запустить тесты
make test

# Проверить стиль кода
make lint

# Проверить KPHP-совместимость
make kphp-check
```

