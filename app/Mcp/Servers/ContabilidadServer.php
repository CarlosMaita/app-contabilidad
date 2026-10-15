<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\BalanceGeneral;
use App\Mcp\Tools\CrearCuenta;
use App\Mcp\Tools\EjecutarOperacion;
use App\Mcp\Tools\EstadoDeResultados;
use App\Mcp\Tools\FlujoDeCaja;
use App\Mcp\Tools\LibroAuxiliar;
use App\Mcp\Tools\LibroMayor;
use App\Mcp\Tools\ListarAsientos;
use App\Mcp\Tools\LogDeEventos;
use App\Mcp\Tools\PlanDeCuentas;
use App\Mcp\Tools\RegistrarAsiento;
use App\Mcp\Tools\ResumenCuenta;
use App\Mcp\Tools\RevertirAsiento;
use App\Mcp\Tools\SaldosDeCuentas;
use App\Mcp\Tools\TiposDeOperacion;
use App\Mcp\Tools\VerAsiento;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Contabilidad por eventos')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    Contabilidad de partida doble de UNA cuenta de usuario (la dueña del token). Moneda única.
    - Montos: decimales con punto («1500.50»); fechas AAAA-MM-DD. Los saldos vienen según la naturaleza de la cuenta.
    - Cuentas: se identifican por código jerárquico («1.1.02»). Solo las imputables (hojas activas) reciben asientos; las principales con auxiliares se imputan a través de sus auxiliares y consolidan sus saldos.
    - Para registrar hechos con un tipo de operación (ventas, cobros, pagos) usá «ejecutar_operacion»: genera el asiento con el mapeo contable. «registrar_asiento» es para ajustes y asientos manuales.
    - Los asientos son inmutables: para corregir uno se usa «revertir_asiento» (contra-asiento) y luego se registra el correcto.
    - No se puede registrar con fecha dentro de un período cerrado (ver «resumen_cuenta»).
    - Antes de escribir, confirmá con el usuario cuentas, montos y fecha.
    TXT)]
class ContabilidadServer extends Server
{
    /** Todas las tools en una sola página de tools/list. */
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        ResumenCuenta::class,
        PlanDeCuentas::class,
        SaldosDeCuentas::class,
        LibroMayor::class,
        LibroAuxiliar::class,
        ListarAsientos::class,
        VerAsiento::class,
        BalanceGeneral::class,
        EstadoDeResultados::class,
        FlujoDeCaja::class,
        TiposDeOperacion::class,
        LogDeEventos::class,
        RegistrarAsiento::class,
        EjecutarOperacion::class,
        RevertirAsiento::class,
        CrearCuenta::class,
    ];
}
