# Plan de desarrollo — App de Contabilidad (Laravel monolito modular)

## 1. Visión general

Aplicación web de contabilidad basada en **eventos**: un módulo **Operativo** define y ejecuta operaciones (pagos, cobros, préstamos, compras, ingresos…) y emite eventos con las variables cargadas; un módulo **Contable** escucha esos eventos y, mediante un **mapeo contable** configurado por el usuario, genera asientos de doble partida en el libro diario. A partir del diario se derivan el libro mayor y los estados financieros (Balance general y P&L).

```
┌──────────────────────┐   OperationExecuted    ┌──────────────────────┐
│   Módulo Operativo   │ ─────────────────────▶ │   Módulo Contable    │
│                      │        (evento)        │                      │
│ - Tipos de operación │                        │ - Plan de cuentas    │
│ - Variables          │                        │ - Mapeo contable     │
│ - Ejecución (form)   │                        │ - Libro diario       │
│ - Log de eventos     │ ◀───────────────────── │ - Libro mayor        │
└──────────────────────┘  JournalEntryPosted    │ - Balance / P&L      │
                          (feedback opcional)   └──────────────────────┘
```

Principios de diseño:

- **Desacoplamiento**: el módulo operativo no conoce cuentas contables; solo emite eventos con un payload. El contable no conoce formularios; solo consume eventos.
- **Inmutabilidad contable**: un asiento registrado no se edita; se corrige con un contra-asiento (asiento de reversión).
- **Idempotencia**: cada evento genera como máximo un asiento (clave única `operation_execution_id` en `journal_entries`).
- **Multiusuario desde el día 1**: todo dato pertenece a un `user_id` (con opción de escalar a "empresa/tenant" más adelante).

---

## 2. Stack técnico

| Capa | Elección | Motivo |
|---|---|---|
| Backend | Laravel 12, PHP 8.3 | Monolito, ecosistema maduro |
| Frontend | Blade + **Livewire 3** + Tailwind CSS + Alpine.js | Formularios dinámicos (variables) sin SPA separada; un solo deploy |
| Auth | Laravel Breeze (stack Livewire) | Registro con correo/contraseña, verificación de email, reset |
| Base de datos | **PostgreSQL 16** (alternativa MySQL 8) | `NUMERIC` para montos, buen soporte de JSON para payloads |
| Cola / caché | Redis + Laravel Queues (Horizon opcional) | Procesar eventos → asientos de forma asíncrona y reintentable |
| Expresiones del mapeo | `symfony/expression-language` | Evaluar `monto * 0.21`, `total - iva`, etc. de forma segura |
| Exportación | `barryvdh/laravel-dompdf` (PDF), `maatwebsite/excel` (XLSX/CSV) | Descarga de estados y libros |
| Montos | `brick/money` o `decimal(18,2)` + `bcmath` | Evitar floats en contabilidad |
| Tests | Pest | Rápido de escribir, buena legibilidad |
| Infra | Docker Compose (nginx + php-fpm + postgres + redis + worker + scheduler) | Portable a cualquier VPS con Docker |

> Alternativa de aceleración: usar **Filament v3** para CRUDs de plan de cuentas, operaciones y mapeos. Reduce mucho el trabajo de UI a cambio de menos control visual. Se recomienda decidir en la Fase 0.

---

## 3. Arquitectura modular (dentro del monolito)

Estructura por módulos en `app/Modules`, sin paquete externo (opcionalmente `nwidart/laravel-modules`):

```
app/
├── Modules/
│   ├── Operations/
│   │   ├── Models/          (OperationType, OperationVariable, OperationExecution)
│   │   ├── Events/          (OperationExecuted)
│   │   ├── Actions/         (CreateOperationType, ExecuteOperation)
│   │   ├── Livewire/        (OperationTypeForm, ExecuteOperationForm, EventLog)
│   │   ├── Rules/           (validación dinámica según tipo de variable)
│   │   └── OperationsServiceProvider.php
│   ├── Accounting/
│   │   ├── Models/          (Account, AccountingMapping, MappingLine, JournalEntry, JournalLine)
│   │   ├── Listeners/       (GenerateJournalEntryFromOperation)
│   │   ├── Services/        (MappingResolver, JournalEntryBuilder, LedgerService, ReportService)
│   │   ├── Exceptions/      (UnbalancedEntryException, MissingMappingException)
│   │   ├── Livewire/        (ChartOfAccounts, MappingEditor, JournalBook, GeneralLedger, Reports)
│   │   ├── Exports/         (BalanceSheetExport, ProfitAndLossExport, ...)
│   │   └── AccountingServiceProvider.php
│   └── Shared/
│       ├── Money/           (helpers de montos)
│       └── Traits/          (BelongsToUser, scopes de tenant)
├── Models/User.php
routes/
├── web.php                  (incluye routes de cada módulo)
```

