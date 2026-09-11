#!/bin/sh
set -e

# Cachear configuración, rutas y vistas con el .env real del contenedor.
php artisan storage:link --force > /dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
