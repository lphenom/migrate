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

### `migrate:make <name>` — создать файл миграции

Генерирует новый файл миграции с правильной структурой (аналог `php artisan make:migration` в Laravel).

```bash
vendor/bin/migrate migrate:make create_users_table
vendor/bin/migrate migrate:make "add email to users"
vendor/bin/migrate migrate:make add-index-to-posts --path=database/migrations
```

Результат:
```
Migration created: database/migrations/20260314123456_create_users_table.php
Class:             Migration20260314123456
Version:           20260314123456
```

Сгенерированный файл содержит готовый шаблон с `up()`, `down()`, `getVersion()` и автоматической регистрацией через `MigrationAutoRegistrar::register()`.

**Особенности:**
- Версия — `YmdHis` timestamp: `20260314123456`
- Имя файла: `{version}_{name}.php`, класс: `Migration{version}`
- Автоматически создаёт директорию если не существует
- Нормализует имя: пробелы/дефисы → подчёркивания, lowercase
- Не требует подключения к базе данных

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

`lphenom/migrate` полностью поддерживает два режима работы:

| Режим | Как загружаются миграции | Подключение к БД |
|---|---|---|
| **Shared hosting (PHP)** | `MigrationLoader` из директории | PDO (PdoMySqlConnection) |
| **KPHP binary** | Явный `require_once` + `MigrationRegistry::register()` | FFI (FfiMySqlConnection) |

---

### Режим 1: Shared hosting (PHP 8.1+)

Используется файл `bin/migrate` с конфигом `migrate.php`:

```bash
# Применить миграции
vendor/bin/migrate migrate --config=migrate.php --path=database/migrations

# Откатить последний batch
vendor/bin/migrate migrate:rollback --config=migrate.php

# Показать статус
vendor/bin/migrate migrate:status --config=migrate.php

# Создать файл миграции
vendor/bin/migrate migrate:make create_users_table
```

`migrate.php` должен возвращать `ConnectionInterface` или массив с ключом `connection`:

```php
<?php
use LPhenom\Db\Driver\ConnectionFactory;

return ConnectionFactory::create([
    'driver'   => 'pdo_mysql',
    'host'     => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'   => $_ENV['DB_NAME'] ?? 'myapp',
    'user'     => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASS'] ?? '',
]);
```

---

### Режим 2: KPHP compiled binary

В KPHP все PHP-файлы компилируются в C++ → статический бинарник **без PHP runtime**.
Поэтому динамическая загрузка PHP-файлов (`MigrationLoader`) недоступна в runtime.

**Workflow:**

#### Шаг 1: Создайте migration-файлы (PHP 8.1 синтаксис)

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
            'CREATE TABLE users (id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY (id))'
        );
    }

    public function down(ConnectionInterface $conn): void
    {
        $conn->execute('DROP TABLE IF EXISTS users');
    }
}

MigrationAutoRegistrar::register(new Migration20260101000001());
```

> **Важно:** `MigrationAutoRegistrar::register()` в KPHP entrypoint вызывается с `$registry` установленным
> через `MigrationAutoRegistrar::setRegistry()`, как и в PHP режиме. Или регистрируйте напрямую через `$registry->register()`.

#### Шаг 2: Создайте KPHP entrypoint файл

```php
<?php
// build/kphp-entrypoint.php — замените под свой проект
declare(strict_types=1);

// lphenom/db зависимости (в правильном порядке)
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/Param.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/ParamBinder.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ResultInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/TransactionCallbackInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ConnectionInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Migration/MigrationInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Driver/FfiMySqlHeader.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Driver/FfiMySqlResult.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Driver/FfiMySqlConnection.php';