Reglas:

- Los módulos se comunican **solo por eventos y contratos** (`app/Modules/Shared/Contracts`). Operations nunca importa modelos de Accounting.
- El listener contable es **queued** (`ShouldQueue`) con reintentos; los fallos quedan en `failed_jobs` y visibles en el log de eventos con estado `failed`.

---

## 4. Modelo de datos

### 4.1 Usuarios
- `users`: id, name, email, password, email_verified_at, settings (json: moneda, formato de fecha, inicio ejercicio).

### 4.2 Módulo Operativo
- `operation_types`: id, user_id, name, code (slug), description, is_active.
- `operation_variables`: id, operation_type_id, name (`monto`), label, **type** (`decimal`, `integer`, `string`, `date`, `boolean`, `account` → permite elegir una cuenta del plan en tiempo de ejecución), is_required, default_value, sort_order.
- `operation_executions` (= **log de eventos**): id, user_id, operation_type_id, **payload** (json con las variables), executed_at (fecha contable), description, status (`pending`, `posted`, `failed`, `unmapped`), error_message, created_at.

### 4.3 Módulo Contable
- `accounts` (plan de cuentas): id, user_id, code (`1.1.01`), name, **type** (`asset`, `liability`, `equity`, `income`, `expense`), parent_id, is_postable (solo hojas reciben apuntes), is_active.
- `accounting_mappings`: id, user_id, operation_type_id, version, is_active, description_template (`"Ingreso por servicios a {cliente}"`).
- `mapping_lines`: id, mapping_id, **side** (`debit` / `credit`), account_id (nullable), account_variable (nullable, nombre de variable de tipo `account`), **amount_expression** (`monto`, `monto * 0.21`, `monto / 1.21`), memo_template, sort_order.
- `journal_entries` (libro de asientos / diario): id, user_id, number (correlativo por usuario), date, description, operation_execution_id (unique, nullable para asientos manuales), mapping_id, reverses_entry_id (nullable), status (`posted`, `reversed`), posted_at.
- `journal_lines`: id, journal_entry_id, account_id, debit `decimal(18,2)`, credit `decimal(18,2)`, memo. Restricción: una de las dos es 0.

Índices clave: `(user_id, date)` en entries, `(account_id, journal_entry_id)` en lines, `(user_id, code)` unique en accounts, `(user_id, number)` unique en entries.

### 4.4 Diagrama simplificado

```
users 1──∞ operation_types 1──∞ operation_variables
users 1──∞ operation_executions ∞──1 operation_types
users 1──∞ accounts (árbol via parent_id)
operation_types 1──∞ accounting_mappings 1──∞ mapping_lines ∞──1 accounts
operation_executions 1──0..1 journal_entries 1──∞ journal_lines ∞──1 accounts
```

---

## 5. Flujos principales

### 5.1 Crear operación con variables
1. El usuario define nombre y agrega variables (nombre, tipo, obligatoria).
2. Validación: nombres de variable únicos por operación, formato `snake_case`, al menos una variable numérica (recomendación, no bloqueo).

### 5.2 Ejecutar operación
1. Livewire renderiza un formulario dinámico a partir de `operation_variables` (input según tipo; select de cuentas para tipo `account`).
2. `ExecuteOperation` valida el payload con reglas generadas dinámicamente, guarda `operation_executions` con status `pending` y despacha `OperationExecuted($execution)`.
3. El log de eventos muestra la ejecución de inmediato con su estado.

