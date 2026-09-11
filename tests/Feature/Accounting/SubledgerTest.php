<?php

use App\Models\User;
use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Livewire\ChartOfAccounts;
use App\Modules\Accounting\Livewire\GeneralLedger;
use App\Modules\Accounting\Livewire\SubledgerBook;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\JournalEntryBuilder;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Accounting\Services\ReportService;
use Livewire\Livewire;

/**
 * Escenario: CxC (principal) con auxiliares Luis y Andrés; Ventas como
 * contrapartida. Venta a crédito a Luis 100 y a Andrés 40; cobro de Luis 30.
 *
 * @return array{cxc: Account, luis: Account, andres: Account, ventas: Account, caja: Account}
 */
function setupSubledger(User $user): array
{
    $cxc = Account::factory()->for($user)->create(['code' => '1.1.03', 'name' => 'Clientes por cobrar', 'type' => 'asset', 'is_postable' => false]);
    $luis = Account::factory()->for($user)->create(['code' => '1.1.03.01', 'name' => 'CxC de Luis', 'type' => 'asset', 'parent_id' => $cxc->id, 'is_auxiliary' => true]);
    $andres = Account::factory()->for($user)->create(['code' => '1.1.03.02', 'name' => 'CxC de Andrés', 'type' => 'asset', 'parent_id' => $cxc->id, 'is_auxiliary' => true]);
    $ventas = Account::factory()->for($user)->create(['code' => '4.1', 'name' => 'Ventas', 'type' => 'income', 'pnl_section' => 'operating_income']);
    $caja = Account::factory()->for($user)->create(['code' => '1.1.01', 'name' => 'Caja', 'type' => 'asset']);

    $post = fn (string $date, string $desc, Account $debit, Account $credit, string $amount) => app(JournalEntryBuilder::class)->post(
        userId: $user->id, date: $date, description: $desc,
        lines: [
            ['side' => EntrySide::Debit, 'account_id' => $debit->id, 'amount' => $amount, 'memo' => null],
            ['side' => EntrySide::Credit, 'account_id' => $credit->id, 'amount' => $amount, 'memo' => null],
        ],
    );

    $post('2026-09-01', 'Venta a crédito a Luis', $luis, $ventas, '100.00');
    $post('2026-09-02', 'Venta a crédito a Andrés', $andres, $ventas, '40.00');
    $post('2026-09-05', 'Cobro parcial de Luis', $caja, $luis, '30.00');

    return compact('cxc', 'luis', 'andres', 'ventas', 'caja');
}

test('se puede crear una cuenta auxiliar bajo una principal y hereda tipo y sección', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $cxc = Account::factory()->for($user)->create(['code' => '1.1.03', 'name' => 'Clientes por cobrar', 'type' => 'asset']);

    Livewire::test(ChartOfAccounts::class)
        ->call('create', $cxc->id)
        ->set('code', '1.1.03.01')
        ->set('name', 'CxC de Luis')
        ->set('is_auxiliary', true)
        ->call('save')
        ->assertHasNoErrors();

    $luis = Account::where('code', '1.1.03.01')->first();

    expect($luis->is_auxiliary)->toBeTrue()
        ->and($luis->is_postable)->toBeTrue()
        ->and($luis->type->value)->toBe('asset')
        ->and($luis->parent_id)->toBe($cxc->id)
        ->and($cxc->fresh()->is_postable)->toBeFalse();
});

test('una cuenta auxiliar no puede tener sub-cuentas', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupSubledger($user);

    // Desde el botón Sub-cuenta: bloqueado.
    Livewire::test(ChartOfAccounts::class)
        ->call('create', $s['luis']->id)
        ->assertSet('showForm', false);

    // Eligiendo el auxiliar como padre en el formulario: bloqueado.
    Livewire::test(ChartOfAccounts::class)
        ->call('create')
        ->set('code', '9.9')
        ->set('name', 'Hija inválida')
        ->set('parent_id', (string) $s['luis']->id)
        ->call('save')
        ->assertHasErrors(['parent_id']);
});

test('los auxiliares consolidan en la principal y no aparecen en el balance', function () {
    $user = User::factory()->create();
    $s = setupSubledger($user);

    $report = app(ReportService::class)->balanceSheet($user->id, '2026-09-30', includeZero: true);
    $assetCodes = collect($report['sections']['asset']['rows'])->pluck('account.code');

    // CxC muestra el consolidado (100 + 40 − 30 = 110); los auxiliares no listan.
    $cxcRow = collect($report['sections']['asset']['rows'])->first(fn ($r) => $r['account']->code === '1.1.03');

    expect($cxcRow['amount'])->toBe('110.00')
        ->and($assetCodes)->not->toContain('1.1.03.01')
        ->and($assetCodes)->not->toContain('1.1.03.02')
        ->and($report['check']['balanced'])->toBeTrue();
});

test('el mayor de la principal agrega los movimientos de sus auxiliares', function () {
    $user = User::factory()->create();
    $s = setupSubledger($user);
    $this->actingAs($user);

    $result = app(LedgerService::class)->movements($s['cxc']);

    expect($result['movements'])->toHaveCount(3)
        ->and($result['closing'])->toBe('110.00');

    // El select del Mayor incluye la principal (no imputable pero con auxiliares)
    // y excluye a los auxiliares.
    Livewire::test(GeneralLedger::class)
        ->assertSee('1.1.03')
        ->assertSee('Clientes por cobrar')
        ->assertDontSee('CxC de Luis');
});

test('los libros auxiliares muestran resumen por auxiliar y detalle con saldo acumulado', function () {
    $user = User::factory()->create();
    $s = setupSubledger($user);
    $this->actingAs($user);

    // Resumen: saldos por auxiliar + total de la principal.
    Livewire::test(SubledgerBook::class)
        ->set('principalId', (string) $s['cxc']->id)
        ->assertSee('CxC de Luis')
        ->assertSee('70,00')   // Luis: 100 − 30
        ->assertSee('CxC de Andrés')
        ->assertSee('40,00')
        ->assertSee('110,00'); // total principal

    // Detalle de Luis con saldo acumulado.
    Livewire::test(SubledgerBook::class)
        ->set('principalId', (string) $s['cxc']->id)
        ->set('auxiliaryId', (string) $s['luis']->id)
        ->assertSee('Venta a crédito a Luis')
        ->assertSee('Cobro parcial de Luis')
        ->assertDontSee('Venta a crédito a Andrés')
        ->assertSee('70,00');
});

test('los libros auxiliares no exponen cuentas de otros usuarios', function () {
    $other = User::factory()->create();
    $s = setupSubledger($other);

    $this->actingAs(User::factory()->create());

    Livewire::test(SubledgerBook::class)
        ->assertDontSee('Clientes por cobrar')
        ->set('principalId', (string) $s['cxc']->id)
        ->assertDontSee('CxC de Luis');
});
