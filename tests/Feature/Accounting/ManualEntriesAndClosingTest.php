<?php

use App\Models\User;
use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Livewire\Dashboard;
use App\Modules\Accounting\Livewire\JournalBook;
use App\Modules\Accounting\Livewire\ManualEntryForm;
use App\Modules\Accounting\Livewire\Reports;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\MappingLine;
use App\Modules\Accounting\Services\JournalEntryBuilder;
use App\Modules\Accounting\Services\PostExecutionToJournal;
use App\Modules\Operations\Actions\ExecuteOperation;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Operations\Models\OperationVariable;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * @return array{caja: Account, gastos: Account}
 */
function setupManual(User $user): array
{
    return [
        'caja' => Account::factory()->for($user)->create(['code' => '1.1', 'name' => 'Caja', 'type' => 'asset']),
        'gastos' => Account::factory()->for($user)->create(['code' => '5.1', 'name' => 'Gastos', 'type' => 'expense']),
    ];
}

function manualLines(array $s, string $amount = '100.00'): array
{
    return [
        ['side' => 'debit', 'account_id' => (string) $s['gastos']->id, 'amount' => $amount, 'memo' => ''],
        ['side' => 'credit', 'account_id' => (string) $s['caja']->id, 'amount' => $amount, 'memo' => 'pago en efectivo'],
    ];
}

test('se puede registrar un asiento manual balanceado', function () {
    $user = User::factory()->create();
    $s = setupManual($user);
    $this->actingAs($user);

    Livewire::test(ManualEntryForm::class)
        ->set('date', '2026-09-08')
        ->set('description', 'Ajuste de caja')
        ->set('lines', manualLines($s))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('journal.index'));

    $entry = JournalEntry::first();

    expect($entry)->not->toBeNull()
        ->and($entry->number)->toBe(1)
        ->and($entry->description)->toBe('Ajuste de caja')
        ->and($entry->operation_execution_id)->toBeNull()
        ->and($entry->lines)->toHaveCount(2)
        ->and((string) $entry->lines->firstWhere('account_id', $s['caja']->id)->credit)->toBe('100.00')
        ->and($entry->lines->firstWhere('account_id', $s['caja']->id)->memo)->toBe('pago en efectivo');
});

test('un asiento manual desbalanceado no se registra y muestra el error', function () {
    $user = User::factory()->create();
    $s = setupManual($user);
    $this->actingAs($user);

    $lines = manualLines($s);
    $lines[1]['amount'] = '90.00';

    Livewire::test(ManualEntryForm::class)
        ->set('description', 'Desbalanceado')
        ->set('lines', $lines)
        ->call('save')
        ->assertHasErrors(['lines']);

    expect(JournalEntry::count())->toBe(0);
});

test('un asiento manual se puede revertir desde el diario', function () {
    $user = User::factory()->create();
    $s = setupManual($user);
    $this->actingAs($user);

    $entry = app(JournalEntryBuilder::class)->post(
        userId: $user->id, date: '2026-09-08', description: 'Ajuste',
        lines: [
            ['side' => EntrySide::Debit, 'account_id' => $s['gastos']->id, 'amount' => '50.00', 'memo' => null],
            ['side' => EntrySide::Credit, 'account_id' => $s['caja']->id, 'amount' => '50.00', 'memo' => null],
        ],
    );

    Livewire::test(JournalBook::class)->call('reverse', $entry->id);

    expect($entry->fresh()->status)->toBe(JournalEntryStatus::Reversed)
        ->and(JournalEntry::where('reverses_entry_id', $entry->id)->exists())->toBeTrue();
});

test('revertir desde el diario un asiento de una operación anula la ejecución', function () {
    $user = User::factory()->create();
    $s = setupManual($user);
    $this->actingAs($user);

    $type = OperationType::factory()->for($user)->create(['code' => 'pago']);
    OperationVariable::factory()->for($type, 'operationType')->create(['name' => 'monto', 'label' => 'Monto', 'type' => 'decimal']);
    $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $type->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['gastos']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => $s['caja']->id]);

    $execution = app(ExecuteOperation::class)->execute($type, ['monto' => '80.00'], '2026-09-08');
    $entry = JournalEntry::whereNotNull('operation_execution_id')->first();

    Livewire::test(JournalBook::class)->call('reverse', $entry->id);

    expect($execution->fresh()->status)->toBe(ExecutionStatus::Voided)
        ->and($entry->fresh()->status)->toBe(JournalEntryStatus::Reversed);
});

