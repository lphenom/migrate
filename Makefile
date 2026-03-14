.PHONY: up down test lint lint-fix analyse kphp-check phar-check install shell

# ── Docker ─────────────────────────────────────────────────────────────────────

up:
	docker compose up -d

down:
	docker compose down

shell:
	docker compose run --rm php sh

# ── Development (inside Docker) ────────────────────────────────────────────────

install:
	docker compose run --rm php composer install --no-interaction --prefer-dist

test:
	docker compose run --rm php vendor/bin/phpunit --colors=always

lint:
	docker compose run --rm php vendor/bin/php-cs-fixer fix --dry-run --diff

lint-fix:
	docker compose run --rm php vendor/bin/php-cs-fixer fix

analyse:
	docker compose run --rm php vendor/bin/phpstan analyse

# ── KPHP + PHAR verification ───────────────────────────────────────────────────

kphp-check:
	docker run --rm -v "$(PWD)":/app -w /app lphenom-migrate-dev sh -c \
		"composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts"
	docker build -f Dockerfile.check --target kphp-build -t lphenom-migrate-kphp-check .
	docker run --rm -v "$(PWD)":/app -w /app lphenom-migrate-dev sh -c \
		"composer install --no-interaction --prefer-dist --optimize-autoloader"

phar-check:
	docker run --rm -v "$(PWD)":/app -w /app lphenom-migrate-dev sh -c \
		"composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts"
	docker build -f Dockerfile.check --target phar-build -t lphenom-migrate-phar-check .
	docker run --rm -v "$(PWD)":/app -w /app lphenom-migrate-dev sh -c \
		"composer install --no-interaction --prefer-dist --optimize-autoloader"

check:
	docker run --rm -v "$(PWD)":/app -w /app lphenom-migrate-dev sh -c \
		"composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts"
	docker build -f Dockerfile.check -t lphenom-migrate-check .
	docker run --rm -v "$(PWD)":/app -w /app lphenom-migrate-dev sh -c \
		"composer install --no-interaction --prefer-dist --optimize-autoloader"

# ── Local (without Docker) ─────────────────────────────────────────────────────

test-local:
	php vendor/bin/phpunit --colors=always

lint-local:
	php vendor/bin/php-cs-fixer fix --dry-run --diff

analyse-local:
	php vendor/bin/phpstan analyse

