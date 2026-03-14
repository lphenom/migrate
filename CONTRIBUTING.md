# Contributing to lphenom/migrate

Thank you for considering contributing!

## Requirements

- PHP 8.1+
- Composer
- Docker (recommended for dev environment)

## Getting Started

```bash
git clone git@github.com:lphenom/migrate.git
cd migrate
composer install
```

Or with Docker:
```bash
make install
```

## Running Tests

```bash
# With Docker (recommended)
make test

# Local
php vendor/bin/phpunit
```

## Code Style

This project uses `php-cs-fixer` (PSR-12 + strict types):

```bash
# Check
make lint

# Auto-fix
make lint-fix
```

## Static Analysis

```bash
make analyse
```

## KPHP Compatibility Check

All code must compile under KPHP:

```bash
make kphp-check
```

## Guidelines

1. `declare(strict_types=1)` in every PHP file
2. No `Reflection`, `eval`, `$$var`, `new $class()`, dynamic loading
3. No `str_starts_with` / `str_ends_with` / `str_contains` (use `substr` / `strpos`)
4. No trailing commas in function calls (KPHP incompatible)
5. No `__destruct()` methods
6. No constructor property promotion or `readonly` properties
7. PHPDoc `@var array<K, V>` for all arrays
8. Small, focused commits — one logical change per commit
9. Tests for every new feature

## Commit Convention

```
chore: initial setup
feat(migrate): add migration loader
fix(schema): handle empty batch table
docs(migrate): update usage guide
test(migrator): add rollback batch test
```

## Pull Requests

- Branch from `main`
- All CI checks must pass (tests, lint, phpstan, KPHP check)
- Update docs if API changes

## License

MIT — see [LICENSE](LICENSE).

