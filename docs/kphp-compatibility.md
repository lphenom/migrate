# KPHP Compatibility — lphenom/migrate

Все правила и ограничения KPHP, специфичные для `lphenom/migrate`.

## Аннотация `@lphenom-build`

Каждый класс/интерфейс/исключение в `src/` помечается аннотацией `@lphenom-build` в docblock.
Она явно декларирует, в каких режимах сборки присутствует файл:

| Значение | Описание |
|---|---|
| `shared` | Только PHP shared hosting режим (Composer autoload, PHP runtime) |
| `kphp` | Включается в KPHP-сборку (`build/kphp-entrypoint.php`) |
| `none` | Не включается ни в один режим (только для dev/tooling) |

**Формат:** `@lphenom-build <targets>` — одно или несколько значений через запятую.

```php
// Работает в обоих режимах:
// @lphenom-build shared,kphp

// Только PHP shared hosting (не KPHP):
// @lphenom-build shared

// Только для разработки / tooling:
// @lphenom-build none
```

## KPHP-совместимые компоненты

| Компонент | Аннотация | Примечание |
|---|---|---|
| `MigrationRegistry` | `@lphenom-build shared,kphp` | Типизированный `array<string, MigrationInterface>` |
| `MigrationAutoRegistrar` | `@lphenom-build shared,kphp` | Static property + static method calls |
| `SchemaRepository` | `@lphenom-build shared,kphp` | `array<string, Param>`, явные null-проверки |
| `Migrator` | `@lphenom-build shared,kphp` | `sort()`, `in_array()`, `count()` |
| `CommandDispatcher` | `@lphenom-build shared,kphp` | `if/elseif` вместо `match` |
| `CommandInterface` | `@lphenom-build shared,kphp` | Простой интерфейс |
| `MakeCommand` | `@lphenom-build shared,kphp` | Компилируется; в KPHP-бинарнике `migrate:make` недоступна |
| `MigrateCommand` | `@lphenom-build shared,kphp` | Явные вызовы `Migrator` |
| `RollbackCommand` | `@lphenom-build shared,kphp` | Явные вызовы `Migrator` |
| `StatusCommand` | `@lphenom-build shared,kphp` | Явные вызовы `Migrator` |
| `MigrateException` | `@lphenom-build shared,kphp` | `RuntimeException` |
| `MigrationLoader` | `@lphenom-build shared` | Динамический `require_once` — только PHP runtime |

## Принятые решения для KPHP

### 1. `MigrationLoader` — PHP-only (`@lphenom-build shared`)

`MigrationLoader::load()` выполняет `require_once $this->path . '/' . $file`, где путь
вычисляется в рантайме из результатов `scandir()`. KPHP должен разрешить ВСЕ пути
`require_once` статически (compile-time constants) — динамический путь недопустим.

В KPHP-режиме миграции регистрируются явно в `build/kphp-entrypoint.php`:

```php
// ❌ ЗАПРЕЩЕНО в KPHP — MigrationLoader не включается в entrypoint
require_once $this->path . '/' . $file; // runtime path — KPHP не может разрешить

// ✅ ПРАВИЛЬНО для KPHP — статический путь в entrypoint
require_once __DIR__ . '/../migrations/20260101000001_create_users.php';
// файл сам вызывает: MigrationAutoRegistrar::register(new Migration20260101000001());
```

### 2. Нет динамической загрузки классов

Обычные migration runners делают `new $className()`. Это запрещено в KPHP.

**Решение:** Каждый файл миграции вызывает `MigrationAutoRegistrar::register()` на уровне файла:

```php
// ❌ ЗАПРЕЩЕНО в KPHP
require_once $file;
$migration = new $className(); // dynamic instantiation

// ✅ ПРАВИЛЬНО
require_once $file;
// файл сам вызывает: MigrationAutoRegistrar::register(new MyMigration());
```

### 3. `substr()` вместо `str_starts_with()`

```php
// ❌ ЗАПРЕЩЕНО в KPHP
if (str_starts_with($arg, '--config=')) { ... }

// ✅ ПРАВИЛЬНО (CommandDispatcher)
if (substr($arg, 0, 9) === '--config=') { ... }
```

### 4. `if/elseif` вместо `match`

```php
// ❌ ограниченная поддержка в KPHP
$result = match($command) { ... };

// ✅ ПРАВИЛЬНО (CommandDispatcher::dispatch())
if ($command === 'migrate') { ... }
elseif ($command === 'migrate:rollback') { ... }
```

### 5. Явная null-проверка после `MAX()`

```php
// ✅ SchemaRepository::getLastBatch()
$row = $this->conn->query('SELECT MAX(batch) AS b FROM ...')->fetchOne();
if ($row === null) { return 0; }
$val = $row['b'] ?? null;
if ($val === null) { return 0; }
return (int) $val;
```

### 6. `try/catch` с ловом `\Throwable` — не `try/finally`

```php
// ✅ MigrationLoader::load()
$exception = null;
try {
    foreach ($files as $file) { require_once ...; }
} catch (\Throwable $e) {
    $exception = $e;
}
MigrationAutoRegistrar::clearRegistry(); // cleanup всегда
if ($exception !== null) { throw new MigrateException(...); }
```

### 7. `array<string, Param>` — однородный типизированный массив

```php
// ✅ SchemaRepository::record()
$this->conn->execute('INSERT INTO ...', [
    ':version'    => ParamBinder::str($version),
    ':batch'      => ParamBinder::int($batch),
    ':applied_at' => ParamBinder::str($appliedAt),
]);
```

### 8. KPHP entrypoint — явная регистрация без MigrationLoader

В `build/kphp-entrypoint.php` используется прямая регистрация (`@lphenom-build shared` файлы исключены):

```php
$registry = new \LPhenom\Migrate\MigrationRegistry();
$registry->register(new KphpExampleMigration());
// MigrationLoader НЕ используется — @lphenom-build shared
```

## Структура entrypoint для KPHP

```php
// build/kphp-entrypoint.php — только файлы с @lphenom-build shared,kphp
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/Param.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/ParamBinder.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ResultInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/TransactionCallbackInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ConnectionInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Migration/MigrationInterface.php';
require_once __DIR__ . '/../src/Exception/MigrateException.php';   // @lphenom-build shared,kphp
require_once __DIR__ . '/../src/MigrationRegistry.php';            // @lphenom-build shared,kphp
require_once __DIR__ . '/../src/MigrationAutoRegistrar.php';       // @lphenom-build shared,kphp
// src/MigrationLoader.php — ИСКЛЮЧЁН (@lphenom-build shared)
require_once __DIR__ . '/../src/SchemaRepository.php';             // @lphenom-build shared,kphp
require_once __DIR__ . '/../src/Migrator.php';                     // @lphenom-build shared,kphp
require_once __DIR__ . '/../src/Command/CommandInterface.php';     // @lphenom-build shared,kphp
require_once __DIR__ . '/../src/Command/MakeCommand.php';          // @lphenom-build shared,kphp
require_once __DIR__ . '/../src/Command/MigrateCommand.php';       // @lphenom-build shared,kphp
require_once __DIR__ . '/../src/Command/RollbackCommand.php';      // @lphenom-build shared,kphp
require_once __DIR__ . '/../src/Command/StatusCommand.php';        // @lphenom-build shared,kphp
require_once __DIR__ . '/../src/CommandDispatcher.php';            // @lphenom-build shared,kphp
```

## Ссылки

- [KPHP vs PHP differences](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [lphenom/db — ConnectionInterface](https://github.com/lphenom/db)

