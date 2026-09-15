# Despliegue en serve.upsofte.com (HestiaCP)

Producción real de `contable.upsofte.com` en el VPS `162.35.113.182`
(Ubuntu 26.04, **HestiaCP 1.10**, usuario `upsofte`). Por los recursos del
servidor (1.6 GB RAM, 1 CPU) se usa la instalación **nativa**, no Docker:

- nginx de Hestia con la plantilla **laravel** (docroot → `public/`).
- PHP-FPM 8.5 (pool del usuario `upsofte`).
- MySQL: base `upsofte_contable`, usuario `upsofte_contable`.
- Cola `sync`, sesiones y caché en `database` (sin Redis ni worker).
- Correo por `sendmail` (exim de Hestia) para el reset de contraseña.

Ruta de la app: `/home/upsofte/web/contable.upsofte.com/public_html`
(el `.env` vive ahí; no está en el repo).

## Actualizar la app (deploy de una versión nueva)

Los assets se compilan localmente (el servidor no tiene Node):

```bash
npm run build
scp -r public/build root@162.35.113.182:/tmp/vite-build
ssh root@162.35.113.182 'APP=/home/upsofte/web/contable.upsofte.com/public_html && \
  cd $APP && sudo -u upsofte git pull --ff-only && \
  sudo -u upsofte composer install --no-dev --no-interaction --optimize-autoloader && \
  sudo -u upsofte php artisan migrate --force && \
  rm -rf $APP/public/build && mv /tmp/vite-build $APP/public/build && chown -R upsofte:upsofte $APP/public/build && \
  find $APP/public/build -type d -exec chmod 755 {} + && find $APP/public/build -type f -exec chmod 644 {} + && \
  sudo -u upsofte php artisan config:cache && sudo -u upsofte php artisan route:cache && sudo -u upsofte php artisan view:cache'
```

## SSL

Con el DNS `contable.upsofte.com → 162.35.113.182` resolviendo:

```bash
ssh root@162.35.113.182 '/usr/local/hestia/bin/v-add-letsencrypt-domain upsofte contable.upsofte.com "" yes'
```

(el último `yes` redirige HTTP→HTTPS; Hestia renueva el certificado solo).
Nota: `SESSION_SECURE_COOKIE=true` — el login no funciona por HTTP plano,
el SSL es requisito.

## Operación

- Logs: `storage/logs/laravel-*.log` (canal daily) y los de nginx en
  `/var/log/nginx/domains/contable.upsofte.com.*`.
- Backups: Hestia hace backup diario del usuario `upsofte` (web + DB).
- Completar plan de cuentas de usuarios existentes tras un cambio del
  seeder: `sudo -u upsofte php artisan accounting:sync-chart`.
