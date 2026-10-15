<?php

use App\Demo\DemoSandbox;
use App\Mcp\Servers\ContabilidadServer;
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
use App\Models\User;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationExecution;
use Illuminate\Testing\TestResponse;

/** Cuenta con datos de ejemplo (plan base, auxiliares, operaciones y asientos). */
function mcpUser(): User
{
    $user = app(DemoSandbox::class)->create();
    $user->forceFill(['name' => 'Cuenta MCP', 'is_demo' => false])->save();

    return $user;
}

function mcpCall(string $token, string $method, array $params = [], int $id = 1): TestResponse
{
    return test()->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json, text/event-stream',
    ])->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
}

function codeId(User $user, string $code): int
{
    return Account::withoutGlobalScopes()->where('user_id', $user->id)->where('code', $code)->value('id');
}

test('el endpoint exige un token válido', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();

    mcpCall('1|token-invalido', 'tools/list')->assertUnauthorized();
});

test('con token lista las tools y responde como el dueño del token', function () {
    $user = mcpUser();
    $token = $user->createToken('test')->plainTextToken;

    mcpCall($token, 'initialize', [
        'protocolVersion' => '2025-06-18',
        'capabilities' => (object) [],
        'clientInfo' => ['name' => 'pest', 'version' => '1'],
    ])->assertOk()->assertJsonPath('result.serverInfo.name', 'Contabilidad por eventos');

    $tools = collect(mcpCall($token, 'tools/list', [], 2)->assertOk()->json('result.tools'))->pluck('name');
    expect($tools)->toHaveCount(16)
        ->toContain('resumen_cuenta', 'registrar_asiento', 'ejecutar_operacion', 'revertir_asiento', 'crear_cuenta');

    mcpCall($token, 'tools/call', ['name' => 'resumen_cuenta', 'arguments' => (object) []], 3)
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.usuario', 'Cuenta MCP')
        ->assertJsonPath('result.structuredContent.balance_cuadra', true);
});

test('un token solo ve los datos de su propia cuenta', function () {
    $owner = mcpUser();
    $other = User::factory()->create();
    $token = $other->createToken('test')->plainTextToken;

    $response = mcpCall($token, 'tools/call', ['name' => 'listar_asientos', 'arguments' => (object) []])->assertOk();

    expect($response->json('result.structuredContent.total'))->toBe(0);

    ContabilidadServer::actingAs($other)->tool(VerAsiento::class, ['numero' => 1])
        ->assertHasErrors(['No se encontró']);
    ContabilidadServer::actingAs($other)->tool(LibroMayor::class, ['cuenta' => (string) codeId($owner, '1.1.02')])
        ->assertHasErrors(['no existe']);
});

test('las tools de lectura devuelven los libros y reportes de la cuenta', function () {
    $user = mcpUser();
    $as = ContabilidadServer::actingAs($user);

    $as->tool(ResumenCuenta::class)->assertOk()->assertSee('"eventos_sin_contabilizar":1');
    $as->tool(PlanDeCuentas::class, ['solo_imputables' => true, 'buscar' => 'luis'])
        ->assertOk()->assertSee('CxC de Luis Pérez')->assertDontSee('Clientes por cobrar');
    $as->tool(SaldosDeCuentas::class)->assertOk()->assertSee('"balanced":true');
    $as->tool(LibroMayor::class, ['cuenta' => '1.1.02'])->assertOk()->assertSee('Aporte de capital inicial');
    $as->tool(LibroAuxiliar::class)->assertOk()->assertSee('1.1.03');
    $as->tool(LibroAuxiliar::class, ['cuenta_principal' => '1.1.03', 'auxiliar' => '1.1.03.01'])
        ->assertOk()->assertSee('CxC de Andrea Gómez')->assertSee('factura A-0004');
    $as->tool(LibroAuxiliar::class, ['cuenta_principal' => '1.1.03', 'auxiliar' => '1.1.02'])
        ->assertHasErrors(['no es auxiliar']);
    $as->tool(ListarAsientos::class, ['buscar' => 'camioneta', 'por_pagina' => 5])->assertOk()->assertSee('Compra de camioneta');
    $as->tool(VerAsiento::class, ['numero' => 1])->assertOk()->assertSee('Aporte de capital inicial');
    $as->tool(BalanceGeneral::class)->assertOk()->assertSee('"balanced":true');
    $as->tool(EstadoDeResultados::class, ['desde' => now()->subMonths(3)->toDateString()])->assertOk()->assertSee('"ebitda"');
    $as->tool(FlujoDeCaja::class, ['desde' => now()->subMonths(3)->toDateString()])->assertOk()->assertSee('"balanced":true');
    $as->tool(TiposDeOperacion::class)->assertOk()->assertSee('venta_credito')->assertSee('"tiene_mapeo":false');
    $as->tool(LogDeEventos::class, ['estado' => 'unmapped'])->assertOk()->assertSee('retiro_socio');
});

