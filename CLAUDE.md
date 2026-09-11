# App de Contabilidad

Aplicación de contabilidad basada en eventos: el módulo **Operations** ejecuta operaciones y emite eventos; el módulo **Accounting** los escucha y genera asientos de doble partida según un mapeo configurable. Plan completo en `docs/plan.md`.

## Stack

- Laravel 12, PHP 8.4, Livewire 3 (Breeze stack Livewire/Volt), Tailwind, Alpine.
- Tests con Pest (`php artisan test`). Lint con Pint (`./vendor/bin/pint`). Ojo: no correr tests con la config cacheada (`php artisan optimize:clear` antes si se cacheó).
- Dev local: SQLite (`database/database.sqlite`) y cola `sync`. Producción: PostgreSQL 16 + Redis en Docker — ver `docs/deploy.md` (Dockerfile multi-stage en `docker/`, `docker-compose.prod.yml`, deploy con `scripts/deploy.sh`, backups con `scripts/backup.sh`).
- Si se agregan vistas Blade o clases Tailwind en enums dentro de `app/Modules`, ya están cubiertas por `tailwind.config.js` (content incluye `app/Modules/**`); no usar clases dinámicas concatenadas.

## Entorno local (Windows)

PHP y Composer no están en el PATH global. Instalados vía winget en:

```
$phpDir = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe"
& "$phpDir\php.exe" artisan test
& "$phpDir\php.exe" "$phpDir\composer.phar" require ...
```

(En una shell nueva `php` puede resolver directo si el PATH de usuario ya se recargó.)

## Arquitectura

- Módulos en `app/Modules/{Operations,Accounting,Shared}`, cada uno con su ServiceProvider registrado en `bootstrap/providers.php`. Migraciones y vistas viven dentro de cada módulo (`Database/Migrations`, `Views` con namespace `operations::` / `accounting::`).
- Comunicación entre módulos **solo por eventos**; Operations nunca importa modelos de Accounting.
- Todo modelo de negocio usa el trait `App\Modules\Shared\Traits\BelongsToUser` (global scope por `user_id` + autocompletado al crear). En tests, usar `withoutGlobalScopes()` para ver datos de otros usuarios.
- Asientos contables inmutables: se corrigen con contra-asientos, nunca se editan ni borran.
- Al registrarse un usuario se precarga un plan de cuentas base (listener de `Registered` → `DefaultChartOfAccounts`).
- El P&L es multi-step (Bruta → EBITDA → EBIT → EBT → Neto con márgenes): cada cuenta de resultado lleva `pnl_section` (enum `PnlSection`, heredada del padre; las legadas sin sección caen en operativo). La cascada vive en `ReportService::CASCADE`.
- Ojo Blade: no usar bloques `@php ... @endphp` en vistas que también tienen `@php(...)` inline — el bloque queda sin compilar. Usar solo la forma inline o pasar datos desde el componente.

## Decisiones tomadas (de las "abiertas" del plan)

1. Livewire puro, sin Filament.
2. Moneda única por usuario en el MVP.
3. Fecha contable manual permitida en la ejecución de operaciones.
4. IVA se resuelve con expresiones en el mapeo (sin módulo de impuestos).
5. Plan de cuentas base genérico en español precargado al registrarse.
6. Eventos no editables: anulación (contra-asiento) + nueva ejecución.
