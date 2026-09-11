# App de Contabilidad — contabilidad por eventos

Aplicación web de contabilidad donde **las operaciones se convierten en asientos**:
un módulo operativo define y ejecuta operaciones (pagos, cobros, préstamos…) y emite
eventos; un módulo contable los escucha y, mediante un **mapeo Debe/Haber configurable
con expresiones**, genera asientos de doble partida en el libro diario. Del diario se
derivan el mayor, el balance general y el estado de resultados.

```
┌──────────────────────┐   OperationExecuted    ┌──────────────────────┐
│   Módulo Operativo   │ ─────────────────────▶ │   Módulo Contable    │
│ - Tipos de operación │        (evento)        │ - Plan de cuentas    │
│ - Variables tipadas  │                        │ - Mapeo Debe/Haber   │
│ - Formulario dinámico│                        │ - Diario / Mayor     │
│ - Log de eventos     │                        │ - Balance / P&L      │
└──────────────────────┘                        └──────────────────────┘
```

## Funcionalidad

- **Plan de cuentas** jerárquico por usuario, con plan base precargado al registrarse.
- **Tipos de operación** con variables tipadas (`decimal`, `integer`, `string`, `date`,
  `boolean`, `account`) y formulario de ejecución generado dinámicamente.
- **Mapeo contable versionado** con expresiones seguras (`monto / 1.21`,
  `round(...)` — symfony/expression-language con whitelist), cuenta fija o tomada
  del payload, previsualización con chequeo de balance y **procesamiento retroactivo**
  de eventos sin mapear.
- **Asientos inmutables**: balance exacto con bcmath, numeración correlativa por
  usuario, corrección solo por contra-asiento; anulación de operaciones trazable.
- **Libros y estados**: diario con buscador, mayor con saldo acumulado, balance
  general (con verificación `Activo = Pasivo + Patrimonio + Resultado` en pantalla)
  y P&L, todos exportables a **PDF y XLSX**.
- **Extras**: asientos manuales, cierre de período (bloqueo de fechas), dashboard
  con KPIs, multiusuario con scoping global por `user_id`.

## Stack

Laravel 12 · PHP 8.4 · Livewire 3 (Breeze) · Tailwind (design system propio
"Modernist") · Pest (93 tests) · SQLite en dev, PostgreSQL 16 + Redis en producción.

## Desarrollo

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan serve
```

Tests y lint:

```bash
php artisan test
./vendor/bin/pint
```

## Producción

Docker multi-stage (nginx + php-fpm + PostgreSQL + Redis + worker + scheduler,
TLS automático con Caddy). Guía completa en [docs/deploy.md](docs/deploy.md):

```bash
cp .env.production.example .env   # completar dominio, DB, SMTP
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

Deploys siguientes con `./scripts/deploy.sh`; backups con `./scripts/backup.sh`.

## Arquitectura

Monolito modular en `app/Modules/{Operations,Accounting,Shared}`: los módulos se
comunican **solo por eventos y contratos** (`Shared/Contracts`) — Operations nunca
importa modelos de Accounting. El plan de desarrollo completo está en
[docs/plan.md](docs/plan.md) y las notas de arquitectura en [CLAUDE.md](CLAUDE.md).
