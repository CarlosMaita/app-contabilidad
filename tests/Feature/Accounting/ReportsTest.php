<?php

use App\Models\User;
use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Livewire\Reports;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\JournalEntryBuilder;
use App\Modules\Accounting\Services\ReportService;
use Livewire\Livewire;

/**
 * Plan jerárquico mínimo con movimientos:
 * - 10/01: aporte de capital 1000 (Caja / Capital)
 * - 01/02: venta 500 (Caja / Ventas)
 * - 15/02: sueldos 200 (Sueldos / Caja)
 * - 01/03: venta 999 (Caja / Ventas)
 *
 * @return array<string, Account>
 */
function setupReports(User $user): array
{
    $mk = fn (string $code, string $name, string $type, ?Account $parent = null, bool $postable = true): Account => Account::factory()->for($user)->create([
        'code' => $code, 'name' => $name, 'type' => $type,
        'parent_id' => $parent?->id, 'is_postable' => $postable,
    ]);

    $activo = $mk('1', 'Activo', 'asset', null, false);
    $caja = $mk('1.1', 'Caja', 'asset', $activo);
    $pasivo = $mk('2', 'Pasivo', 'liability', null, false);
    $mk('2.1', 'Préstamos', 'liability', $pasivo);
    $patrimonio = $mk('3', 'Patrimonio', 'equity', null, false);
    $capital = $mk('3.1', 'Capital', 'equity', $patrimonio);
    $ingresos = $mk('4', 'Ingresos', 'income', null, false);
    $ventas = $mk('4.1', 'Ventas', 'income', $ingresos);
    $gastos = $mk('5', 'Gastos', 'expense', null, false);
    $sueldos = $mk('5.1', 'Sueldos', 'expense', $gastos);

    $post = fn (string $date, string $desc, Account $debit, Account $credit, string $amount) => app(JournalEntryBuilder::class)->post(
        userId: $user->id, date: $date, description: $desc,
        lines: [
            ['side' => EntrySide::Debit, 'account_id' => $debit->id, 'amount' => $amount, 'memo' => null],
            ['side' => EntrySide::Credit, 'account_id' => $credit->id, 'amount' => $amount, 'memo' => null],
        ],
    );

    $post('2026-01-10', 'Aporte de capital', $caja, $capital, '1000.00');
    $post('2026-02-01', 'Venta de febrero', $caja, $ventas, '500.00');
    $post('2026-02-15', 'Sueldos de febrero', $sueldos, $caja, '200.00');
    $post('2026-03-01', 'Venta de marzo', $caja, $ventas, '999.00');

    return compact('activo', 'caja', 'ventas', 'sueldos');
}

test('el balance general cuadra: Activo = Pasivo + Patrimonio + Resultado', function () {
    $user = User::factory()->create();
    setupReports($user);

    $report = app(ReportService::class)->balanceSheet($user->id, '2026-02-28');

    expect($report['sections']['asset']['total'])->toBe('1300.00')
        ->and($report['sections']['liability']['total'])->toBe('0.00')
        ->and($report['sections']['equity']['total'])->toBe('1000.00')
        ->and($report['result'])->toBe('300.00')
        ->and($report['check']['liabilities_equity'])->toBe('1300.00')
        ->and($report['check']['balanced'])->toBeTrue();
});

test('el balance agrupa jerárquicamente: el padre acumula a sus hijas', function () {
    $user = User::factory()->create();
    setupReports($user);

    $report = app(ReportService::class)->balanceSheet($user->id, '2026-02-28');
    $rows = $report['sections']['asset']['rows'];

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['account']->code)->toBe('1')
        ->and($rows[0]['level'])->toBe(0)
        ->and($rows[0]['amount'])->toBe('1300.00')
        ->and($rows[1]['account']->code)->toBe('1.1')
        ->and($rows[1]['level'])->toBe(1)
        ->and($rows[1]['amount'])->toBe('1300.00');

    // Pasivo sin movimientos: no aparece ninguna fila.
    expect($report['sections']['liability']['rows'])->toBeEmpty();
});

test('el balance a una fecha excluye movimientos posteriores', function () {
    $user = User::factory()->create();
    setupReports($user);

    $febrero = app(ReportService::class)->balanceSheet($user->id, '2026-02-28');
    $marzo = app(ReportService::class)->balanceSheet($user->id, '2026-03-31');

    expect($febrero['sections']['asset']['total'])->toBe('1300.00')
        ->and($marzo['sections']['asset']['total'])->toBe('2299.00')
        ->and($marzo['check']['balanced'])->toBeTrue();
});

test('el estado de resultados calcula ingresos, gastos y resultado neto por rango', function () {
    $user = User::factory()->create();
    setupReports($user);

    $pnl = app(ReportService::class)->profitAndLoss($user->id, '2026-02-01', '2026-02-28');

    expect($pnl['income']['total'])->toBe('500.00')
        ->and($pnl['expense']['total'])->toBe('200.00')
        ->and($pnl['result'])->toBe('300.00');

    $anual = app(ReportService::class)->profitAndLoss($user->id, null, '2026-12-31');
    expect($anual['income']['total'])->toBe('1499.00')
        ->and($anual['result'])->toBe('1299.00');
});

test('los reportes no incluyen datos de otros usuarios', function () {
    $other = User::factory()->create();
    setupReports($other);

    $user = User::factory()->create();
    $report = app(ReportService::class)->balanceSheet($user->id, '2026-12-31');

    expect($report['sections']['asset']['total'])->toBe('0.00')
        ->and($report['check']['balanced'])->toBeTrue();
});

test('la página de reportes muestra el balance y el P&L', function () {
    $user = User::factory()->create();
    setupReports($user);
    $this->actingAs($user);

    Livewire::test(Reports::class)
        ->set('asOf', '2026-02-28')
        ->assertSee('Balance general')
        ->assertSee('1.300,00')
        ->assertSee('Resultado del período')
        ->call('setTab', 'pnl')
        ->set('from', '2026-02-01')
        ->set('to', '2026-02-28')
        ->assertSee('Resultado neto del período')
        ->assertSee('300,00');
});

test('las exportaciones descargan XLSX y PDF con el formato correcto', function () {
    $user = User::factory()->create();
    $s = setupReports($user);
    $this->actingAs($user);

    $this->get('/exports/balance?format=xlsx&as_of=2026-02-28')
        ->assertOk()
        ->assertDownload('balance-general-2026-02-28.xlsx');

    $this->get('/exports/balance?format=pdf&as_of=2026-02-28')
        ->assertOk()
        ->assertDownload('balance-general-2026-02-28.pdf');

    $this->get('/exports/pnl?format=xlsx&from=2026-02-01&to=2026-02-28')
        ->assertOk()
        ->assertDownload('estado-de-resultados-2026-02-28.xlsx');

    $this->get('/exports/journal?format=xlsx')
        ->assertOk()
        ->assertDownload('libro-diario.xlsx');

    $this->get('/exports/journal?format=pdf')
        ->assertOk()
        ->assertDownload('libro-diario.pdf');

    $this->get("/exports/ledger?format=xlsx&account_id={$s['caja']->id}")
        ->assertOk()
        ->assertDownload('libro-mayor-1.1.xlsx');
});

test('las exportaciones requieren autenticación y no exponen cuentas ajenas', function () {
    $this->get('/exports/balance?format=pdf&as_of=2026-01-01')->assertRedirect('/login');

    $other = User::factory()->create();
    $s = setupReports($other);

    $this->actingAs(User::factory()->create())
        ->get("/exports/ledger?format=xlsx&account_id={$s['caja']->id}")
        ->assertNotFound();
});
