# syntax=docker/dockerfile:1

# --- Tahap 1: dependensi PHP ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# --ignore-platform-req=ext-intl: base image composer:2 tidak punya ext-intl (Filament butuh itu),
# tapi image akhir (FrankenPHP) memasangnya lewat install-php-extensions di bawah. Tahap ini cuma
# resolve & unduh dependency, tidak menjalankan PHP yang perlu intl.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-req=ext-intl
COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- Tahap 2: aset frontend ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# --- Tahap 3: image akhir ---
FROM dunglas/frankenphp:php8.4-alpine
WORKDIR /app

RUN install-php-extensions pdo_pgsql redis intl opcache gd zip pcntl gmp

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
 && chown -R www-data:www-data storage bootstrap/cache

ENV SERVER_NAME=:8080
EXPOSE 8080

# Ganti health check bawaan base image (curl ke :2019, admin API Caddy yang sengaja kita matikan)
# dengan endpoint kita sendiri. Tanpa ini container selamanya "unhealthy" dan Traefik menolak routing.
HEALTHCHECK --interval=10s --timeout=3s --start-period=15s --retries=3 \
    CMD curl -f http://localhost:8080/up || exit 1

ENTRYPOINT ["entrypoint"]