test('registrar_asiento crea un asiento balanceado con cuentas por código', function () {
    $user = mcpUser();
    $before = JournalEntry::withoutGlobalScopes()->where('user_id', $user->id)->count();

    ContabilidadServer::actingAs($user)->tool(RegistrarAsiento::class, [
        'fecha' => now()->toDateString(),
        'descripcion' => 'Ajuste por MCP',
        'lineas' => [
            ['lado' => 'debe', 'cuenta' => '6.3.02', 'monto' => 1250.5, 'memo' => 'comisión'],
            ['lado' => 'haber', 'cuenta' => '1.1.02', 'monto' => '1250.50'],
        ],
    ])->assertOk()->assertHasNoErrors()->assertSee('Ajuste por MCP');

    $entry = JournalEntry::withoutGlobalScopes()->where('user_id', $user->id)->where('description', 'Ajuste por MCP')->sole();
    expect(JournalEntry::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe($before + 1)
        ->and((string) $entry->lines()->where('account_id', codeId($user, '6.3.02'))->value('debit'))->toBe('1250.50');
});

test('registrar_asiento rechaza asientos inválidos sin registrar nada', function () {
    $user = mcpUser();
    $user->update(['accounting_closed_until' => now()->subMonth()->endOfMonth()->toDateString()]);
    $count = fn () => JournalEntry::withoutGlobalScopes()->where('user_id', $user->id)->count();
    $before = $count();
    $as = ContabilidadServer::actingAs($user);
    $line = fn (string $side, string $code, string $amount) => ['lado' => $side, 'cuenta' => $code, 'monto' => $amount];

    $as->tool(RegistrarAsiento::class, ['fecha' => now()->toDateString(), 'descripcion' => 'Desbalanceado', 'lineas' => [
        $line('debe', '6.1.01', '100'), $line('haber', '1.1.01', '90'),
    ]])->assertHasErrors();

    $as->tool(RegistrarAsiento::class, ['fecha' => now()->toDateString(), 'descripcion' => 'Cuenta madre', 'lineas' => [
        $line('debe', '1.1.03', '100'), $line('haber', '1.1.01', '100'),
    ]])->assertHasErrors(['no es imputable']);

    $as->tool(RegistrarAsiento::class, ['fecha' => now()->subMonths(2)->toDateString(), 'descripcion' => 'Período cerrado', 'lineas' => [
        $line('debe', '6.1.01', '100'), $line('haber', '1.1.01', '100'),
    ]])->assertHasErrors();

    $as->tool(RegistrarAsiento::class, ['fecha' => now()->toDateString(), 'descripcion' => 'Cuenta inexistente', 'lineas' => [
        $line('debe', '9.9.99', '100'), $line('haber', '1.1.01', '100'),
    ]])->assertHasErrors(['no existe']);

    expect($count())->toBe($before);
});

test('ejecutar_operacion registra el evento y genera el asiento por el mapeo', function () {
    $user = mcpUser();
    $as = ContabilidadServer::actingAs($user);

    $as->tool(EjecutarOperacion::class, [
        'tipo' => 'venta_credito',
        'datos' => ['cliente' => '1.1.03.02', 'neto' => 1000],
        'fecha' => now()->toDateString(),
        'descripcion' => 'Venta por MCP',
    ])->assertOk()->assertHasNoErrors()->assertSee('"estado":"posted"')->assertSee('1210.00');

    $execution = OperationExecution::withoutGlobalScopes()->where('user_id', $user->id)->where('description', 'Venta por MCP')->sole();
    expect($execution->payload['cliente'])->toBe(codeId($user, '1.1.03.02'))
        ->and(JournalEntry::withoutGlobalScopes()->where('operation_execution_id', $execution->id)->exists())->toBeTrue();

    $as->tool(EjecutarOperacion::class, [
        'tipo' => 'retiro_socio', 'datos' => ['monto' => 500], 'fecha' => now()->toDateString(),
    ])->assertOk()->assertSee('"estado":"unmapped"');

    $as->tool(EjecutarOperacion::class, [
        'tipo' => 'no_existe', 'datos' => [], 'fecha' => now()->toDateString(),
    ])->assertHasErrors(['No existe el tipo']);
});

test('revertir_asiento genera contra-asiento y anula la operación de origen', function () {
    $user = mcpUser();
    $as = ContabilidadServer::actingAs($user);
    $entries = JournalEntry::withoutGlobalScopes()->where('user_id', $user->id);

    $manual = (clone $entries)->whereNull('operation_execution_id')->orderBy('number')->first();
    $as->tool(RevertirAsiento::class, ['numero' => $manual->number])->assertOk()->assertSee('contra-asiento');
    expect($manual->fresh()->status)->toBe(JournalEntryStatus::Reversed);

    $fromOperation = (clone $entries)->whereNotNull('operation_execution_id')->where('status', 'posted')->orderBy('number')->first();
    $as->tool(RevertirAsiento::class, ['numero' => $fromOperation->number])->assertOk()->assertSee('operación de origen anulada');
    expect(OperationExecution::withoutGlobalScopes()->find($fromOperation->operation_execution_id)->status)->toBe(ExecutionStatus::Voided);

    $as->tool(RevertirAsiento::class, ['numero' => $manual->number])->assertHasErrors(['ya está revertido']);
});

test('crear_cuenta respeta las reglas del plan de cuentas', function () {
    $user = mcpUser();
    $as = ContabilidadServer::actingAs($user);

    $as->tool(CrearCuenta::class, [
        'codigo' => '1.1.03.04', 'nombre' => 'CxC de Marta', 'cuenta_padre' => '1.1.03', 'auxiliar' => true,
    ])->assertOk()->assertHasNoErrors();

    $marta = Account::withoutGlobalScopes()->where('user_id', $user->id)->where('code', '1.1.03.04')->sole();
    expect($marta->is_auxiliary)->toBeTrue()
        ->and($marta->type->value)->toBe('asset')
        ->and($marta->is_postable)->toBeTrue();

    $as->tool(CrearCuenta::class, ['codigo' => '1.1.03.04.01', 'nombre' => 'Sub', 'cuenta_padre' => '1.1.03.04'])
        ->assertHasErrors(['auxiliar no puede tener sub-cuentas']);
    $as->tool(CrearCuenta::class, ['codigo' => '1.1.03.04', 'nombre' => 'Repetida', 'tipo' => 'asset'])
        ->assertHasErrors();
    $as->tool(CrearCuenta::class, ['codigo' => '7', 'nombre' => 'Sin tipo'])
        ->assertHasErrors(['Indicá el tipo']);
});
