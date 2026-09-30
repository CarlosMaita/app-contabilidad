<?php

use App\Models\User;
use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Livewire\Reports;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\DefaultChartOfAccounts;
use App\Modules\Accounting\Services\JournalEntryBuilder;
use App\Modules\Accounting\Services\ReportService;
use Livewire\Livewire;

/**
 * Plan base + movimientos:
 * - 20/08: aporte inicial en caja 1.000 (saldo de apertura para septiembre)
 * - Septiembre:
 *   aporte de capital al banco 50.000        → financiamiento +50.000
 *   venta a crédito 30.000                     → resultado +30.000, clientes −30.000
 *   cobro en caja 10.000                       → clientes +10.000
 *   compra de vehículo con banco 20.000        → inversión −20.000
 *   depreciación del vehículo 1.000            → resultado −1.000, ajuste +1.000
 *   préstamo recibido en banco 5.000           → financiamiento +5.000
 *   sueldos pagados en caja 3.000              → resultado −3.000
 */
function setupCashFlow(User $user): array
{
    DefaultChartOfAccounts::seedFor($user);

    $acc = fn (string $code): Account => Account::withoutGlobalScopes()
        ->where('user_id', $user->id)->where('code', $code)->firstOrFail();

    $post = fn (string $date, string $debit, string $credit, string $amount) => app(JournalEntryBuilder::class)->post(
        userId: $user->id, date: $date, description: "Mov {$debit}/{$credit}",
        lines: [
            ['side' => EntrySide::Debit, 'account_id' => $acc($debit)->id, 'amount' => $amount, 'memo' => null],
            ['side' => EntrySide::Credit, 'account_id' => $acc($credit)->id, 'amount' => $amount, 'memo' => null],
        ],
    );

    $post('2026-08-20', '1.1.01', '3.1', '1000.00');
    $post('2026-09-01', '1.1.02', '3.1', '50000.00');
    $post('2026-09-02', '1.1.03', '4.1.01', '30000.00');
    $post('2026-09-05', '1.1.01', '1.1.03', '10000.00');
    $post('2026-09-10', '1.2.01.01', '1.1.02', '20000.00');
    $post('2026-09-30', '6.2.01', '1.2.02.01', '1000.00');
    $post('2026-09-15', '1.1.02', '2.1.03', '5000.00');
    $post('2026-09-25', '6.1.02', '1.1.01', '3000.00');

    return ['acc' => $acc];
}

function rowAmount(array $section, string $code): ?string
{
    foreach ($section['rows'] as $row) {
        if ($row['account']->code === $code) {
            return $row['amount'];
        }
    }

    return null;
}

test('el flujo de efectivo discrimina actividades y concilia con caja y bancos', function () {
    $user = User::factory()->create();
    setupCashFlow($user);

    $cf = app(ReportService::class)->cashFlow($user->id, '2026-09-01', '2026-09-30');

    expect($cf['net_income'])->toBe('26000.00')                                 // 30.000 − 1.000 − 3.000
        ->and(rowAmount($cf['sections']['non_cash'], '1.2.02.01'))->toBe('1000.00')
        ->and(rowAmount($cf['sections']['operating'], '1.1.03'))->toBe('-20000.00')
        ->and($cf['totals']['operating'])->toBe('7000.00')                     // 26.000 + 1.000 − 20.000
        ->and(rowAmount($cf['sections']['investing'], '1.2.01.01'))->toBe('-20000.00')
        ->and($cf['totals']['investing'])->toBe('-20000.00')
        ->and(rowAmount($cf['sections']['financing'], '3.1'))->toBe('50000.00')  // el aporte de agosto no entra
        ->and(rowAmount($cf['sections']['financing'], '2.1.03'))->toBe('5000.00')
        ->and($cf['totals']['financing'])->toBe('55000.00')
        ->and($cf['net_change'])->toBe('42000.00')
        ->and($cf['cash_opening'])->toBe('1000.00')
        ->and($cf['cash_closing'])->toBe('43000.00')
        ->and($cf['check']['balanced'])->toBeTrue();

    // Las cuentas de efectivo no son una línea: son lo que se explica.
    foreach ($cf['sections'] as $section) {
        expect(collect($section['rows'])->pluck('account.code'))
            ->not->toContain('1.1.01')
            ->not->toContain('1.1.02');
    }
});

