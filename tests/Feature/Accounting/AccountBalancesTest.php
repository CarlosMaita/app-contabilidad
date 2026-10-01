<?php

use App\Models\User;
use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Livewire\AccountBalances;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\DefaultChartOfAccounts;
use App\Modules\Accounting\Services\JournalEntryBuilder;
use App\Modules\Accounting\Services\ReportService;
use Livewire\Livewire;

/**
 * Plan base + movimientos:
 * - 01/09 aporte de capital al banco 10.000
 * - 02/09 venta a crédito a Luis (auxiliar de Clientes) 3.000
 * - 10/09 depreciación de vehículos 500
 * - 20/10 sueldos pagados por banco 1.000 (posterior al corte de septiembre)
 */
function setupBalances(User $user): array
{
    DefaultChartOfAccounts::seedFor($user);

    $acc = fn (string $code): Account => Account::withoutGlobalScopes()
        ->where('user_id', $user->id)->where('code', $code)->firstOrFail();

    $luis = Account::factory()->for($user)->create([
        'code' => '1.1.03.01', 'name' => 'CxC de Luis', 'type' => 'asset',
        'parent_id' => $acc('1.1.03')->id, 'is_auxiliary' => true,
    ]);

    $post = fn (string $date, Account $debit, Account $credit, string $amount) => app(JournalEntryBuilder::class)->post(
        userId: $user->id, date: $date, description: 'Mov',
        lines: [
            ['side' => EntrySide::Debit, 'account_id' => $debit->id, 'amount' => $amount, 'memo' => null],
            ['side' => EntrySide::Credit, 'account_id' => $credit->id, 'amount' => $amount, 'memo' => null],
        ],
    );

    $post('2026-09-01', $acc('1.1.02'), $acc('3.1'), '10000.00');
    $post('2026-09-02', $luis, $acc('4.1.01'), '3000.00');
    $post('2026-09-10', $acc('6.2.01'), $acc('1.2.02.01'), '500.00');
    $post('2026-10-20', $acc('6.1.02'), $acc('1.1.02'), '1000.00');

    return ['acc' => $acc, 'luis' => $luis];
}

function balanceOf(array $report, string $code): ?string
{
    foreach ($report['groups'] as $group) {
        foreach ($group['rows'] as $row) {
            if ($row['account']->code === $code) {
                return $row['balance'];
            }
        }
    }

    return null;
}

test('muestra solo las cuentas con saldo, según su naturaleza y agrupadas por tipo', function () {
    $user = User::factory()->create();
    setupBalances($user);

    $r = app(ReportService::class)->accountBalances($user->id, '2026-09-30');

    expect(balanceOf($r, '1.1.02'))->toBe('10000.00')        // banco, deudor
        ->and(balanceOf($r, '1.1.03.01'))->toBe('3000.00')   // auxiliar con saldo propio
        ->and(balanceOf($r, '1.2.02.01'))->toBe('-500.00')   // contra-activo: saldo contrario a su naturaleza
        ->and(balanceOf($r, '3.1'))->toBe('10000.00')        // patrimonio, acreedor positivo
        ->and(balanceOf($r, '4.1.01'))->toBe('3000.00')
        ->and(balanceOf($r, '6.2.01'))->toBe('500.00')
        ->and(balanceOf($r, '1.1.01'))->toBeNull()           // caja sin movimientos: se omite
        ->and(balanceOf($r, '6.1.02'))->toBeNull()           // sueldos es de octubre: fuera del corte
        ->and($r['groups']['asset']['total'])->toBe('12500.00')
        ->and($r['count'])->toBe(6)
        ->and($r['debit_total'])->toBe('13500.00')
        ->and($r['credit_total'])->toBe('13500.00')
        ->and($r['balanced'])->toBeTrue();
});

test('el corte por fecha incluye los movimientos hasta ese día', function () {
    $user = User::factory()->create();
    setupBalances($user);

    $r = app(ReportService::class)->accountBalances($user->id, '2026-10-31');

    expect(balanceOf($r, '1.1.02'))->toBe('9000.00')
        ->and(balanceOf($r, '6.1.02'))->toBe('1000.00')
        ->and($r['balanced'])->toBeTrue();
});

test('la búsqueda filtra filas pero el control usa todas las cuentas', function () {
    $user = User::factory()->create();
    setupBalances($user);

    $r = app(ReportService::class)->accountBalances($user->id, '2026-09-30', 'luis');

    expect($r['count'])->toBe(1)
        ->and(balanceOf($r, '1.1.03.01'))->toBe('3000.00')
        ->and($r['balanced'])->toBeTrue();
});

test('la página enlaza al mayor o al libro auxiliar y no muestra datos ajenos', function () {
    $user = User::factory()->create();
    ['acc' => $acc, 'luis' => $luis] = setupBalances($user);

    $other = User::factory()->create();
    DefaultChartOfAccounts::seedFor($other);
    $otherBank = Account::withoutGlobalScopes()->where('user_id', $other->id)->where('code', '1.1.02')->first();
    $otherCapital = Account::withoutGlobalScopes()->where('user_id', $other->id)->where('code', '3.1')->first();
    app(JournalEntryBuilder::class)->post(
        userId: $other->id, date: '2026-09-01', description: 'Ajeno',
        lines: [
            ['side' => EntrySide::Debit, 'account_id' => $otherBank->id, 'amount' => '777.00', 'memo' => null],
            ['side' => EntrySide::Credit, 'account_id' => $otherCapital->id, 'amount' => '777.00', 'memo' => null],
        ],
    );

    $this->actingAs($user)->get('/balances')->assertOk()->assertSee('Saldos de cuentas');

    Livewire::test(AccountBalances::class)
        ->set('asOf', '2026-09-30')
        ->assertSee('Bancos')
        ->assertSee('10.000,00')
        ->assertSee(route('ledger.index', ['accountId' => $acc('1.1.02')->id]), false)
        ->assertSee(e(route('subledgers.index', ['principalId' => $luis->parent_id, 'auxiliaryId' => $luis->id])), false)
        ->assertDontSee('777,00');
});
