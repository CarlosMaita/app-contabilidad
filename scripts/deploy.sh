#!/usr/bin/env bash
# Deploy en el VPS: pull + build + migrate + restart de workers.
set -euo pipefail
cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml"

echo "==> Actualizando código"
git pull --ff-only

echo "==> Construyendo imágenes"
$COMPOSE build

echo "==> Levantando servicios"
$COMPOSE up -d --remove-orphans

echo "==> Migraciones"
$COMPOSE exec -T app php artisan migrate --force

echo "==> Reiniciando workers (toman el código nuevo)"
$COMPOSE restart queue scheduler

echo "==> Listo"
$COMPOSE ps