test('sin fecha desde el flujo arranca en cero y sigue conciliando', function () {
    $user = User::factory()->create();
    setupCashFlow($user);

    $cf = app(ReportService::class)->cashFlow($user->id, null, '2026-09-30');

    expect($cf['cash_opening'])->toBe('0.00')
        ->and($cf['cash_closing'])->toBe('43000.00')
        ->and($cf['totals']['financing'])->toBe('56000.00')
        ->and($cf['check']['balanced'])->toBeTrue();
});

test('el flujo oculta cuentas sin variación salvo que se pidan', function () {
    $user = User::factory()->create();
    setupCashFlow($user);

    $oculto = app(ReportService::class)->cashFlow($user->id, '2026-09-01', '2026-09-30');
    $visible = app(ReportService::class)->cashFlow($user->id, '2026-09-01', '2026-09-30', includeZero: true);

    expect(rowAmount($oculto['sections']['operating'], '2.1.01'))->toBeNull()
        ->and(rowAmount($visible['sections']['operating'], '2.1.01'))->toBe('0.00')
        ->and($visible['net_change'])->toBe($oculto['net_change']);
});

test('las auxiliares se consolidan en su cuenta principal dentro del flujo', function () {
    $user = User::factory()->create();
    ['acc' => $acc] = setupCashFlow($user);

    $clientes = $acc('1.1.03');
    $luis = Account::factory()->for($user)->create([
        'code' => '1.1.03.01', 'name' => 'CxC de Luis', 'type' => 'asset',
        'parent_id' => $clientes->id, 'is_auxiliary' => true,
    ]);

    app(JournalEntryBuilder::class)->post(
        userId: $user->id, date: '2026-09-20', description: 'Venta a Luis',
        lines: [
            ['side' => EntrySide::Debit, 'account_id' => $luis->id, 'amount' => '4000.00', 'memo' => null],
            ['side' => EntrySide::Credit, 'account_id' => $acc('4.1.01')->id, 'amount' => '4000.00', 'memo' => null],
        ],
    );

    $cf = app(ReportService::class)->cashFlow($user->id, '2026-09-01', '2026-09-30');

    expect(rowAmount($cf['sections']['operating'], '1.1.03'))->toBe('-24000.00')
        ->and(rowAmount($cf['sections']['operating'], '1.1.03.01'))->toBeNull()
        ->and($cf['check']['balanced'])->toBeTrue();
});

test('la actividad se hereda del padre y sync clasifica cuentas existentes', function () {
    $user = User::factory()->create();

    // Plan viejo sin clasificar: Caja coincide con el plan base.
    $activo = Account::factory()->for($user)->create(['code' => '1.1', 'name' => 'Activo corriente', 'type' => 'asset', 'is_postable' => false]);
    $caja = Account::factory()->for($user)->create(['code' => '1.1.01', 'name' => 'Caja', 'type' => 'asset', 'parent_id' => $activo->id]);
    $cajaChica = Account::factory()->for($user)->create(['code' => '1.1.01.01', 'name' => 'Caja chica', 'type' => 'asset', 'parent_id' => $caja->id]);

    expect($caja->effectiveCashFlowSection()->value)->toBe('operating');

    DefaultChartOfAccounts::classifyFor($user);

    expect($caja->fresh()->cash_flow_section->value)->toBe('cash')
        // Sin clasificación propia, la sub-cuenta hereda del padre.
        ->and($cajaChica->fresh()->effectiveCashFlowSection()->value)->toBe('cash')
        // Idempotente.
        ->and(DefaultChartOfAccounts::classifyFor($user))->toBe(0);
});

test('la pestaña de flujo de efectivo se muestra y exporta', function () {
    $user = User::factory()->create();
    setupCashFlow($user);
    $this->actingAs($user);

    Livewire::test(Reports::class)
        ->call('setTab', 'cashflow')
        ->set('from', '2026-09-01')
        ->set('to', '2026-09-30')
        ->assertSee('Flujo neto de actividades operativas')
        ->assertSee('Flujo neto de actividades de inversión')
        ->assertSee('Flujo neto de actividades de financiamiento')
        ->assertSee('42.000,00')
        ->assertSee('43.000,00');

    $this->get('/exports/cashflow?format=xlsx&from=2026-09-01&to=2026-09-30')
        ->assertOk()
        ->assertDownload('flujo-de-efectivo-2026-09-30.xlsx');

    $this->get('/exports/cashflow?format=pdf&from=2026-09-01&to=2026-09-30')
        ->assertOk()
        ->assertDownload('flujo-de-efectivo-2026-09-30.pdf');
});
