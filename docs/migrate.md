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
1. Объявлять класс в **глобальном пространстве имён** (без `namespace`)
2. Реализовывать `LPhenom\Db\Migration\MigrationInterface`
3. Не иметь конструктора с аргументами (или не иметь конструктора вовсе)

`MigrationLoader` определяет имя класса из имени файла через алгоритм
`filenameToClassName()` и создаёт экземпляр через `new $className()`.

```php
<?php
// database/migrations/20260318000001_create_users.php
declare(strict_types=1);

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;

final class Migration20260318000001CreateUsers implements MigrationInterface
{
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

    public function getVersion(): string
    {
        return '20260318000001';
    }
}
```

### Соглашение по именованию

| Составляющая | Формат | Пример |
|---|---|---|
| Имя файла | `YYYYMMDDNNNNNN_snake_case_name.php` | `20260318000001_create_users.php` |
| Версия (`getVersion()`) | `YYYYMMDDNNNNNN` (14 цифр) | `20260318000001` |
| Имя класса | `Migration{VERSION}{PascalCaseName}` | `Migration20260318000001CreateUsers` |

Алгоритм преобразования имени файла в имя класса (`MigrationLoader::filenameToClassName()`):

1. Убрать расширение `.php`
2. Разбить по `_`
3. Каждую часть — `ucfirst()`
4. Склеить с префиксом `Migration`

Пример: `20260318000001_create_users_table.php` → `Migration20260318000001CreateUsersTable`

---

## Команды

### `migrate:make <name>` — создать файл миграции

Генерирует новый файл миграции с правильной структурой.

```bash
vendor/bin/migrate migrate:make create_users_table
vendor/bin/migrate migrate:make add_email_to_users
vendor/bin/migrate migrate:make add_index_to_posts
```

Результат:
```
Created migration: database/migrations/20260318000001_create_users_table.php
```

Если в этот день уже создавались миграции — порядковый номер автоматически инкрементируется:

```
database/migrations/20260318000001_create_users_table.php   ← первая
database/migrations/20260318000002_add_email_to_users.php   ← вторая
database/migrations/20260318000003_add_index_to_posts.php   ← третья
```

Сгенерированный файл:

```php
<?php

declare(strict_types=1);

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;

final class Migration20260318000001CreateUsersTable implements MigrationInterface
{
    public function up(ConnectionInterface $conn): void
    {
        // TODO: implement
    }

    public function down(ConnectionInterface $conn): void
    {
        // TODO: implement
    }

    public function getVersion(): string
    {
        return '20260318000001';
    }
}
```

**Правила именования `<name>`:**

| Правило | Описание |
|---|---|
| Разрешены символы | `[a-z0-9_]` только строчные буквы, цифры и подчёркивания |
| Обязательный аргумент | Если не передан — ошибка, код `1` |
| Пробелы запрещены | `"add email to users"` → ошибка |
| Дефисы запрещены | `add-email-to-users` → ошибка |
| Автоматическая нормализация | **Отсутствует** — передавайте уже нормализованное имя |

**Особенности:**
- Версия — `YYYYMMDDNNNNNN`: `YYYYMMDD` — текущая дата, `NNNNNN` — 6-значный порядковый номер за день (начиная с `000001`)
- Имя файла: `{version}_{name}.php`, класс: `Migration{version}{PascalCaseName}`
- Автоматически создаёт директорию если не существует
- Проверяет уникальность: если файл уже существует — ошибка, код `1`
- Не требует подключения к базе данных

### `migrate` — применить все ожидающие миграции

```bash
vendor/bin/migrate migrate
# или с явным конфигом:
vendor/bin/migrate migrate --config=migrate.php --path=database/migrations
```

Вывод:
```
Migrated:  20260318000001
Migrated:  20260318000002

Applied 2 migration(s).
```

### `migrate:rollback` — откатить последний batch

```bash
vendor/bin/migrate migrate:rollback
```

