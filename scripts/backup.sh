#!/usr/bin/env bash
# Backup de PostgreSQL con pg_dump. Pensado para cron diario, por ejemplo:
#   0 3 * * * /ruta/al/repo/scripts/backup.sh >> /var/log/contabilidad-backup.log 2>&1
# Conserva los últimos 14 dumps. Copiá los archivos a almacenamiento externo
# (S3, rclone, otro servidor) desde BACKUP_DIR.
set -euo pipefail
cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml"
BACKUP_DIR="${BACKUP_DIR:-./backups}"
KEEP="${KEEP:-14}"

source <(grep -E '^(DB_DATABASE|DB_USERNAME)=' .env)

mkdir -p "$BACKUP_DIR"
FILE="$BACKUP_DIR/contabilidad-$(date +%Y%m%d-%H%M%S).sql.gz"

echo "==> Dump a $FILE"
$COMPOSE exec -T db pg_dump -U "$DB_USERNAME" "$DB_DATABASE" | gzip > "$FILE"

echo "==> Rotando (conservo $KEEP)"
ls -1t "$BACKUP_DIR"/contabilidad-*.sql.gz | tail -n +$((KEEP + 1)) | xargs -r rm --

echo "==> OK: $(du -h "$FILE" | cut -f1)"