### 5.3 Generar asiento (listener contable)
1. `GenerateJournalEntryFromOperation` busca el mapeo activo del `operation_type`. Si no existe → status `unmapped` (sin error).
2. `MappingResolver` evalúa cada `mapping_line`: resuelve cuenta (fija o desde variable) y monto (expresión sobre el payload).
3. `JournalEntryBuilder` arma el asiento, **valida balance** (Σ debe = Σ haber, tolerancia 0.00) y que las cuentas sean `is_postable`. Si no balancea → excepción, status `failed`, mensaje visible en el log.
4. Todo en una transacción; asigna número correlativo con bloqueo (`lockForUpdate`) para evitar duplicados.
5. Marca la ejecución como `posted` y emite `JournalEntryPosted`.

### 5.4 Configurar / modificar mapeo
- Editor con dos columnas (Debe / Haber), previsualización con valores de ejemplo y validación de balance **simbólica** cuando sea posible (mismas expresiones a ambos lados) y numérica con datos de prueba.
- Al **guardar un mapeo nuevo** para una operación: se ofrece "Procesar eventos pendientes" → job que recorre `operation_executions` con status `unmapped` y genera sus asientos (requisito: al configurar el mapeo se registran asientos).
- Al **modificar** un mapeo: se crea una nueva versión; los asientos existentes no se tocan. Opción explícita "Regenerar asientos de esta operación" = reversión de los anteriores + generación con la nueva versión (nunca borrado).

### 5.5 Anular una operación
- Genera un contra-asiento (`reverses_entry_id`) y marca la ejecución como anulada. Se mantiene la trazabilidad completa.

### 5.6 Libros y reportes
- **Libro diario**: listado de asientos ordenados por fecha/número, filtros por rango de fechas, buscador (número, descripción, cuenta, nombre de operación), expandible para ver líneas.
- **Libro mayor**: selección de cuenta (o todas), rango de fechas, saldo inicial, movimientos con saldo acumulado, saldo final.
- **Balance general** a una fecha: activos, pasivos, patrimonio (incluye resultado del período como línea calculada). Verificación `Activo = Pasivo + Patrimonio` mostrada en pantalla.
- **P&L (Estado de resultados)** por rango: ingresos, gastos, resultado neto, con agrupación por cuenta padre.
- Descarga en PDF y XLSX/CSV.

Cálculo de saldos: consulta agregada `SUM(debit) - SUM(credit)` por cuenta hasta la fecha (signo según tipo de cuenta). Para volúmenes altos, más adelante se puede agregar tabla de saldos materializados por mes.

---

## 6. Seguridad y multiusuario

- Global scope `BelongsToUser` en todos los modelos de negocio + policies por recurso.
- Verificación de email obligatoria antes de operar. Rate limiting en login/registro.
- Expresiones del mapeo evaluadas con `ExpressionLanguage` con **whitelist de funciones** (`round`, `abs`, `min`, `max`); nunca `eval`.
- Validación de CSRF (nativa), cabeceras seguras, cookies `secure` en producción.

---

## 7. Docker y despliegue

### 7.1 Servicios (`docker-compose.yml`)
| Servicio | Imagen / build | Función |
|---|---|---|
| `app` | Dockerfile propio (php:8.3-fpm-alpine + extensiones pdo_pgsql, bcmath, intl, gd, redis) | PHP-FPM |
| `web` | nginx:alpine | Reverse proxy → php-fpm, sirve `public/` |
| `db` | postgres:16-alpine | Datos (volumen persistente) |
| `redis` | redis:7-alpine | Colas, caché, sesiones |
| `queue` | misma imagen que `app` | `php artisan queue:work --tries=3` |
| `scheduler` | misma imagen que `app` | `php artisan schedule:work` |
| `caddy` (opcional) | caddy:2 | TLS automático con Let's Encrypt en lugar de nginx |

### 7.2 Dockerfile multi-stage
1. Stage `node`: `npm ci && npm run build` (assets Vite).
2. Stage `composer`: `composer install --no-dev --optimize-autoloader`.
3. Stage final: php-fpm con código, assets y vendor; `php artisan config:cache route:cache view:cache` en el entrypoint.

### 7.3 Despliegue en VPS
1. Instalar Docker + Docker Compose en el VPS.
2. Clonar repo, copiar `.env.production` → `.env`, generar `APP_KEY`.
3. `docker compose -f docker-compose.prod.yml up -d --build`.
4. `docker compose exec app php artisan migrate --force`.
5. Script `deploy.sh` (o Makefile) que encapsule: pull, build, migrate, restart de `queue`.
6. Backups: cron de `pg_dump` a volumen + copia externa (S3/rclone). Restauración documentada.
7. Logs a stdout/stderr para `docker compose logs`.

