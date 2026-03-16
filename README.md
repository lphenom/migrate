# lphenom/migrate

CLI инструмент миграций для приложений на [LPhenom](https://github.com/lphenom).

[![CI](https://github.com/lphenom/migrate/actions/workflows/ci.yml/badge.svg)](https://github.com/lphenom/migrate/actions)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## Возможности

- ✅ Применение ожидающих миграций (`migrate`)
- ✅ Откат последнего батча (`migrate:rollback`)
- ✅ Просмотр статуса всех миграций (`migrate:status`)
- ✅ Batching — группировка миграций по запускам
- ✅ Простой CLI без тяжёлых зависимостей
- ✅ KPHP-совместимость — компилируется в нативный бинарник
- ✅ Работает на shared hosting (PHP 8.1+)

## Установка

```bash
composer require lphenom/migrate
```

## Быстрый старт

1. Создайте `migrate.php` в корне проекта:

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

2. Создайте миграцию в `database/migrations/`:

```php
<?php
// database/migrations/20260101000001_create_users.php
declare(strict_types=1);
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;
use LPhenom\Migrate\MigrationAutoRegistrar;

final class Migration20260101000001 implements MigrationInterface {
    public function getVersion(): string { return '20260101000001'; }
    public function up(ConnectionInterface $conn): void {
        $conn->execute('CREATE TABLE users (id INTEGER NOT NULL, PRIMARY KEY (id))');
    }
    public function down(ConnectionInterface $conn): void {
        $conn->execute('DROP TABLE IF EXISTS users');
    }
}
MigrationAutoRegistrar::register(new Migration20260101000001());
```

3. Запустите:

```bash
vendor/bin/migrate migrate
vendor/bin/migrate migrate:status
vendor/bin/migrate migrate:rollback
```

## Документация

- [docs/migrate.md](docs/migrate.md) — полное руководство по использованию
- [docs/kphp-compatibility.md](docs/kphp-compatibility.md) — KPHP-совместимость

## Разработка

```bash
make install     # установить зависимости (Docker)
make test        # запустить тесты
make lint        # проверить стиль кода
make analyse     # PHPStan
make kphp-check  # проверить KPHP-компиляцию
```

## Требования

- PHP >= 8.1
- [lphenom/db](https://github.com/lphenom/db) ^0.1

## Лицензия

MIT — см. [LICENSE](LICENSE).