// lphenom/migrate (MigrationLoader исключён: KPHP не поддерживает require_once $variable)
require_once __DIR__ . '/../src/Exception/MigrateException.php';
require_once __DIR__ . '/../src/MigrationRegistry.php';
require_once __DIR__ . '/../src/MigrationAutoRegistrar.php';
require_once __DIR__ . '/../src/SchemaRepository.php';
require_once __DIR__ . '/../src/Migrator.php';
require_once __DIR__ . '/../src/Command/CommandInterface.php';
require_once __DIR__ . '/../src/Command/MigrateCommand.php';
require_once __DIR__ . '/../src/Command/RollbackCommand.php';
require_once __DIR__ . '/../src/Command/StatusCommand.php';
require_once __DIR__ . '/../src/CommandDispatcher.php';

// Явно регистрируем все migration-файлы (без MigrationLoader)
require_once __DIR__ . '/../database/migrations/20260101000001_create_users.php';
require_once __DIR__ . '/../database/migrations/20260101000002_add_users_index.php';
// ... добавляйте новые миграции сюда

// Подключение к БД через FFI (KPHP-native)
$conn = new \LPhenom\Db\Driver\FfiMySqlConnection(
    $_ENV['DB_HOST'] ?? 'localhost',
    (int) ($_ENV['DB_PORT'] ?? 3306),
    $_ENV['DB_NAME'] ?? 'myapp',
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASS'] ?? ''
);

// Registry — наполняется через MigrationAutoRegistrar при require_once выше
$registry   = new \LPhenom\Migrate\MigrationRegistry();

// Файлы миграций вызвали MigrationAutoRegistrar::register() при require_once,
// но без setRegistry(). Поэтому используем прямую регистрацию:
$registry->register(new Migration20260101000001());
$registry->register(new Migration20260101000002());

$repository = new \LPhenom\Migrate\SchemaRepository($conn, 'schema_migrations');
$migrator   = new \LPhenom\Migrate\Migrator($registry, $repository);

$cliArgs = [];
foreach ($argv as $a) {
    $cliArgs[] = (string) $a;
}

$dispatcher = new \LPhenom\Migrate\CommandDispatcher($cliArgs, $migrator, $conn);
exit($dispatcher->dispatch());
```

#### Шаг 3: Скомпилируйте бинарник

```bash
# Через Docker (рекомендуется)
docker run --rm -v "$(pwd)":/build vkcom/kphp:latest \
    kphp -d /build/kphp-out -M cli /build/build/kphp-entrypoint.php

# Или через make (если настроено)
make kphp-check
```

#### Шаг 4: Используйте бинарник

```bash
# Показать help
./kphp-out/cli

# Применить миграции
DB_HOST=localhost DB_NAME=myapp DB_USER=root DB_PASS=secret \
    ./kphp-out/cli migrate

# Откатить последний batch
./kphp-out/cli migrate:rollback

# Статус
./kphp-out/cli migrate:status
```

**Что работает в KPHP binary:**

| Команда | Статус |
|---|---|
| `migrate` (apply pending) | ✅ |
| `migrate:rollback` | ✅ |
| `migrate:status` | ✅ |
| `migrate:make` | ❌ Только в PHP-режиме (генерирует PHP-файлы) |

**Добавление новой миграции в KPHP-проект:**

1. Создайте файл миграции (PHP-синтаксис)
2. Добавьте `require_once` в entrypoint
3. Добавьте `$registry->register(new MigrationXXX())` в entrypoint
4. Перекомпилируйте бинарник: `kphp -d ... -M cli entrypoint.php`

> **Примечание:** `migrate:make` генерирует заготовку migration-файла и доступен только в PHP (shared hosting) режиме. В KPHP binary генерировать PHP-файлы бессмысленно, так как для применения новых миграций бинарник всё равно нужно перекомпилировать.

> Подробнее о KPHP-совместимости и ограничениях — в [kphp-compatibility.md](./kphp-compatibility.md).

---

## Окружение разработки (Docker)

```bash
# Поднять контейнер
make up

# Запустить тесты
make test

# Проверить стиль кода
make lint

# Проверить KPHP-совместимость + PHAR build
make kphp-check
```

