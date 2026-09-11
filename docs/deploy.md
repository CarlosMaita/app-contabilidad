# Despliegue en un VPS con Docker

## Arquitectura

```
Internet ──▶ caddy (80/443, TLS automático Let's Encrypt)
                │
                ▼
             web (nginx, sirve public/ y estáticos)
                │ fastcgi
                ▼
             app (php-fpm)  ──▶ db (PostgreSQL 16)
             queue (worker) ──▶ redis (colas, caché, sesiones)
             scheduler
```

Todas las imágenes salen del mismo `docker/Dockerfile` multi-stage:
assets (Vite) → vendor (Composer sin dev) → app (php-fpm con
pdo_pgsql, bcmath, intl, gd, zip, opcache, redis) → web (nginx con los
estáticos copiados).

## Primer despliegue

1. Instalar Docker + el plugin de Compose en el VPS y clonar el repo.

2. Configurar el entorno:

   ```bash
   cp .env.production.example .env
   # Completar: APP_DOMAIN, ACME_EMAIL, APP_URL, DB_PASSWORD, MAIL_*
   ```

3. Generar la clave de la app:

   ```bash
   docker compose -f docker-compose.prod.yml build app
   docker compose -f docker-compose.prod.yml run --rm --no-deps app php artisan key:generate --show
   # Pegar el valor en APP_KEY del .env
   ```

4. Apuntar el DNS del dominio al VPS (Caddy emite el certificado solo
   cuando el dominio resuelve a la máquina).

5. Levantar y migrar:

   ```bash
   docker compose -f docker-compose.prod.yml up -d --build
   docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
   ```

## Deploys siguientes

```bash
./scripts/deploy.sh
```

Hace: `git pull` → build → `up -d` → `migrate --force` → restart de
`queue` y `scheduler` (los workers no toman código nuevo sin reinicio).

## Backups

```bash
./scripts/backup.sh          # dump manual
crontab -e
# 0 3 * * * /ruta/al/repo/scripts/backup.sh >> /var/log/contabilidad-backup.log 2>&1
```

Los dumps quedan en `./backups` (rotación: 14). Copialos afuera del VPS
(rclone/S3/scp) — un backup en la misma máquina no es un backup.

### Restauración

```bash
gunzip -c backups/contabilidad-YYYYMMDD-HHMMSS.sql.gz | \
  docker compose -f docker-compose.prod.yml exec -T db psql -U "$DB_USERNAME" "$DB_DATABASE"
```

(Sobre una base vacía: `docker compose ... exec db dropdb/createdb` antes,
o borrar el volumen `db_data`.)

## Logs y operación

```bash
docker compose -f docker-compose.prod.yml logs -f app queue   # errores de la app y worker
docker compose -f docker-compose.prod.yml ps                  # estado
```

- La app loguea a stderr (`LOG_CHANNEL=stderr`).
- Los jobs fallidos quedan en la tabla `failed_jobs`
  (`php artisan queue:failed` / `queue:retry all`).

## Hardening incluido

- `APP_DEBUG=false`, `expose_php=Off`, cabeceras nosniff/frame en nginx.
- Base y Redis sin puertos publicados (solo la red interna de Docker).
- Cookies `secure` (`SESSION_SECURE_COOKIE=true`) y proxies confiables
  configurados para que las URLs se generen con https.
- OPcache con `validate_timestamps=0` (el código solo cambia con deploy).
- Rate limiting de login/registro (Breeze) y verificación de email
  obligatoria: configurar `MAIL_*` o nadie puede operar.
