# =============================================================================
# Dockerfile — LPhenom Migrate dev environment
#
# PHP 8.1-alpine with Composer 2.9.5
# All tooling runs inside Docker — nothing from the host machine is used.
# =============================================================================

FROM php:8.1-cli-alpine AS base

# Install system deps
RUN apk add --no-cache \
        bash \
        git \
        unzip \
        sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Install Composer (pinned version)
COPY --from=composer:2.9.5 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ── deps stage ─────────────────────────────────────────────────────────────────

FROM base AS deps

COPY composer.json ./

RUN composer install \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader

# ── dev stage ──────────────────────────────────────────────────────────────────

FROM base AS dev

COPY --from=deps /app/vendor /app/vendor
COPY . .

# Make bin executable
RUN chmod +x bin/migrate

