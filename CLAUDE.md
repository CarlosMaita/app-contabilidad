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
- **Contabilidades** (`App\Modules\Shared\Books\Book`, tabla `books`): los datos contables y operativos pertenecen a una contabilidad, no a un usuario. Cada usuario tiene su contabilidad personal (se crea al crear el usuario, `User::personalBook()`) y puede crear o sumarse a compartidas (miembros en `book_user`, rol `owner|member`; invitaciones pendientes por correo en `book_invitations`). `CurrentBook::id()` resuelve la contabilidad actual del usuario autenticado (`users.current_book_id`, validando membresía) y el trait `BelongsToBook` aplica el global scope por `book_id` y autocompleta `book_id`/`created_by`. Numeración de asientos y cierre de período (`books.accounting_closed_until`) son por contabilidad. En tests: `bookOf($user)` (helper de `tests/Pest.php`) y `withoutGlobalScopes()` para ver otras contabilidades.
- La orquestación entre módulos vive en `app/Books`: `BookService` (crear con plantilla Negocio u Hogar, invitar, quitar, salir, borrar, aportes por miembro = auxiliar «Aportes de X» bajo 3.1), `HouseholdTemplate`, `BookTransferService` (transferencia entre contabilidades: un asiento en cada una, enlazados en `BookTransfer`; revertir uno revierte ambos en `ReverseJournalEntry`) e `InBook::run()` para ejecutar servicios en otra contabilidad.
- Asientos contables inmutables: se corrigen con contra-asientos, nunca se editan ni borran.
- Al registrarse un usuario se precarga el plan de cuentas base en su contabilidad personal (listener de `Registered` → `DefaultChartOfAccounts::seedFor($book)`) y se acepta cualquier invitación pendiente a su correo.
- El P&L es multi-step (Bruta → EBITDA → EBIT → EBT → Neto con márgenes): cada cuenta de resultado lleva `pnl_section` (enum `PnlSection`, heredada del padre; las legadas sin sección caen en operativo). La cascada vive en `ReportService::CASCADE`.
- Flujo de efectivo (método indirecto, `ReportService::cashFlow`): cada cuenta de balance lleva `cash_flow_section` (enum `CashFlowSection`: efectivo, ajuste sin efectivo, capital de trabajo, inversión, financiamiento; si falta se hereda del padre y si no, default por tipo). Concilia por construcción con la variación real de las cuentas de efectivo. Tras cambios de clasificación en el seeder, `accounting:sync-chart` clasifica las cuentas de usuarios existentes.
- Cuentas auxiliares (`is_auxiliary`, ej. "CxC de Luis" bajo "Clientes por cobrar"): reciben la imputación pero no aparecen en mayor ni estados — la principal consolida (mayor agregado en `LedgerService`). Su detalle se consulta en `/subledgers` (Libros auxiliares). Un auxiliar no puede tener sub-cuentas.
- Demo: el botón «Ver como demo» del login (`POST /demo`) crea un usuario descartable `is_demo` con plan base + datos de ejemplo y una contabilidad «Familia (demo)» con aportes y gastos (`App\Demo\DemoSandbox` / `DemoData` / `DemoFamily`, fechas relativas a hoy, generadas por el flujo real de eventos). Se borra al salir y las vencidas (24 h) se purgan al crear otra demo o con `demo:purge`. Usuario demo fijo para local: `php artisan db:seed --class=DemoSeeder` (no llamarlo desde `DatabaseSeeder`, que corre sin model events).
- Servidor MCP (`laravel/mcp`) en `/mcp` (`routes/ai.php`): `app/Mcp/Servers/ContabilidadServer.php` + una clase por tool en `app/Mcp/Tools` (base `AccountingTool`), delgadas sobre los servicios. Auth `auth:mcp-token,api`: tokens personales del Perfil (tabla de Sanctum vía `App\Mcp\PersonalTokens`, sin el trait de Sanctum en `User`) u OAuth de Passport para el conector de claude.ai (`Mcp::oauthRoutes()`, vista `mcp/authorize`). El guard fija `Auth::user()`, así `BelongsToBook` aísla la contabilidad actual del usuario (tools `contabilidades` / `usar_contabilidad` / `transferir_entre_contabilidades`); las cuentas demo se rechazan. Passport requiere `passport:keys` y la extensión `sodium`. Las reglas de alta de cuentas viven en `AccountManager` (compartidas con el Livewire). Ver `docs/mcp.md`.
- Ojo Blade: no usar bloques `@php ... @endphp` en vistas que también tienen `@php(...)` inline — el bloque queda sin compilar. Usar solo la forma inline o pasar datos desde el componente.

## Decisiones tomadas (de las "abiertas" del plan)

1. Livewire puro, sin Filament.
2. Moneda única por usuario en el MVP.
3. Fecha contable manual permitida en la ejecución de operaciones.
4. IVA se resuelve con expresiones en el mapeo (sin módulo de impuestos).
5. Plan de cuentas base genérico en español precargado al registrarse.
6. Eventos no editables: anulación (contra-asiento) + nueva ejecución.
