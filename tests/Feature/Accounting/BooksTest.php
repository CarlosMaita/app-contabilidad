<?php

use App\Models\User;
use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Livewire\GeneralLedger;
use App\Modules\Accounting\Livewire\JournalBook;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalEntryBuilder;
use App\Modules\Accounting\Services\LedgerService;
use Livewire\Livewire;

/**
 * @return array{caja: Account, ventas: Account}
 */
function setupBooks(User $user): array
{
    $caja = Account::factory()->for($user)->create(['code' => '1.1', 'name' => 'Caja', 'type' => 'asset']);
    $ventas = Account::factory()->for($user)->create(['code' => '4.1', 'name' => 'Ventas', 'type' => 'income']);

    return ['caja' => $caja, 'ventas' => $ventas];
}

function postSale(User $user, Account $caja, Account $ventas, string $date, string $amount, string $description): JournalEntry
{
    return app(JournalEntryBuilder::class)->post(
        userId: $user->id,
        date: $date,
        description: $description,
        lines: [
            ['side' => EntrySide::Debit, 'account_id' => $caja->id, 'amount' => $amount, 'memo' => null],
            ['side' => EntrySide::Credit, 'account_id' => $ventas->id, 'amount' => $amount, 'memo' => null],
        ],
    );
}

test('el libro diario requiere autenticación y se renderiza', function () {
    $this->get('/journal')->assertRedirect('/login');

    $this->actingAs(User::factory()->create())
        ->get('/journal')
        ->assertOk()
        ->assertSeeLivewire(JournalBook::class);
});

test('el diario lista asientos y filtra por rango de fechas', function () {
    $user = User::factory()->create();
    $s = setupBooks($user);
    postSale($user, $s['caja'], $s['ventas'], '2026-09-01', '100.00', 'Venta de septiembre');
    postSale($user, $s['caja'], $s['ventas'], '2026-08-15', '50.00', 'Venta de agosto');

    $this->actingAs($user);

    Livewire::test(JournalBook::class)
        ->assertSee('Venta de septiembre')
        ->assertSee('Venta de agosto')
        ->set('from', '2026-09-01')
        ->assertSee('Venta de septiembre')
        ->assertDontSee('Venta de agosto');
});

test('el buscador del diario encuentra por descripción, número y cuenta', function () {
    $user = User::factory()->create();
    $s = setupBooks($user);
    postSale($user, $s['caja'], $s['ventas'], '2026-09-01', '100.00', 'Cobro factura 0099');
    postSale($user, $s['caja'], $s['ventas'], '2026-09-02', '75.00', 'Otra cosa');

    $this->actingAs($user);

    // Por descripción
    Livewire::test(JournalBook::class)
        ->set('search', 'factura')
        ->assertSee('Cobro factura 0099')
        ->assertDontSee('Otra cosa');

    // Por número exacto
    Livewire::test(JournalBook::class)
        ->set('search', '2')
        ->assertSee('Otra cosa')
        ->assertDontSee('Cobro factura 0099');

    // Por nombre de cuenta: ambos usan Caja
    Livewire::test(JournalBook::class)
        ->set('search', 'Caja')
        ->assertSee('Cobro factura 0099')
        ->assertSee('Otra cosa');
});

test('el diario no muestra asientos de otros usuarios', function () {
    $other = User::factory()->create();
    $s = setupBooks($other);
    postSale($other, $s['caja'], $s['ventas'], '2026-09-01', '100.00', 'Asiento ajeno');

    $this->actingAs(User::factory()->create());

    Livewire::test(JournalBook::class)->assertDontSee('Asiento ajeno');
});

test('el mayor calcula saldo inicial, acumulado y final para cuenta deudora', function () {
    $user = User::factory()->create();
    $s = setupBooks($user);
    postSale($user, $s['caja'], $s['ventas'], '2026-08-10', '200.00', 'Venta previa');
    postSale($user, $s['caja'], $s['ventas'], '2026-09-01', '100.00', 'Venta 1');
    postSale($user, $s['caja'], $s['ventas'], '2026-09-05', '30.00', 'Venta 2');

    $this->actingAs($user);

    $result = app(LedgerService::class)->movements($s['caja'], '2026-09-01', '2026-09-30');

    expect($result['opening'])->toBe('200.00')
        ->and($result['movements'])->toHaveCount(2)
        ->and($result['movements'][0]->balance)->toBe('300.00')
        ->and($result['movements'][1]->balance)->toBe('330.00')
        ->and($result['closing'])->toBe('330.00');
});

test('el mayor respeta la naturaleza acreedora de ingresos y pasivos', function () {
    $user = User::factory()->create();
    $s = setupBooks($user);
    postSale($user, $s['caja'], $s['ventas'], '2026-09-01', '100.00', 'Venta');

    $this->actingAs($user);

    $result = app(LedgerService::class)->movements($s['ventas']);

    // Ventas se acredita: su saldo acreedor es positivo.
    expect($result['closing'])->toBe('100.00');
});

test('el componente del mayor muestra los movimientos de la cuenta elegida', function () {
    $user = User::factory()->create();
    $s = setupBooks($user);
    postSale($user, $s['caja'], $s['ventas'], '2026-09-01', '100.00', 'Venta con saldo');

    $this->actingAs($user);

    Livewire::test(GeneralLedger::class)
        ->assertSee('Elegí una cuenta')
        ->set('accountId', (string) $s['caja']->id)
        ->assertSee('Venta con saldo')
        ->assertSee('Saldo final');
});

test('el mayor no expone cuentas de otros usuarios', function () {
    $other = User::factory()->create();
    $s = setupBooks($other);
    postSale($other, $s['caja'], $s['ventas'], '2026-09-01', '100.00', 'Movimiento ajeno');

    $this->actingAs(User::factory()->create());

    Livewire::test(GeneralLedger::class)
        ->set('accountId', (string) $s['caja']->id)
        ->assertSee('Elegí una cuenta')
        ->assertDontSee('Movimiento ajeno');
});
