#!/bin/sh
# Entrypoint del backend. Solo el proceso php-fpm prepara la aplicación;
# otros comandos (scheduler, artisan, composer) arrancan directamente.
set -e

if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint] Esperando a la base de datos ${DB_HOST}:${DB_PORT:-3306}..."
    i=0
    until php -r '
        try { new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); }
        catch (Throwable $e) { exit(1); }' ; do
        i=$((i+1))
        [ "$i" -ge 60 ] && { echo "[entrypoint] La base de datos no responde."; exit 1; }
        sleep 2
    done

    if [ -z "$APP_KEY" ]; then
        echo "[entrypoint] ERROR: APP_KEY no definida (php artisan key:generate --show)."; exit 1
    fi

    if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
        echo "[entrypoint] Migrando y sembrando catálogos/roles/permisos..."
        php artisan migrate --force
        php artisan db:seed --force
    fi

    if [ "${APP_ENV}" = "production" ]; then
        php artisan config:cache && php artisan route:cache && php artisan view:cache
    fi
fi

exec "$@"