Entornos: `docker-compose.yml` (dev, con hot reload de Vite y xdebug opcional) y `docker-compose.prod.yml` (sin puertos de DB expuestos, `APP_DEBUG=false`).

---

## 8. Roadmap por fases

### Fase 0 — Setup (1–2 días)
- Proyecto Laravel 12, Breeze (Livewire), Tailwind, Pest.
- Docker Compose de desarrollo funcionando.
- Estructura de módulos y service providers. Decidir Filament sí/no.
- CI básica (GitHub Actions: lint + tests).

### Fase 1 — Auth y plan de cuentas (2–3 días)
- Registro/login/verificación de email.
- CRUD del plan de cuentas con jerarquía y tipos. Importar plan de cuentas base (seeder) opcional al registrarse.
- Tests: scoping por usuario, unicidad de códigos.

### Fase 2 — Módulo operativo (3–4 días)
- CRUD de tipos de operación y variables.
- Formulario dinámico de ejecución con validación por tipo.
- Evento `OperationExecuted` y log de eventos con filtros y estado.
- Tests: validación dinámica, emisión de evento, payload persistido.

### Fase 3 — Mapeo contable y asientos (4–5 días)
- Editor de mapeo (Debe/Haber, expresiones, cuenta fija o por variable) con previsualización.
- `MappingResolver`, `JournalEntryBuilder`, listener queued, numeración correlativa.
- Procesamiento de eventos `unmapped` al crear mapeo; versionado de mapeos; reversión.
- Tests: balance, idempotencia, expresiones, errores visibles en log.

### Fase 4 — Libros (2–3 días)
- Libro diario con filtros por fecha y buscador.
- Libro mayor por cuenta con saldo acumulado.
- Paginación y rendimiento (índices, consultas agregadas).

### Fase 5 — Estados financieros y exportación (3 días)
- Balance general y P&L con agrupación jerárquica.
- Exportación PDF y XLSX/CSV de libros y estados.
- Tests de consistencia: `Activo = Pasivo + Patrimonio + Resultado`.

### Fase 6 — Producción (2 días)
- `docker-compose.prod.yml`, Dockerfile multi-stage, TLS, script de deploy, backups.
- Hardening: `APP_DEBUG=false`, rate limits, revisión de policies.

### Fase 7 — Pulido y extras (continuo)
- Asientos manuales (sin operación de origen).
- Ejercicios contables y cierre de período (bloqueo de fechas cerradas).
- Multi-empresa por usuario, multimoneda, auditoría (`spatie/laravel-activitylog`).
- Dashboard con KPIs (caja, resultado del mes).

Estimación total MVP (Fases 0–6): **~3–4 semanas** para un desarrollador.

---

## 9. Definición de "hecho" del MVP

- [ ] Registro e inicio de sesión con correo/contraseña.
- [ ] Plan de cuentas configurable.
- [ ] Crear operaciones con variables tipadas y ejecutarlas mediante formulario.
- [ ] Log de eventos con estado por evento.
- [ ] Mapeo contable Debe/Haber con validación de balance.
- [ ] Generación automática de asientos y procesamiento retroactivo al crear el mapeo.
- [ ] Libro diario y mayor con filtros por fecha y buscador.
- [ ] Balance general y P&L visibles y descargables.
- [ ] Despliegue con `docker compose up` en un VPS.

---

## 10. Decisiones abiertas (a confirmar antes de la Fase 1)

1. **Livewire vs Filament** para los CRUDs administrativos.
2. **Moneda única** por usuario en el MVP (recomendado) o multimoneda desde el inicio.
3. **Fecha contable del evento**: siempre la fecha de ejecución, o permitir fecha manual (recomendado permitir, con validación de período abierto).
4. **Impuestos (IVA)**: ¿se resuelven solo con expresiones en el mapeo o se necesita un módulo de impuestos?
5. **Plan de cuentas base** precargado según país (Argentina, España, México…) o vacío.
6. **Modificación de eventos**: prohibir edición y usar anulación + nueva ejecución (recomendado) vs edición con regeneración.
