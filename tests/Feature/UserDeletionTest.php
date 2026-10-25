<?php

use App\Models\User;
use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Models\MappingLine;
use App\Modules\Accounting\Services\ReverseJournalEntry;
use App\Modules\Operations\Actions\ExecuteOperation;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Operations\Models\OperationVariable;
use App\Modules\Shared\Books\Book;

test('eliminar la cuenta de usuario purga todos sus datos de negocio', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Datos completos: cuentas, operación con mapeo, ejecución contabilizada y reversión.
    $caja = Account::factory()->for(bookOf($user))->create(['code' => '1.1', 'name' => 'Caja', 'type' => 'asset']);
    $gasto = Account::factory()->for(bookOf($user))->create(['code' => '6.1', 'name' => 'Gastos', 'type' => 'expense']);

    $type = OperationType::factory()->for(bookOf($user))->create(['code' => 'pago']);
    OperationVariable::factory()->for($type, 'operationType')->create(['name' => 'monto', 'label' => 'Monto', 'type' => 'decimal']);
    $mapping = AccountingMapping::factory()->for(bookOf($user))->create(['operation_type_id' => $type->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $gasto->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => $caja->id]);

    app(ExecuteOperation::class)->execute($type, ['monto' => '100.00'], '2026-09-05');
    app(ReverseJournalEntry::class)->reverse(JournalEntry::first());

    // Otro usuario con datos, para verificar que no se toca lo ajeno.
    $other = User::factory()->create();
    $otherAccount = Account::factory()->for(bookOf($other))->create(['code' => '1.1']);

    auth()->logout();
    $userId = $user->id;
    $bookId = bookOf($user)->id;

    $user->delete();

    expect(User::find($userId))->toBeNull()
        ->and(Book::find($bookId))->toBeNull()
        ->and(Account::withoutGlobalScopes()->where('book_id', $bookId)->count())->toBe(0)
        ->and(JournalEntry::withoutGlobalScopes()->where('book_id', $bookId)->count())->toBe(0)
        ->and(JournalLine::count())->toBe(0)
        ->and(AccountingMapping::withoutGlobalScopes()->where('book_id', $bookId)->count())->toBe(0)
        ->and(OperationType::withoutGlobalScopes()->where('book_id', $bookId)->count())->toBe(0)
        ->and(OperationExecution::withoutGlobalScopes()->where('book_id', $bookId)->count())->toBe(0)
        // Lo del otro usuario sigue intacto.
        ->and(Account::withoutGlobalScopes()->find($otherAccount->id))->not->toBeNull();
});
