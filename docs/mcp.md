# Servidor MCP

La app expone un servidor [MCP](https://modelcontextprotocol.io) para gestionar la contabilidad de **una cuenta** desde Claude Code o Claude Desktop: consultas, reportes y registro de asientos y operaciones.

- Endpoint: `https://contable.upsofte.com/mcp` (transporte HTTP «streamable»).
- Autenticación: token personal (Sanctum) en el header `Authorization: Bearer <token>`. Cada token pertenece a un usuario y solo ve sus datos.
- Código: `app/Mcp/Servers/ContabilidadServer.php` (instrucciones y lista de tools) y `app/Mcp/Tools/*` (una clase por tool, sobre los servicios de Accounting/Operations). Ruta en `routes/ai.php`.

## Conectar

1. En la app: **Perfil → Acceso MCP (Claude) → Generar token**. El token se muestra una sola vez junto al comando listo para copiar.
2. Claude Code:

   ```bash
   claude mcp add --transport http contable https://contable.upsofte.com/mcp --header "Authorization: Bearer <token>"
   ```

3. Claude Desktop (`claude_desktop_config.json`), vía el puente `mcp-remote`:

   ```json
   {
     "mcpServers": {
       "contable": {
         "command": "npx",
         "args": ["-y", "mcp-remote", "https://contable.upsofte.com/mcp", "--header", "Authorization: Bearer <token>"]
       }
     }
   }
   ```

Para cortar el acceso, revocá el token en el Perfil. Las cuentas demo no pueden generar tokens.

## Tools

| Tool | Qué hace |
|---|---|
| `resumen_cuenta` | Activo total, control del balance, resultado del mes, eventos sin contabilizar, período cerrado |
| `plan_de_cuentas` | Cuentas con tipo, padre, imputable, auxiliar, activa (filtros: buscar, solo_imputables) |
| `saldos_de_cuentas` | Saldos a una fecha agrupados por tipo, con control deudor = acreedor |
| `libro_mayor` | Movimientos de una cuenta con saldo acumulado |
| `libro_auxiliar` | Saldos por auxiliar de una principal y detalle de un auxiliar |
| `listar_asientos` / `ver_asiento` | Libro diario paginado con filtros / un asiento con sus líneas |
| `balance_general`, `estado_de_resultados`, `flujo_de_caja` | Reportes (fechas e «incluir_cero») |
| `tipos_de_operacion` / `log_de_eventos` | Tipos con sus variables y si tienen mapeo / ejecuciones con estado y asiento |
| `registrar_asiento` | Asiento manual (líneas debe/haber, cuentas por código) |
| `ejecutar_operacion` | Ejecuta un tipo de operación; el mapeo genera el asiento |
| `revertir_asiento` | Contra-asiento; si viene de una operación, la anula |
| `crear_cuenta` | Alta en el plan de cuentas (incluye auxiliares) |

Los errores de negocio (asiento desbalanceado, período cerrado, cuenta no imputable, etc.) vuelven como error de la tool con el mensaje en español; no se registra nada.

## Probar en local

```bash
php artisan db:seed --class=DemoSeeder   # usuario demo@contable.test con datos
php artisan mcp:inspector /mcp           # inspector oficial (pide el header Authorization)
```
