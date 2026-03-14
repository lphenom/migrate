# KPHP Compatibility — lphenom/migrate

Все правила и ограничения KPHP, специфичные для `lphenom/migrate`.

## KPHP-совместимые компоненты

| Компонент | Статус |
|---|---|
| `MigrationRegistry` — типизированный массив `array<string, MigrationInterface>` | ✅ |
| `MigrationAutoRegistrar` — static property + static method calls | ✅ |
| `MigrationLoader` — `require_once $dynamicPath` (runtime path) | ❌ PHP-only |
| `SchemaRepository` — `array<string, Param>` params, явные null-проверки | ✅ |
| `Migrator` — `sort()`, `in_array()`, `count()` | ✅ |
| `CommandDispatcher` — `if/elseif` вместо `match` | ✅ |
| `SchemaRepository::getLastBatch()` — явная проверка `MAX()` на null | ✅ |

## Принятые решения для KPHP

### 1. Нет динамической загрузки классов

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

### 2. `substr()` вместо `str_starts_with()`

```php
// ❌ ЗАПРЕЩЕНО в KPHP
if (str_starts_with($arg, '--config=')) { ... }

// ✅ ПРАВИЛЬНО (CommandDispatcher)
if (substr($arg, 0, 9) === '--config=') { ... }
```

### 3. `if/elseif` вместо `match`

```php
// ❌ ограниченная поддержка в KPHP
$result = match($command) { ... };

// ✅ ПРАВИЛЬНО (CommandDispatcher::dispatch())
if ($command === 'migrate') { ... }
elseif ($command === 'migrate:rollback') { ... }
```

### 4. Явная null-проверка после `MAX()`

```php
// ✅ SchemaRepository::getLastBatch()
$row = $this->conn->query('SELECT MAX(batch) AS b FROM ...')->fetchOne();
if ($row === null) { return 0; }
$val = $row['b'] ?? null;
if ($val === null) { return 0; }
return (int) $val;
```

### 5. `try/catch` с ловом `\Throwable` — не `try/finally`

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

### 6. `array<string, Param>` — однородный типизированный массив

```php
// ✅ SchemaRepository::record()
$this->conn->execute('INSERT INTO ...', [
    ':version'    => ParamBinder::str($version),
    ':batch'      => ParamBinder::int($batch),
    ':applied_at' => ParamBinder::str($appliedAt)
]);
```

### 7. KPHP entrypoint — явная регистрация без MigrationLoader

В `build/kphp-entrypoint.php` используется прямая регистрация:

```php
$registry = new \LPhenom\Migrate\MigrationRegistry();
$registry->register(new KphpExampleMigration());
```

`MigrationLoader` не включается в KPHP entrypoint — он использует `require_once` с динамическим путём, что является PHP-only паттерном (для shared hosting).

## Структура entrypoint для KPHP

```php
// build/kphp-entrypoint.php
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/Param.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/ParamBinder.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ResultInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/TransactionCallbackInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ConnectionInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Migration/MigrationInterface.php';
require_once __DIR__ . '/../src/Exception/MigrateException.php';
require_once __DIR__ . '/../src/MigrationRegistry.php';
require_once __DIR__ . '/../src/MigrationAutoRegistrar.php';
// NOTE: MigrationLoader is NOT included — uses require_once $variable which KPHP cannot compile.
// In KPHP mode, register all migrations explicitly via MigrationRegistry::register().
require_once __DIR__ . '/../src/SchemaRepository.php';
require_once __DIR__ . '/../src/Migrator.php';
require_once __DIR__ . '/../src/Command/CommandInterface.php';
require_once __DIR__ . '/../src/Command/MigrateCommand.php';
require_once __DIR__ . '/../src/Command/RollbackCommand.php';
require_once __DIR__ . '/../src/Command/StatusCommand.php';
require_once __DIR__ . '/../src/CommandDispatcher.php';
```

## Ссылки

- [KPHP vs PHP differences](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [lphenom/db — ConnectionInterface](https://github.com/lphenom/db)

