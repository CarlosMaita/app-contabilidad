<?php

use App\Books\BookService;
use App\Books\BookTransferService;
use App\Livewire\Books\TransferBetweenBooks;
use App\Models\User;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Livewire\JournalBook;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BookTransfer;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\DefaultChartOfAccounts;
use App\Modules\Accounting\Services\ReportService;
use App\Modules\Shared\Books\Book;
use App\Modules\Shared\Books\CurrentBook;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @return array{user: User, personal: Book, family: Book} */
function transferSetup(): array
{
    $user = User::factory()->create(['name' => 'Carlos']);
    DefaultChartOfAccounts::seedFor(bookOf($user));
    $family = app(BookService::class)->create($user, 'Familia Maita', 'household');

    return ['user' => $user, 'personal' => bookOf($user), 'family' => $family];
}

function acc(Book $book, string $codeOrName): Account
{
    return Account::withoutGlobalScopes()->where('book_id', $book->id)
        ->where(fn ($q) => $q->where('code', $codeOrName)->orWhere('name', $codeOrName))->firstOrFail();
}

function lineOf(JournalEntry $entry, Account $account): array
{
    $line = $entry->lines()->where('account_id', $account->id)->firstOrFail();

    return [(string) $line->debit, (string) $line->credit];
}

function aporte(array $s, string $amount = '200.00', string $date = '2026-10-10'): BookTransfer
{
    return app(BookTransferService::class)->transfer($s['user'], [
        'from' => $s['personal'], 'from_cash' => acc($s['personal'], '1.1.02')->id,
        'to' => $s['family'], 'to_cash' => acc($s['family'], '1.1.01')->id,
        'amount' => $amount, 'date' => $date, 'description' => 'Aporte de octubre',
    ]);
}

test('un aporte registra un asiento en cada contabilidad y los enlaza', function () {
    $s = transferSetup();
    $this->actingAs($s['user']);

    $t = aporte($s);

    $out = $t->fromEntry;
    $in = $t->toEntry;
    $aportesA = acc($s['personal'], 'Aportes a Familia Maita');

    expect($out->book_id)->toBe($s['personal']->id)
        ->and($in->book_id)->toBe($s['family']->id)
        ->and($aportesA->parent->code)->toBe('6.5')
        ->and(lineOf($out, $aportesA))->toBe(['200.00', '0.00'])
        ->and(lineOf($out, acc($s['personal'], '1.1.02')))->toBe(['0.00', '200.00'])
        ->and(lineOf($in, acc($s['family'], '1.1.01')))->toBe(['200.00', '0.00'])
        ->and(lineOf($in, acc($s['family'], 'Aportes de Carlos')))->toBe(['0.00', '200.00'])
        ->and($in->description)->toContain('Contabilidad personal (Carlos)');

    $reports = app(ReportService::class);
    expect($reports->balanceSheet($s['family']->id, '2026-12-31')['check']['balanced'])->toBeTrue()
        ->and($reports->balanceSheet($s['personal']->id, '2026-12-31')['check']['balanced'])->toBeTrue();
});

test('un reintegro del fondo a la personal usa ingreso en la personal', function () {
    $s = transferSetup();
    $this->actingAs($s['user']);

    $t = app(BookTransferService::class)->transfer($s['user'], [
        'from' => $s['family'], 'from_cash' => acc($s['family'], '1.1.01')->id,
        'to' => $s['personal'], 'to_cash' => acc($s['personal'], '1.1.02')->id,
        'amount' => '50', 'date' => '2026-10-11', 'description' => 'Reintegro',
    ]);

    expect(lineOf($t->fromEntry, acc($s['family'], 'Aportes de Carlos')))->toBe(['50.00', '0.00'])
        ->and(acc($s['personal'], 'Reintegros de Familia Maita')->parent->code)->toBe('4.3');
});

test('cuentas de otra contabilidad, período cerrado o no ser miembro no registran nada', function () {
    $s = transferSetup();
    $this->actingAs($s['user']);
    $base = ['from' => $s['personal'], 'to' => $s['family'], 'amount' => '10', 'date' => '2026-10-10', 'description' => 'x'];

    // Cuenta de salida que pertenece a la familiar.
    expect(fn () => app(BookTransferService::class)->transfer($s['user'], $base + [
        'from_cash' => acc($s['family'], '1.1.01')->id, 'to_cash' => acc($s['family'], '1.1.01')->id,
    ]))->toThrow(ValidationException::class);

    // Período cerrado en el destino: tampoco queda el asiento de salida.
    $s['family']->update(['accounting_closed_until' => '2026-10-31']);
    expect(fn () => aporte($s))->toThrow(RuntimeException::class);

    // Alguien que no es miembro del destino.
    $intruso = User::factory()->create();
    $otra = app(BookService::class)->create(User::factory()->create(), 'Ajena', 'household');
    expect(fn () => app(BookTransferService::class)->transfer($intruso, [
        'from' => bookOf($intruso), 'from_cash' => 1, 'to' => $otra, 'to_cash' => 1, 'amount' => '1', 'date' => '2026-10-10', 'description' => 'x',
    ]))->toThrow(HttpException::class);

    expect(JournalEntry::withoutGlobalScopes()->count())->toBe(0)
        ->and(BookTransfer::count())->toBe(0);
});

test('revertir un lado de la transferencia revierte los dos', function () {
    $s = transferSetup();
    $this->actingAs($s['user']);
    $t = aporte($s);

    Livewire::test(JournalBook::class)
        ->assertSee('Transferencia ↔ Familia Maita')
        ->call('reverse', $t->from_entry_id);

    expect($t->fromEntry->fresh()->status)->toBe(JournalEntryStatus::Reversed)
        ->and($t->toEntry->fresh()->status)->toBe(JournalEntryStatus::Reversed)
        ->and(JournalEntry::withoutGlobalScopes()->where('book_id', $s['family']->id)->count())->toBe(2)
        ->and(app(ReportService::class)->balanceSheet($s['family']->id, '2026-12-31')['check']['assets'])->toBe('0.00');
});

test('la pantalla precarga origen y destino y registra la transferencia', function () {
    $s = transferSetup();
    $this->actingAs($s['user'])->post(route('books.switch', $s['family']));
    CurrentBook::flush();

    $this->get(route('transfers.create'))->assertOk()->assertSee('Transferir entre contabilidades');

    Livewire::test(TransferBetweenBooks::class)
        ->assertSet('fromBookId', (string) $s['personal']->id)
        ->assertSet('toBookId', (string) $s['family']->id)
        ->assertSet('toCash', (string) acc($s['family'], '1.1.01')->id)
        ->set('fromCash', (string) acc($s['personal'], '1.1.02')->id)
        ->set('amount', '75.25')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Transferencia registrada');

    expect(BookTransfer::sole()->amount)->toBe('75.25');

    $this->get(route('dashboard'))->assertSee('Aportar desde mi contabilidad');
});