test('el período cerrado bloquea asientos manuales y reversiones con fecha cerrada', function () {
    $user = User::factory()->create(['accounting_closed_until' => '2026-08-31']);
    $s = setupManual($user);
    $this->actingAs($user);

    // Asiento manual dentro del período cerrado: rechazado.
    Livewire::test(ManualEntryForm::class)
        ->set('date', '2026-08-15')
        ->set('description', 'Fuera de término')
        ->set('lines', manualLines($s))
        ->call('save')
        ->assertHasErrors(['lines']);

    expect(JournalEntry::count())->toBe(0);

    // Con fecha posterior al cierre: permitido.
    Livewire::test(ManualEntryForm::class)
        ->set('date', '2026-09-01')
        ->set('description', 'En término')
        ->set('lines', manualLines($s))
        ->call('save')
        ->assertHasNoErrors();

    expect(JournalEntry::count())->toBe(1);
});

test('el período cerrado bloquea la ejecución de operaciones con fecha cerrada', function () {
    $user = User::factory()->create(['accounting_closed_until' => '2026-08-31']);
    $this->actingAs($user);

    $type = OperationType::factory()->for($user)->create(['code' => 'pago']);
    OperationVariable::factory()->for($type, 'operationType')->create(['name' => 'monto', 'label' => 'Monto', 'type' => 'decimal']);

    expect(fn () => app(ExecuteOperation::class)->execute($type, ['monto' => '10.00'], '2026-08-15'))
        ->toThrow(ValidationException::class);

    expect(OperationExecution::count())->toBe(0);
});

test('el procesamiento retroactivo marca failed si el período ya está cerrado', function () {
    $user = User::factory()->create();
    $s = setupManual($user);
    $this->actingAs($user);

    $type = OperationType::factory()->for($user)->create(['code' => 'pago']);
    OperationVariable::factory()->for($type, 'operationType')->create(['name' => 'monto', 'label' => 'Monto', 'type' => 'decimal']);

    // La ejecución entra con el período abierto y queda unmapped.
    $execution = app(ExecuteOperation::class)->execute($type, ['monto' => '10.00'], '2026-08-15');
    expect($execution->fresh()->status)->toBe(ExecutionStatus::Unmapped);

    // Luego se cierra el período y se configura el mapeo.
    $user->update(['accounting_closed_until' => '2026-08-31']);
    $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $type->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['gastos']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => $s['caja']->id]);

    app(PostExecutionToJournal::class)->post($execution);

    expect($execution->fresh()->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->fresh()->error_message)->toContain('cerrado');
});

test('el cierre de período se configura desde la página de reportes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Reports::class)
        ->set('closedUntil', '2026-08-31')
        ->call('saveClosedUntil')
        ->assertHasNoErrors();

    expect($user->fresh()->accounting_closed_until->toDateString())->toBe('2026-08-31');

    Livewire::test(Reports::class)
        ->set('closedUntil', '')
        ->call('saveClosedUntil');

    expect($user->fresh()->accounting_closed_until)->toBeNull();
});

test('el dashboard muestra KPIs, últimos asientos y eventos por atender', function () {
    $user = User::factory()->create();
    $s = setupManual($user);
    $this->actingAs($user);

    $ventas = Account::factory()->for($user)->create(['code' => '4.1', 'name' => 'Ventas', 'type' => 'income']);
    app(JournalEntryBuilder::class)->post(
        userId: $user->id, date: now()->toDateString(), description: 'Venta del mes',
        lines: [
            ['side' => EntrySide::Debit, 'account_id' => $s['caja']->id, 'amount' => '300.00', 'memo' => null],
            ['side' => EntrySide::Credit, 'account_id' => $ventas->id, 'amount' => '300.00', 'memo' => null],
        ],
    );

    $type = OperationType::factory()->for($user)->create(['code' => 'pago']);
    OperationExecution::factory()->for($user)->create([
        'operation_type_id' => $type->id,
        'status' => ExecutionStatus::Unmapped,
    ]);

    $this->get('/dashboard')->assertOk();

    Livewire::test(Dashboard::class)
        ->assertSee('Activo total')
        ->assertSee('300.00')
        ->assertSee('Venta del mes')
        ->assertSee('1 evento(s) sin contabilizar');
});