Вывод:
```
Rolled back: 20260318000002
Rolled back: 20260318000001

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
20260318000001                                     applied
20260318000002                                     applied
20260318000003                                     pending
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
use LPhenom\Migrate\MigrationLoader;
use LPhenom\Migrate\SchemaRepository;
use LPhenom\Migrate\Migrator;

// 1. Создайте registry и загрузите миграции из папки (PHP/shared-hosting режим)
//    MigrationLoader определяет имя класса из имени файла и делает new $className()
$registry = new MigrationRegistry();
$loader   = new MigrationLoader(__DIR__ . '/database/migrations');
$loader->load($registry);

// 2. Или зарегистрируйте вручную (для KPHP-совместимого кода)
$registry->register(new Migration20260318000001CreateUsersTable());

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
| **Shared hosting (PHP)** | `MigrationLoader` — авто через `new $className()` | PDO (PdoMySqlConnection) |
| **KPHP binary** | Явный `require_once` + `$registry->register(new MigrationXXX())` | FFI (FfiMySqlConnection) |

---

### Режим 1: Shared hosting (PHP 8.1+)

Используется файл `bin/migrate` с конфигом `migrate.php`:

```bash
# Создать файл миграции
vendor/bin/migrate migrate:make create_users_table

# Применить миграции
vendor/bin/migrate migrate --config=migrate.php --path=database/migrations

# Откатить последний batch
vendor/bin/migrate migrate:rollback --config=migrate.php

# Показать статус
vendor/bin/migrate migrate:status --config=migrate.php
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

`MigrationLoader` автоматически загружает все `.php`-файлы из директории, определяет имя класса
через `MigrationLoader::filenameToClassName()` и регистрирует их в реестре.

---

### Режим 2: KPHP compiled binary

В KPHP все PHP-файлы компилируются в C++ → статический бинарник **без PHP runtime**.
`MigrationLoader` (использует `new $className()`) **недоступен** в KPHP — динамическое
создание классов запрещено компилятором. Все миграции регистрируются явно в entrypoint.

**Workflow:**

#### Шаг 1: Создайте файлы миграций

Файлы миграций — обычный PHP без `namespace` и без `MigrationAutoRegistrar`:

```php
<?php
// database/migrations/20260318000001_create_users.php
declare(strict_types=1);

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;

final class Migration20260318000001CreateUsers implements MigrationInterface
{
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

    public function getVersion(): string
    {
        return '20260318000001';
    }
}
```

> Файлы миграций создаются командой `migrate:make` в PHP-режиме и уже имеют правильный формат.

#### Шаг 2: Создайте KPHP entrypoint файл

```php
<?php
// build/kphp-entrypoint.php
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

// lphenom/migrate (MigrationLoader исключён: использует new $className() — запрещено в KPHP)
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

// Явно подключаем файлы миграций (без MigrationLoader)
require_once __DIR__ . '/../database/migrations/20260318000001_create_users.php';
require_once __DIR__ . '/../database/migrations/20260318000002_add_users_index.php';
// ... добавляйте новые миграции сюда

// Подключение к БД через FFI (KPHP-native)
$conn = new \LPhenom\Db\Driver\FfiMySqlConnection(
    $_ENV['DB_HOST'] ?? 'localhost',
    (int) ($_ENV['DB_PORT'] ?? 3306),
    $_ENV['DB_NAME'] ?? 'myapp',
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASS'] ?? ''
);

// Явная регистрация миграций (имена классов совпадают с filenameToClassName())
$registry = new \LPhenom\Migrate\MigrationRegistry();
$registry->register(new Migration20260318000001CreateUsers());
$registry->register(new Migration20260318000002AddUsersIndex());
// ... добавляйте новые миграции сюда

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

| Команда | Статус | Примечание |
|---|---|---|
| `migrate` | ✅ | Применяет ожидающие миграции |
| `migrate:rollback` | ✅ | Откатывает последний batch |
| `migrate:status` | ✅ | Показывает статус всех миграций |
| `migrate:make` | ❌ | Только в PHP-режиме — генерирует PHP-файлы |

**Добавление новой миграции в KPHP-проект:**

1. Создайте файл миграции через `vendor/bin/migrate migrate:make my_migration` (в PHP-режиме)
2. Добавьте `require_once` на новый файл в entrypoint
3. Добавьте `$registry->register(new Migration{VERSION}{PascalCaseName}())` в entrypoint
4. Перекомпилируйте бинарник: `kphp -d /build/kphp-out -M cli build/kphp-entrypoint.php`

> Имя класса всегда можно определить из имени файла через `MigrationLoader::filenameToClassName('20260318000001_my_migration.php')` → `Migration20260318000001MyMigration`

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
